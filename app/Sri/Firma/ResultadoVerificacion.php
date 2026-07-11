<?php

namespace App\Sri\Firma;

/**
 * Resultado de verificar una firma XAdES-BES: la validez criptográfica de
 * la firma y la integridad de cada referencia (digest recomputado vs.
 * declarado), útil para diagnosticar exactamente qué parte falla.
 */
final readonly class ResultadoVerificacion
{
    /**
     * @param  bool  $firmaValida  SignatureValue verifica contra SignedInfo
     * @param  array<string, bool>  $referencias  URI => digest íntegro
     */
    public function __construct(
        public bool $firmaValida,
        public array $referencias,
    ) {}

    public function esValida(): bool
    {
        return $this->firmaValida
            && $this->referencias !== []
            && ! in_array(false, $this->referencias, true);
    }
}
