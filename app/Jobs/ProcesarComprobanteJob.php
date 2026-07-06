<?php

namespace App\Jobs;

use App\Models\Comprobante;
use App\Sri\Data\ComprobanteData;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionComprobante;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use App\Sri\ValueObjects\CertificadoFirma;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Emisión asíncrona: ejecuta el MISMO pipeline que el endpoint síncrono y
 * persiste el resultado en el registro.
 *
 * ShouldBeEncrypted es obligatorio: el payload transporta el certificado
 * .p12 y su clave, que nunca deben quedar legibles en la tabla de colas.
 * (En la fase 6, con certificados almacenados por usuario, dejarán de
 * viajar en el job.)
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
     */
    public function __construct(
        public Comprobante $registro,
        public string $dataClass,
        public array $payloadComprobante,
        public string $p12Base64,
        public string $claveP12,
    ) {}

    public function handle(EmitirComprobante $pipeline, RegistroDeEmision $registro): void
    {
        $emision = new EmisionComprobante(
            comprobante: $this->dataClass::from($this->payloadComprobante),
            certificado: CertificadoFirma::desdeBase64($this->p12Base64, $this->claveP12),
        );

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
        $this->registro->update([
            'estado' => EstadoComprobante::Fallido,
            'mensajes' => ['La emisión falló por un error técnico; reintente más tarde.'],
        ]);
    }
}
