<?php

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Partner/plataforma integradora (§11): sistema tercero que aprovisiona
 * contribuyentes y emite en su nombre autenticándose con un token Sanctum
 * propio (el modelo es tokenable, igual que User). Su cuota mensual es un
 * pool que comparten todos sus contribuyentes gestionados.
 *
 * @property int $id
 * @property string $uuid
 * @property string $nombre
 * @property string $slug
 * @property int|null $cuota_mensual null = sin cuota (ilimitado)
 * @property int $limite_por_minuto
 */
class Partner extends Authenticatable
{
    /** @use HasFactory<PartnerFactory> */
    use HasApiTokens, HasFactory;

    use HasUuids;

    protected $guarded = [];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return HasMany<Contribuyente, $this>
     */
    public function contribuyentes(): HasMany
    {
        return $this->hasMany(Contribuyente::class);
    }

    /**
     * @return HasManyThrough<Comprobante, Contribuyente, $this>
     */
    public function comprobantes(): HasManyThrough
    {
        return $this->hasManyThrough(Comprobante::class, Contribuyente::class);
    }

    /**
     * Emisiones del mes en curso de TODOS sus contribuyentes (cuota pool).
     */
    public function emisionesDelMes(): int
    {
        return $this->comprobantes()
            ->whereBetween('comprobantes.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    public function agotoCuotaMensual(): bool
    {
        return $this->cuota_mensual !== null && $this->emisionesDelMes() >= $this->cuota_mensual;
    }
}
