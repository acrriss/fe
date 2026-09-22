<?php

namespace App\Sri\Data\NotaDebito;

use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use Spatie\LaravelData\Data;

/**
 * Motivo del débito (<motivos><motivo>): razón y valor.
 */
final class MotivoData extends Data
{
    use RechazaClavesDesconocidas;

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        return self::soloClavesConocidas($properties);
    }

    public function __construct(
        public string $razon,
        public string $valor,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return [
            'razon' => $this->razon,
            'valor' => $this->valor,
        ];
    }
}
