<?php

namespace App\Models;

use Database\Factories\ClaveIdempotenciaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de una Idempotency-Key usada en la emisión (§11): la clave es
 * única por contribuyente, guarda la huella del request original (método,
 * URI y cuerpo) y la respuesta final. Un reintento con la misma clave y
 * la misma huella devuelve la respuesta guardada; con otra huella, 409.
 *
 * `respuesta` null = la petición original sigue en curso.
 *
 * @property int $id
 * @property int $contribuyente_id
 * @property string $clave
 * @property string $huella sha256 de "método|uri|cuerpo"
 * @property int|null $codigo_http
 * @property string|null $respuesta cuerpo JSON de la respuesta original
 * @property-read Contribuyente|null $contribuyente
 */
class ClaveIdempotencia extends Model
{
    /** @use HasFactory<ClaveIdempotenciaFactory> */
    use HasFactory;

    use MassPrunable;

    protected $table = 'claves_idempotencia';

    protected $guarded = [];

    /**
     * @return BelongsTo<Contribuyente, $this>
     */
    public function contribuyente(): BelongsTo
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function respondida(): bool
    {
        return $this->respuesta !== null;
    }

    public function expirada(): bool
    {
        return $this->created_at !== null
            && $this->created_at->addHours(config()->integer('sri.idempotencia.ttl_horas', 24))->isPast();
    }

    /**
     * La petición original sigue "en curso" solo durante una ventana
     * corta: si el proceso murió sin responder, pasada la ventana se
     * permite reprocesar en lugar de bloquear la clave para siempre.
     */
    public function enCursoVigente(): bool
    {
        return ! $this->respondida()
            && $this->updated_at !== null
            && $this->updated_at->addSeconds(config()->integer('sri.idempotencia.en_curso_segundos', 90))->isFuture();
    }

    /**
     * Expiración (model:prune diario): las claves viejas se borran y la
     * clave puede reutilizarse.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('created_at', '<', now()->subHours(config()->integer('sri.idempotencia.ttl_horas', 24)));
    }
}
