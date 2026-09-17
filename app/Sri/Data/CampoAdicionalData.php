<?php

namespace App\Sri\Data;

use Spatie\LaravelData\Data;

/**
 * Un campo del bloque `infoAdicional`: pareja nombre/valor que el SRI
 * serializa como `<campoAdicional nombre="…">valor</campoAdicional>`.
 */
final class CampoAdicionalData extends Data
{
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
