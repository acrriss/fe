<?php

namespace App\Sri\Data\NotaDebito;

use Spatie\LaravelData\Data;

/**
 * Motivo del débito (<motivos><motivo>): razón y valor.
 */
final class MotivoData extends Data
{
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
