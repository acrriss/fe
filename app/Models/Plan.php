<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan de consumo del microservicio.
 *
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property int $cuota_mensual
 * @property int $limite_por_minuto
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<Contribuyente, $this>
     */
    public function contribuyentes(): HasMany
    {
        return $this->hasMany(Contribuyente::class);
    }
}
