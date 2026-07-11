<?php

namespace App\Sri\Firma;

use App\Sri\Certificados\CertificadoAbierto;
use App\Sri\Certificados\LectorPkcs12;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Exceptions\CertificadoInvalido;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\ValueObjects\CertificadoFirma;
use DOMDocument;
use DOMElement;

/**
 * Firma XAdES-BES nativa en PHP (ficha del SRI §6: esquema 1.3.2, firma
 * ENVELOPED, RSA-SHA1, digests SHA1, C14N inclusiva, KeyInfo firmado).
 *
 * Replica la estructura exacta del firmador jar heredado (verificada como
 * aceptada por el SRI) pero sin Java, sin procesos hijos y sin archivos
 * temporales: la clave privada nunca sale del proceso PHP.
 *
 * Toda firma producida se puede auditar con VerificadorXades.
 */
class XadesXmlSigner implements XmlSigner
{
    private const string NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private const string NS_ETSI = 'http://uri.etsi.org/01903/v1.3.2#';

    private const string NS_XMLNS = 'http://www.w3.org/2000/xmlns/';

    public function __construct(private readonly LectorPkcs12 $lector) {}

    public function firmar(string $xml, CertificadoFirma $certificado): string
    {
        try {
            $abierto = $this->lector->abrir($certificado);
        } catch (CertificadoInvalido $excepcion) {
            throw EmisionFallida::enFirma($excepcion->getMessage());
        }

        $documento = new DOMDocument;
        $documento->preserveWhiteSpace = true;

        if (! @$documento->loadXML($xml) || $documento->documentElement === null) {
            throw EmisionFallida::enFirma('el XML del comprobante no está bien formado.');
        }

        if ($documento->documentElement->getAttribute('id') !== 'comprobante') {
            throw EmisionFallida::enFirma('la raíz del comprobante debe tener id="comprobante".');
        }

        // digest del documento (transform enveloped): se calcula ANTES de
        // insertar la firma, que es exactamente "el documento sin la firma"
        $digestComprobante = $this->digest($documento->documentElement->C14N(false, false));

        $ids = $this->generarIds();
        $firma = $this->construirFirma($documento, $abierto, $ids);
        $documento->documentElement->appendChild($firma);

        // los digests de KeyInfo y SignedProperties requieren los nodos ya
        // insertados: la C14N inclusiva hereda los xmlns del contexto
        $this->asignarDigests($documento, $firma, $digestComprobante, $ids);
        $this->firmarSignedInfo($documento, $firma, $abierto);

        $resultado = $documento->saveXML();

        if ($resultado === false) {
            throw EmisionFallida::enFirma('no se pudo serializar el XML firmado.');
        }

        return $resultado;
    }

