<?php

namespace App\Models;

use App\Sri\Enums\EstadoEntregaWebhook;
use Database\Factories\WebhookEntregaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Entrega de un evento a un endpoint de webhook: registro consultable del
 * resultado de cada notificación (§11). El job de envío la actualiza en
 * cada intento.
 *
 * @property int $id
 * @property string $uuid
 * @property int $webhook_endpoint_id
 * @property string $evento
 * @property array<string, mixed> $payload
 * @property EstadoEntregaWebhook $estado
 * @property int $intentos
 * @property int|null $codigo_http
 * @property string|null $error
 * @property Carbon|null $entregado_en
 * @property-read WebhookEndpoint|null $endpoint
 */
class WebhookEntrega extends Model
{
    /** @use HasFactory<WebhookEntregaFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'webhook_entregas';

    protected $guarded = [];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'estado' => EstadoEntregaWebhook::class,
            'entregado_en' => 'datetime',
        ];
    }
}
