<?php

namespace App\Models;

use App\Sri\Certificados\CertificadoAbierto;
use App\Sri\Certificados\LectorPkcs12;
use App\Sri\Exceptions\CertificadoInvalido;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\CertificadoFirma;
use Database\Factories\ContribuyenteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use SensitiveParameter;

/**
 * Emisor de comprobantes electrónicos: dueño del RUC, del certificado de
 * firma y del logo. Agrupa usuarios y pertenece a un plan.
 *
 * @property int $id
 * @property string $uuid
 * @property string $ruc
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string|null $dir_matriz
 * @property string|null $logo_path
 * @property string|null $certificado_p12 base64 del .p12 (cifrado en reposo)
 * @property string|null $certificado_clave (cifrada en reposo)
 * @property string|null $certificado_titular
 * @property string|null $certificado_emisor
 * @property Carbon|null $certificado_valido_hasta
 * @property int|null $plan_id
 * @property-read Plan|null $plan
 */
class Contribuyente extends Model
{
    /** @use HasFactory<ContribuyenteFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'contribuyentes';

    protected $guarded = [];

    protected $hidden = ['certificado_p12', 'certificado_clave'];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Comprobante, $this>
     */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tieneCertificado(): bool
    {
        return $this->certificado_p12 !== null && $this->certificado_clave !== null;
    }

    /**
     * Valida y guarda el certificado: la clave debe abrir el .p12 y el
     * certificado debe estar vigente. Persiste también los metadatos
     * (titular, emisor, vencimiento) para el panel.
     *
     * @throws DatoInvalido si el base64 es inválido
     * @throws CertificadoInvalido si la clave es incorrecta, el archivo no
     *                             es un .p12 o el certificado está vencido
     */
    public function guardarCertificado(string $p12Base64, #[SensitiveParameter] string $clave): CertificadoAbierto
    {
        $certificado = CertificadoFirma::desdeBase64($p12Base64, $clave);

        $abierto = app(LectorPkcs12::class)->abrir($certificado);

        if ($abierto->vencido()) {
            throw CertificadoInvalido::vencido($abierto->validoHasta);
        }

        $this->update([
            'certificado_p12' => $p12Base64,
            'certificado_clave' => $clave,
            'certificado_titular' => $abierto->titular,
            'certificado_emisor' => $abierto->emisor,
            'certificado_valido_hasta' => $abierto->validoHasta,
        ]);

        return $abierto;
    }

    public function certificadoFirma(): CertificadoFirma
    {
        if (! $this->tieneCertificado()) {
            throw new \RuntimeException('El contribuyente no tiene un certificado de firma configurado.');
        }

        return CertificadoFirma::desdeBase64(
            (string) $this->certificado_p12,
            (string) $this->certificado_clave,
        );
    }

    /**
     * Comprobantes emitidos en el mes calendario en curso (para la cuota).
     */
    public function emisionesDelMes(): int
    {
        return $this->comprobantes()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /**
     * Un contribuyente sin plan no tiene cuota (uso interno/ilimitado).
     */
    public function agotoCuotaMensual(): bool
    {
        $plan = $this->plan;

        return $plan !== null && $this->emisionesDelMes() >= $plan->cuota_mensual;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'certificado_p12' => 'encrypted',
            'certificado_clave' => 'encrypted',
            'certificado_valido_hasta' => 'datetime',
        ];
    }
}
