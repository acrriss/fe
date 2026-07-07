<?php

namespace App\Sri\Enums;

/**
 * Tipo de comprobante electrónico según la ficha técnica del SRI (tabla 3).
 * El valor del case es el `codDoc` que viaja en la clave de acceso y el XML.
 *
 * El legado aceptaba el codDoc del payload (y los ejemplos lo traían mal);
 * en el nuevo dominio el codDoc SIEMPRE se deriva de este enum.
 */
enum TipoComprobante: string
{
    case Factura = '01';
    case LiquidacionCompra = '03';
    case NotaCredito = '04';
    case NotaDebito = '05';
    case GuiaRemision = '06';
    case ComprobanteRetencion = '07';

    /**
     * Nombre del elemento raíz del XML (y de la clave del payload legado).
     */
    public function rootElement(): string
    {
        return match ($this) {
            self::Factura => 'factura',
            self::LiquidacionCompra => 'liquidacionCompra',
            self::NotaCredito => 'notaCredito',
            self::NotaDebito => 'notaDebito',
            self::GuiaRemision => 'guiaRemision',
            self::ComprobanteRetencion => 'comprobanteRetencion',
        };
    }

    public static function fromRootElement(string $rootElement): self
    {
        foreach (self::cases() as $tipo) {
            if ($tipo->rootElement() === $rootElement) {
                return $tipo;
            }
        }

        throw new \ValueError("Tipo de comprobante desconocido: {$rootElement}");
    }

    /**
     * Versión del esquema XML que espera el SRI (comportamiento del legado:
     * la retención usa 1.0.0 y el resto 1.1.0).
     */
    public function versionEsquema(): string
    {
        return $this === self::ComprobanteRetencion ? '1.0.0' : '1.1.0';
    }

    /**
     * Nombre del documento tal como se muestra en el RIDE.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Factura => 'FACTURA',
            self::LiquidacionCompra => 'LIQUIDACIÓN DE COMPRA',
            self::NotaCredito => 'NOTA DE CRÉDITO',
            self::NotaDebito => 'NOTA DE DÉBITO',
            self::GuiaRemision => 'GUÍA DE REMISIÓN',
            self::ComprobanteRetencion => 'COMPROBANTE DE RETENCIÓN',
        };
    }
}
