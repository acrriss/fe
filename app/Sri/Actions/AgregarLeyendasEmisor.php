<?php

namespace App\Sri\Actions;

use App\Sri\Data\ComprobanteData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\ValueObjects\LeyendasEmisor;
use Closure;

/**
 * Vuelca en el comprobante las designaciones del emisor que la ficha obliga
 * a imprimir como leyenda (Anexo 21: agente de retención; Anexo 22: régimen
 * RIMPE; Anexo 24: gran contribuyente; Tabla 11 fila 8: contribuyente
 * especial).
 *
 * Como con el RUC del proveedor, van como etapa del pipeline y no en el
 * DTO que arma el cliente: son un atributo del contribuyente configurado
 * una vez, y así todos sus integradores cumplen sin tocar el payload.
 */
final class AgregarLeyendasEmisor
{
    /** Nombre literal del campo adicional que fija el Anexo 24. */
    public const string CAMPO_GRAN_CONTRIBUYENTE = 'Gran Contribuyente';

    /**
     * El Anexo 24 lo exige en «comprobantes de venta, notas de crédito y
     * notas de débito»: quedan fuera la retención y la guía de remisión.
     * La liquidación de compra entra por ser comprobante de venta
     * (Reglamento de Comprobantes de Venta, art. 1).
     *
     * @var list<TipoComprobante>
     */
    private const array TIPOS_GRAN_CONTRIBUYENTE = [
        TipoComprobante::Factura,
        TipoComprobante::LiquidacionCompra,
        TipoComprobante::NotaCredito,
        TipoComprobante::NotaDebito,
    ];

    public function __invoke(EmisionEnCurso $emision, Closure $next): mixed
    {
        self::agregar($emision->comprobante, $emision->leyendas);

        return $next($emision);
    }

    /**
     * Las leyendas son una afirmación sobre el emisor, no un dato de la
     * transacción: si el cliente las manda, se rechaza, para que no pueda
     * declararse agente de retención (o dejar de serlo) factura a factura.
     * Se comprueba sobre el payload y no al construir el XML, para no
     * romper la relectura del XML ya emitido (que sí las lleva) al generar
     * el RIDE.
     */
    public static function rechazarSiVieneEnElPayload(ComprobanteData $comprobante): void
    {
        if ($comprobante->infoTributaria->agenteRetencion !== null) {
            throw self::loFijaLaConfiguracion('agenteRetencion');
        }

        if ($comprobante->infoTributaria->contribuyenteRimpe !== null) {
            throw self::loFijaLaConfiguracion('contribuyenteRimpe');
        }

        if ($comprobante->bloqueInfo()->contribuyenteEspecial !== null) {
            throw self::loFijaLaConfiguracion('contribuyenteEspecial');
        }

        if ($comprobante->tieneCampoAdicional(self::CAMPO_GRAN_CONTRIBUYENTE)) {
            throw self::loFijaLaConfiguracion(self::CAMPO_GRAN_CONTRIBUYENTE);
        }
    }

    public static function agregar(ComprobanteData $comprobante, LeyendasEmisor $leyendas): void
    {
        $comprobante->infoTributaria->agenteRetencion = $leyendas->agenteRetencion;
        $comprobante->infoTributaria->contribuyenteRimpe = $leyendas->leyendaRimpe();
        $comprobante->bloqueInfo()->contribuyenteEspecial = $leyendas->contribuyenteEspecial;

        if ($leyendas->granContribuyente !== null && self::aplicaGranContribuyente($comprobante::tipo())) {
            $comprobante->agregarCampoAdicional(self::CAMPO_GRAN_CONTRIBUYENTE, $leyendas->granContribuyente);
        }
    }

    public static function aplicaGranContribuyente(TipoComprobante $tipo): bool
    {
        return in_array($tipo, self::TIPOS_GRAN_CONTRIBUYENTE, true);
    }

    private static function loFijaLaConfiguracion(string $campo): DatoInvalido
    {
        return new DatoInvalido(
            "El campo «{$campo}» lo fija la configuración del contribuyente en el servicio "
            .'de facturación electrónica: no debe enviarse en el comprobante.',
        );
    }
}
