<?php

namespace App\Sri\Data;

use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use Spatie\LaravelData\Data;

/**
 * Un campo del bloque `infoAdicional`: pareja nombre/valor que el SRI
 * serializa como `<campoAdicional nombre="…">valor</campoAdicional>`.
 */
final class CampoAdicionalData extends Data
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
        public string $nombre,
        public string $valor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return [
            '_attributes' => ['nombre' => $this->nombre],
            '_value' => $this->valor,
        ];
    }
}
