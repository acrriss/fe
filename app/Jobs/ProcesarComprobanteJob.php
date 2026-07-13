<?php

namespace App\Jobs;

use App\Models\Comprobante;
use App\Sri\Data\ComprobanteData;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use App\Sri\ValueObjects\ClaveAcceso;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Emisión asíncrona: ejecuta el MISMO pipeline que el endpoint síncrono y
 * persiste el resultado en el registro.
 *
 * El certificado de firma NO viaja en el job: se lee del contribuyente
 * dueño del registro al momento de procesar. El payload va cifrado de
 * todos modos (ShouldBeEncrypted) por contener datos del comprobante.
 */
class ProcesarComprobanteJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public int $timeout = 120;

    /**
     * @param  class-string<ComprobanteData>  $dataClass
     * @param  array<string, mixed>  $payloadComprobante
     * @param  string|null  $claveAcceso  clave a reutilizar (reintentos, §5.10)
     */
    public function __construct(
        public Comprobante $registro,
        public string $dataClass,
        public array $payloadComprobante,
        public ?string $claveAcceso = null,
    ) {}

    public function handle(EmitirComprobante $pipeline, RegistroDeEmision $registro): void
    {
        $contribuyente = $this->registro->contribuyente
            ?? throw new \RuntimeException('El registro no tiene contribuyente asociado.');

        $emision = new EmisionEnCurso(
            comprobante: $this->dataClass::from($this->payloadComprobante),
            certificado: $contribuyente->certificadoFirma(),
        );

        if ($this->claveAcceso !== null) {
            $emision->claveAcceso = ClaveAcceso::fromString($this->claveAcceso);
        }

        try {
            $registro->completar($this->registro, $pipeline->emitir($emision));
        } catch (EmisionFallida $fallo) {
            // Fallo de negocio (devuelto/no autorizado): el job terminó su
            // trabajo correctamente, el resultado vive en el registro.
            $registro->fallar($this->registro, $fallo, $emision);
        }
    }

    /**
     * Fallo técnico agotados los reintentos (SRI caído, error inesperado).
     */
    public function failed(?Throwable $excepcion): void
    {
        app(RegistroDeEmision::class)->fallarPorErrorTecnico(
            $this->registro,
            'La emisión falló por un error técnico; reintente más tarde.',
        );
    }
}
