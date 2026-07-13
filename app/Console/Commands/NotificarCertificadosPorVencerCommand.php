<?php

namespace App\Console\Commands;

use App\Models\Contribuyente;
use App\Models\WebhookEndpoint;
use App\Sri\Enums\EventoWebhook;
use Illuminate\Console\Command;

/**
 * Publica el evento `certificado.por_vencer` (§11) para los contribuyentes
 * cuyo certificado vence exactamente en alguno de los umbrales
 * configurados (30/7/1 días por defecto). Programado a diario: cada
 * umbral dispara una sola vez, sin necesidad de registrar avisos previos.
 */
class NotificarCertificadosPorVencerCommand extends Command
{
    protected $signature = 'webhooks:certificados-por-vencer';

    protected $description = 'Publica certificado.por_vencer para los certificados que vencen en los umbrales configurados';

    public function handle(): int
    {
        $notificados = 0;

        foreach (config()->array('sri.webhooks.certificado_umbrales_dias', [30, 7, 1]) as $dias) {
            if (! is_int($dias)) {
                continue;
            }

            $porVencer = Contribuyente::query()
                ->whereDate('certificado_valido_hasta', today()->addDays($dias))
                ->get();

            foreach ($porVencer as $contribuyente) {
                WebhookEndpoint::publicar(EventoWebhook::CertificadoPorVencer, $contribuyente, [
                    'titular' => $contribuyente->certificado_titular,
                    'validoHasta' => $contribuyente->certificado_valido_hasta?->toIso8601String(),
                    'diasRestantes' => $dias,
                ]);

                $notificados++;
            }
        }

        $this->info("Certificados por vencer notificados: {$notificados}.");

        return self::SUCCESS;
    }
}
