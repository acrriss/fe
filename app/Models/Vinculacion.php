<?php

namespace App\Models;

use App\Sri\Enums\EstadoVinculacion;
use Database\Factories\VinculacionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Solicitud de vinculación (§11, 7d): un partner pide gestionar un RUC
 * que ya existe como cuenta directa. El dueño la aprueba o rechaza desde
 * su panel; al aprobar, el contribuyente pasa a ser gestionado
 * (partner_id) conservando sus usuarios directos, y sus emisiones pasan
 * a consumir la cuota pool del partner.
 *
 * @property int $id
 * @property string $uuid
 * @property int $partner_id
 * @property int $contribuyente_id
 * @property EstadoVinculacion $estado
 * @property Carbon|null $resuelta_en
 * @property-read Partner|null $partner
 * @property-read Contribuyente|null $contribuyente
 */
class Vinculacion extends Model
{
    /** @use HasFactory<VinculacionFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'vinculaciones';

    protected $guarded = [];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * @return BelongsTo<Contribuyente, $this>
     */
    public function contribuyente(): BelongsTo
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function pendiente(): bool
    {
        return $this->estado === EstadoVinculacion::Pendiente;
    }

    public function aprobar(): void
    {
        $this->update(['estado' => EstadoVinculacion::Aprobada, 'resuelta_en' => now()]);
        $this->contribuyente?->update(['partner_id' => $this->partner_id]);
    }

    public function rechazar(): void
    {
        $this->update(['estado' => EstadoVinculacion::Rechazada, 'resuelta_en' => now()]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoVinculacion::class,
            'resuelta_en' => 'datetime',
        ];
    }
}
