<?php

namespace App\Models;

use App\Jobs\EnviarWebhookJob;
use App\Sri\Enums\EventoWebhook;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Endpoint de webhook (§11): URL suscrita a eventos del servicio. El
 * suscriptor es un Partner (recibe los eventos de todos sus contribuyentes
 * gestionados) o un Contribuyente (solo los suyos). Cada entrega se firma
 * con el secreto del endpoint (HMAC-SHA256).
 *
 * @property int $id
 * @property string $uuid
 * @property string $suscriptor_type
 * @property int $suscriptor_id
 * @property string $url
 * @property string $secreto (cifrado en reposo)
 * @property array<int, string> $eventos
 * @property bool $activo
 */
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'webhook_endpoints';

    protected $guarded = [];

    protected $hidden = ['secreto'];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public static function generarSecreto(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function suscriptor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WebhookEntrega, $this>
     */
    public function entregas(): HasMany
    {
        return $this->hasMany(WebhookEntrega::class);
    }

    /**
     * Publica un evento de un contribuyente: crea una entrega por cada
     * endpoint suscrito (los del contribuyente y los de su partner, si
     * tiene) y encola su envío firmado.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function publicar(EventoWebhook $evento, Contribuyente $contribuyente, array $datos): void
    {
        $endpoints = static::query()
            ->where('activo', true)
            ->whereJsonContains('eventos', $evento->value)
            ->where(function (Builder $query) use ($contribuyente): void {
                $query->whereMorphedTo('suscriptor', $contribuyente);

                if ($contribuyente->partner !== null) {
                    $query->orWhereMorphedTo('suscriptor', $contribuyente->partner);
                }
            })
            ->get();

        foreach ($endpoints as $endpoint) {
            $entrega = $endpoint->entregas()->create([
                'evento' => $evento->value,
                'payload' => [
                    'evento' => $evento->value,
                    'publicadoEn' => now()->toIso8601String(),
                    'contribuyente' => [
                        'id' => $contribuyente->uuid,
                        'ruc' => $contribuyente->ruc,
                    ],
                    'datos' => $datos,
                ],
            ]);

            EnviarWebhookJob::dispatch($entrega);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secreto' => 'encrypted',
            'eventos' => 'array',
            'activo' => 'boolean',
        ];
    }
}
