<?php

namespace App\Sri\Enums;

/**
 * Eventos que el servicio notifica por webhook (§11). Los de comprobante
 * se disparan al alcanzar un estado final; certificado.por_vencer lo
 * publica un comando programado en umbrales de vencimiento.
 */
enum EventoWebhook: string
{
    case ComprobanteAutorizado = 'comprobante.autorizado';
    case ComprobanteDevuelto = 'comprobante.devuelto';
    case ComprobanteNoAutorizado = 'comprobante.no_autorizado';
    case ComprobanteFallido = 'comprobante.fallido';
    case CertificadoPorVencer = 'certificado.por_vencer';

    /**
     * Evento que corresponde a un estado final de emisión (null para los
     * estados no finales o intermedios, que no se notifican).
     */
    public static function porEstado(EstadoComprobante $estado): ?self
    {
        return match ($estado) {
            EstadoComprobante::Autorizado => self::ComprobanteAutorizado,
            EstadoComprobante::Devuelto => self::ComprobanteDevuelto,
            EstadoComprobante::NoAutorizado => self::ComprobanteNoAutorizado,
            EstadoComprobante::Fallido => self::ComprobanteFallido,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