    /**
     * @return array<string, string>
     */
    private function generarIds(): array
    {
        $n = fn (): int => random_int(100000, 999999);
        $signature = 'Signature'.$n();

        return [
            'signature' => $signature,
            'signedInfo' => 'Signature-SignedInfo'.$n(),
            'signedPropertiesId' => 'SignedPropertiesID'.$n(),
            'signedProperties' => $signature.'-SignedProperties'.$n(),
            'certificate' => 'Certificate'.$n(),
            'referencia' => 'Reference-ID-'.$n(),
            'signatureValue' => 'SignatureValue'.$n(),
            'object' => $signature.'-Object'.$n(),
        ];
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function construirFirma(DOMDocument $documento, CertificadoAbierto $abierto, array $ids): DOMElement
    {
        $ds = fn (string $nombre): DOMElement => $documento->createElementNS(self::NS_DS, 'ds:'.$nombre);
        $etsi = fn (string $nombre): DOMElement => $documento->createElementNS(self::NS_ETSI, 'etsi:'.$nombre);
        $texto = fn (DOMElement $elemento, string $valor): DOMElement => tap($elemento, fn () => $elemento->appendChild($documento->createTextNode($valor)));

        $firma = $ds('Signature');
        $firma->setAttributeNS(self::NS_XMLNS, 'xmlns:etsi', self::NS_ETSI);
        $firma->setAttribute('Id', $ids['signature']);

        // --- SignedInfo -----------------------------------------------------
        $signedInfo = $ds('SignedInfo');
        $signedInfo->setAttribute('Id', $ids['signedInfo']);

        $c14n = $ds('CanonicalizationMethod');
        $c14n->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($c14n);

        $metodo = $ds('SignatureMethod');
        $metodo->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($metodo);

        $referenciaPropiedades = $ds('Reference');
        $referenciaPropiedades->setAttribute('Id', $ids['signedPropertiesId']);
        $referenciaPropiedades->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $referenciaPropiedades->setAttribute('URI', '#'.$ids['signedProperties']);
        $this->conDigestSha1($documento, $referenciaPropiedades);
        $signedInfo->appendChild($referenciaPropiedades);

        $referenciaCertificado = $ds('Reference');
        $referenciaCertificado->setAttribute('URI', '#'.$ids['certificate']);
        $this->conDigestSha1($documento, $referenciaCertificado);
        $signedInfo->appendChild($referenciaCertificado);

        $referenciaComprobante = $ds('Reference');
        $referenciaComprobante->setAttribute('Id', $ids['referencia']);
        $referenciaComprobante->setAttribute('URI', '#comprobante');
        $transforms = $ds('Transforms');
        $transform = $ds('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);
        $referenciaComprobante->appendChild($transforms);
        $this->conDigestSha1($documento, $referenciaComprobante);
        $signedInfo->appendChild($referenciaComprobante);

        $firma->appendChild($signedInfo);

        // --- SignatureValue (se completa al final) --------------------------
        $signatureValue = $ds('SignatureValue');
        $signatureValue->setAttribute('Id', $ids['signatureValue']);
        $firma->appendChild($signatureValue);

        // --- KeyInfo ---------------------------------------------------------
        $derBase64 = $this->derBase64($abierto->certificadoPem);
        $rsa = $this->componentesRsa($abierto->certificadoPem);

        $keyInfo = $ds('KeyInfo');
        $keyInfo->setAttribute('Id', $ids['certificate']);
        $x509Data = $ds('X509Data');
        $x509Data->appendChild($texto($ds('X509Certificate'), $this->base64EnLineas($derBase64)));
        $keyInfo->appendChild($x509Data);
        $keyValue = $ds('KeyValue');
        $rsaKeyValue = $ds('RSAKeyValue');
        $rsaKeyValue->appendChild($texto($ds('Modulus'), $this->base64EnLineas($rsa['modulo'])));
        $rsaKeyValue->appendChild($texto($ds('Exponent'), $rsa['exponente']));
        $keyValue->appendChild($rsaKeyValue);
        $keyInfo->appendChild($keyValue);
        $firma->appendChild($keyInfo);

        // --- Object > QualifyingProperties > SignedProperties ---------------
        $x509 = $this->parseX509($abierto->certificadoPem);

        $object = $ds('Object');
        $object->setAttribute('Id', $ids['object']);
        $qualifying = $etsi('QualifyingProperties');
        $qualifying->setAttribute('Target', '#'.$ids['signature']);

        $signedProperties = $etsi('SignedProperties');
        $signedProperties->setAttribute('Id', $ids['signedProperties']);

        $firmaProps = $etsi('SignedSignatureProperties');
        $firmaProps->appendChild($texto($etsi('SigningTime'), now()->format('c')));

        $signingCertificate = $etsi('SigningCertificate');
        $cert = $etsi('Cert');
        $certDigest = $etsi('CertDigest');
        $digestMethod = $ds('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($texto($ds('DigestValue'), $this->digest(base64_decode($derBase64))));
        $cert->appendChild($certDigest);
        $issuerSerial = $etsi('IssuerSerial');
        $issuerSerial->appendChild($texto($ds('X509IssuerName'), $x509['emisor']));
        $issuerSerial->appendChild($texto($ds('X509SerialNumber'), $x509['serial']));
        $cert->appendChild($issuerSerial);
        $signingCertificate->appendChild($cert);
        $firmaProps->appendChild($signingCertificate);
        $signedProperties->appendChild($firmaProps);

        $objetoProps = $etsi('SignedDataObjectProperties');
        $formato = $etsi('DataObjectFormat');
        $formato->setAttribute('ObjectReference', '#'.$ids['referencia']);
        $formato->appendChild($texto($etsi('Description'), 'contenido comprobante'));
        $formato->appendChild($texto($etsi('MimeType'), 'text/xml'));
        $objetoProps->appendChild($formato);
        $signedProperties->appendChild($objetoProps);

        $qualifying->appendChild($signedProperties);
        $object->appendChild($qualifying);
        $firma->appendChild($object);

        return $firma;
    }

    /**
     * @param  array<string, string>  $ids
     */
    private function asignarDigests(DOMDocument $documento, DOMElement $firma, string $digestComprobante, array $ids): void
    {
        $digests = [
            '#'.$ids['signedProperties'] => $this->digest($this->nodoPorId($firma, $ids['signedProperties'])->C14N(false, false)),
            '#'.$ids['certificate'] => $this->digest($this->nodoPorId($firma, $ids['certificate'])->C14N(false, false)),
            '#comprobante' => $digestComprobante,
        ];

        foreach ($firma->getElementsByTagNameNS(self::NS_DS, 'Reference') as $referencia) {
            $valor = $referencia->getElementsByTagNameNS(self::NS_DS, 'DigestValue')->item(0);
            $valor?->appendChild($documento->createTextNode($digests[$referencia->getAttribute('URI')]));
        }
    }

    private function firmarSignedInfo(DOMDocument $documento, DOMElement $firma, CertificadoAbierto $abierto): void
    {
        $signedInfo = $firma->getElementsByTagNameNS(self::NS_DS, 'SignedInfo')->item(0);
        $signatureValue = $firma->getElementsByTagNameNS(self::NS_DS, 'SignatureValue')->item(0);

        if (! $signedInfo instanceof DOMElement || ! $signatureValue instanceof DOMElement) {
            throw EmisionFallida::enFirma('estructura de firma incompleta.');
        }

        $firmaBinaria = '';
        $exito = openssl_sign(
            $signedInfo->C14N(false, false),
            $firmaBinaria,
            $abierto->clavePrivadaPem,
            OPENSSL_ALGO_SHA1,
        );

        if (! $exito) {
            throw EmisionFallida::enFirma('no se pudo firmar con la clave privada del certificado.');
        }

        $signatureValue->appendChild(
            $documento->createTextNode($this->base64EnLineas(base64_encode(is_string($firmaBinaria) ? $firmaBinaria : ''))),
        );
    }

    private function conDigestSha1(DOMDocument $documento, DOMElement $referencia): void
    {
        $metodo = $documento->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $metodo->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $referencia->appendChild($metodo);
        $referencia->appendChild($documento->createElementNS(self::NS_DS, 'ds:DigestValue'));
    }

    private function nodoPorId(DOMElement $firma, string $id): DOMElement
    {
        foreach ($firma->getElementsByTagName('*') as $nodo) {
            if ($nodo->getAttribute('Id') === $id) {
                return $nodo;
            }
        }

        throw EmisionFallida::enFirma("no se encontró el nodo {$id} en la firma.");
    }

    private function digest(string $datos): string
    {
        return base64_encode(sha1($datos, true));
    }

    private function derBase64(string $certificadoPem): string
    {
        $base64 = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $certificadoPem);

        if ($base64 === null || $base64 === '') {
            throw EmisionFallida::enFirma('el certificado no tiene formato PEM válido.');
        }

        return $base64;
    }

    /**
     * @return array{modulo: string, exponente: string}
     */
    private function componentesRsa(string $certificadoPem): array
    {
        $publica = openssl_pkey_get_public($certificadoPem);
        $detalles = $publica === false ? false : openssl_pkey_get_details($publica);
        $rsa = is_array($detalles) && is_array($detalles['rsa'] ?? null) ? $detalles['rsa'] : [];
        $modulo = $rsa['n'] ?? null;
        $exponente = $rsa['e'] ?? null;

        if (! is_string($modulo) || ! is_string($exponente)) {
            throw EmisionFallida::enFirma('el certificado no contiene una clave RSA.');
        }

        return [
            'modulo' => base64_encode($modulo),
            'exponente' => base64_encode($exponente),
        ];
    }

    /**
     * @return array{emisor: string, serial: string}
     */
    private function parseX509(string $certificadoPem): array
    {
        $x509 = openssl_x509_parse($certificadoPem);
        $issuer = is_array($x509) ? ($x509['issuer'] ?? null) : null;

        if (! is_array($issuer)) {
            throw EmisionFallida::enFirma('no se pudo leer el certificado X.509.');
        }

        // el jar (MITyC) serializa el emisor en orden RFC 2253 (invertido)
        $emisor = collect(array_reverse($issuer, true))
            ->map(fn (mixed $valor, int|string $clave): string => $clave.'='.$this->valorRdn($valor))
            ->implode(',');

        $serial = is_string($x509['serialNumber'] ?? null) ? $x509['serialNumber'] : '';

        return ['emisor' => $emisor, 'serial' => $serial];
    }

    private function valorRdn(mixed $valor): string
    {
        if (is_array($valor)) {
            return implode('+', array_map($this->valorRdn(...), $valor));
        }

        return is_scalar($valor) ? (string) $valor : '';
    }

    /**
     * Base64 en líneas de 76 caracteres con salto inicial, como el jar.
     */
    private function base64EnLineas(string $base64): string
    {
        return "\n".rtrim(chunk_split($base64, 76, "\n"))."\n";
    }
}
