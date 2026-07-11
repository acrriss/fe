<?php

namespace App\Sri\Firma;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Verifica firmas XAdES-BES de comprobantes del SRI: recomputa el digest
 * de cada referencia con canonicalización C14N inclusiva y valida el
 * SignatureValue (RSA-SHA1) con el certificado embebido en KeyInfo.
 *
 * Es el oráculo del firmador nativo: la salida del jar heredado (aceptada
 * por el SRI) debe verificar al 100% con este código, lo que prueba que
 * nuestra canonicalización es compatible con la del SRI.
 */
class VerificadorXades
{
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private const string TRANSFORM_ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    public function verificar(string $xml): ResultadoVerificacion
    {
        $documento = new DOMDocument;
        $documento->preserveWhiteSpace = true;

        if (! @$documento->loadXML($xml)) {
            throw new \InvalidArgumentException('El XML firmado no está bien formado.');
        }

        $xpath = $this->xpathDe($documento);
        $firma = $this->primerElemento($xpath, '//ds:Signature');

        if (! $firma instanceof DOMElement) {
            throw new \InvalidArgumentException('El documento no contiene una firma ds:Signature.');
        }

        return new ResultadoVerificacion(
            firmaValida: $this->verificarSignatureValue($xpath, $firma),
            referencias: $this->verificarReferencias($documento, $xpath, $firma),
        );
    }

    /**
     * @return array<string, bool>
     */
    private function verificarReferencias(DOMDocument $documento, DOMXPath $xpath, DOMElement $firma): array
    {
        $resultados = [];
        $referencias = $xpath->query('./ds:SignedInfo/ds:Reference', $firma) ?: [];

        foreach ($referencias as $referencia) {
            if (! $referencia instanceof DOMElement) {
                continue;
            }

            $uri = $referencia->getAttribute('URI');
            $declarado = $this->base64Limpio($xpath->evaluate('string(./ds:DigestValue)', $referencia));
            $calculado = $this->digestDeReferencia($documento, $referencia, $uri);

            $resultados[$uri] = $calculado !== null && $calculado === $declarado;
        }

        return $resultados;
    }

    private function digestDeReferencia(DOMDocument $documento, DOMElement $referencia, string $uri): ?string
    {
        $xpath = $this->xpathDe($documento);

        // el transform enveloped-signature exige computar el digest sobre
        // el documento SIN el nodo de la firma: se trabaja sobre una copia
        $transforms = $xpath->query("./ds:Transforms/ds:Transform[@Algorithm='".self::TRANSFORM_ENVELOPED."']", $referencia);

        if ($transforms !== false && $transforms->count() > 0) {
            $documento = $this->documentoSinFirma($documento);
            $xpath = $this->xpathDe($documento);
        }

        $nodo = $this->resolverUri($xpath, $uri);

        if (! $nodo instanceof DOMElement) {
            return null;
        }

        return base64_encode(sha1($nodo->C14N(false, false), true));
    }

    private function verificarSignatureValue(DOMXPath $xpath, DOMElement $firma): bool
    {
        $signedInfo = $this->primerElemento($xpath, './ds:SignedInfo', $firma);

        if (! $signedInfo instanceof DOMElement) {
            return false;
        }

        $firmaBinaria = base64_decode($this->base64Limpio(
            $xpath->evaluate('string(./ds:SignatureValue)', $firma),
        ), true);

        $certificado = $this->certificadoPem($xpath, $firma);

        if ($firmaBinaria === false || $certificado === null) {
            return false;
        }

        $clavePublica = openssl_pkey_get_public($certificado);

        return $clavePublica !== false
            && openssl_verify($signedInfo->C14N(false, false), $firmaBinaria, $clavePublica, OPENSSL_ALGO_SHA1) === 1;
    }

    private function certificadoPem(DOMXPath $xpath, DOMElement $firma): ?string
    {
        $base64 = $this->base64Limpio(
            $xpath->evaluate('string(./ds:KeyInfo/ds:X509Data/ds:X509Certificate)', $firma),
        );

        if ($base64 === '') {
            return null;
        }

        return "-----BEGIN CERTIFICATE-----\n"
            .chunk_split($base64, 64, "\n")
            ."-----END CERTIFICATE-----\n";
    }

    /**
     * Copia del documento sin el nodo ds:Signature (transform enveloped).
     */
    private function documentoSinFirma(DOMDocument $documento): DOMDocument
    {
        $copia = new DOMDocument;
        $copia->preserveWhiteSpace = true;
        $copia->loadXML((string) $documento->saveXML());

        $firma = $this->primerElemento($this->xpathDe($copia), '//ds:Signature');

        if ($firma !== null) {
            $firma->parentNode?->removeChild($firma);
        }

        return $copia;
    }

    /**
     * Resuelve una URI de referencia (#id) buscando por atributo Id o id
     * (el SRI usa `id="comprobante"` en minúsculas para la raíz).
     */
    private function resolverUri(DOMXPath $xpath, string $uri): ?DOMElement
    {
        $id = ltrim($uri, '#');

        if ($id === '' || preg_match('/^[\w.-]+$/', $id) !== 1) {
            return null;
        }

        return $this->primerElemento($xpath, "//*[@Id='{$id}' or @id='{$id}']");
    }

    private function primerElemento(DOMXPath $xpath, string $consulta, ?DOMElement $contexto = null): ?DOMElement
    {
        $nodos = $xpath->query($consulta, $contexto);
        $nodo = $nodos === false ? null : $nodos->item(0);

        return $nodo instanceof DOMElement ? $nodo : null;
    }

    private function xpathDe(DOMDocument $documento): DOMXPath
    {
        $xpath = new DOMXPath($documento);
        $xpath->registerNamespace('ds', self::NS_DS);

        return $xpath;
    }

    private function base64Limpio(mixed $valor): string
    {
        return is_string($valor) ? (string) preg_replace('/\s+/', '', $valor) : '';
    }
}
