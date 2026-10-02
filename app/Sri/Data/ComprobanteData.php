<?php

namespace App\Sri\Data;

use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Base de todos los comprobantes electrónicos. Cada subtipo declara su
 * TipoComprobante, del que se derivan codDoc, elemento raíz del XML y
 * versión del esquema (nunca se confía en el codDoc del payload).
 */
abstract class ComprobanteData extends Data
{
    use RechazaClavesDesconocidas;

    abstract public static function tipo(): TipoComprobante;

    /**
     * Fecha de emisión del documento (vive en el bloque info* de cada tipo).
     */
    abstract public function fechaEmision(): CarbonImmutable;

    /**
     * El bloque info* del tipo (infoFactura, infoNotaCredito…), para lo que
     * el esquema repite en todos ellos, como la leyenda de contribuyente
     * especial.
     */
    abstract public function bloqueInfo(): BloqueInfoData;

    /**
     * Importe del documento, si el tipo lo declara (la retención no tiene).
     */
    public function importeTotal(): ?string
    {
        return null;
    }

    /**
     * Formas de pago (<pagos>), en los tipos que las llevan: factura, nota
     * de débito y liquidación de compra.
     *
     * @return array<int, PagoData>
     */
    public function formasDePago(): array
    {
        return [];
    }

    /**
     * Las formas de pago tienen que sumar exactamente el importe del
     * documento. La ficha no lo exige y el SRI de pruebas autoriza
     * comprobantes que no cuadran, pero un documento que dice cobrar más o
     * menos de lo que vale es un error del integrador: el servicio lo
     * rechaza al emitir.
     *
     * Se comprueba sobre el payload y no al construir el DTO, para no romper
     * la relectura de un XML ya autorizado que no cuadrara (el RIDE).
     *
     * @throws DatoInvalido si no suman el importe
     */
    public function exigirQueLosPagosSumenElTotal(): void
    {
        $pagos = $this->formasDePago();
        $total = $this->importeTotal();

        if ($pagos === [] || $total === null) {
            return;
        }

        $suma = '0';

        foreach ($pagos as $pago) {
            if (! is_numeric($pago->total)) {
                throw DatoInvalido::porFormato('pagos.pago.total', 'un importe numérico', $pago->total);
            }

            $suma = bcadd($suma, $pago->total, 2);
        }

        if (! is_numeric($total)) {
            throw DatoInvalido::porFormato('importeTotal', 'un importe numérico', $total);
        }

        if (bccomp($suma, $total, 2) !== 0) {
            throw new DatoInvalido(sprintf(
                'La suma de las formas de pago (%s) no coincide con el importe total del comprobante (%s). '
                .'El servicio lo exige aunque la ficha técnica no lo pida.',
                $suma,
                bcadd($total, '0', 2),
            ));
        }
    }

    /**
     * Representación como array listo para ArrayToXml, en el orden que exige
     * la ficha técnica del SRI. Requiere que la claveAcceso ya esté asignada.
     *
     * @return array<string, mixed>
     */
    abstract public function xmlArray(): array;

    public InfoTributariaData $infoTributaria;

    /**
     * Bloque `infoAdicional`, opcional y SIEMPRE el último elemento del
     * comprobante según la ficha del SRI.
     *
     * @var array<int, CampoAdicionalData>
     */
    public array $infoAdicional = [];

    /** Tope de campos que admite el esquema del SRI. */
    public const int MAXIMO_CAMPOS_ADICIONALES = 15;

    /**
     * Normaliza el wrapper `{infoAdicional: {campoAdicional: X}}` a lista.
     * Cada subtipo llama a este padre antes de lo suyo.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['infoAdicional'] = Payload::lista(
            data_get($properties, 'infoAdicional.campoAdicional'),
        );

        return $properties;
    }

    /**
     * Añade un campo a `infoAdicional` si no está ya (idempotente: el mismo
     * comprobante puede pasar dos veces por el pipeline).
     *
     * @throws DatoInvalido si el comprobante ya agotó los campos del esquema
     */
    public function agregarCampoAdicional(string $nombre, string $valor): void
    {
        if ($this->tieneCampoAdicional($nombre)) {
            return;
        }

        if (count($this->infoAdicional) >= self::MAXIMO_CAMPOS_ADICIONALES) {
            throw new DatoInvalido(
                "No cabe el campo «{$nombre}»: el comprobante ya usa los "
                .self::MAXIMO_CAMPOS_ADICIONALES
                .' campos adicionales que admite el esquema del SRI.',
            );
        }

        $this->infoAdicional[] = new CampoAdicionalData($nombre, $valor);
    }

    public function tieneCampoAdicional(string $nombre): bool
    {
        foreach ($this->infoAdicional as $campo) {
            if ($campo->nombre === $nombre) {
                return true;
            }
        }

        return false;
    }

    /**
     * El bloque listo para ArrayToXml, o vacío si no hay campos: así un
     * comprobante sin información adicional produce el mismo XML que antes
     * de que existiera este soporte.
     *
     * @return array<string, mixed>
     */
    protected function infoAdicionalXml(): array
    {
        if ($this->infoAdicional === []) {
            return [];
        }

        return [
            'infoAdicional' => [
                'campoAdicional' => array_map(
                    fn (CampoAdicionalData $campo): array => $campo->xmlArray(),
                    $this->infoAdicional,
                ),
            ],
        ];
    }
}
