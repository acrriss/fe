<?php

namespace App\Models;

use App\Sri\Enums\Ambiente;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Enums\TipoComprobante;
use Database\Factories\ComprobanteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de una emisión de comprobante electrónico.
 *
 * @property int $id
 * @property string $uuid
 * @property TipoComprobante $tipo
 * @property EstadoComprobante $estado
 * @property Ambiente $ambiente
 * @property string|null $clave_acceso
 * @property string $ruc
 * @property string $razon_social
 * @property string $secuencial
 * @property string|null $importe_total
 * @property string|null $numero_autorizacion
 * @property Carbon|null $autorizado_en
 * @property array<int, string>|null $mensajes
 * @property string|null $xml_path
 * @property string|null $ride_path
 * @property Carbon|null $emitido_en
 * @property int|null $contribuyente_id
 * @property-read Contribuyente|null $contribuyente
 */
class Comprobante extends Model
{
    /** @use HasFactory<ComprobanteFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = [];

    /**
     * El id autoincremental nunca se expone: la API identifica el recurso
     * por uuid.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<Contribuyente, $this>
     */
    public function contribuyente(): BelongsTo
    {
        return $this->belongsTo(Contribuyente::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoComprobante::class,
            'estado' => EstadoComprobante::class,
            'ambiente' => Ambiente::class,
            'importe_total' => 'decimal:2',
            'mensajes' => 'array',
            'autorizado_en' => 'datetime',
            'emitido_en' => 'date',
        ];
    }
}
