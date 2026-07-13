<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Aviso por webhook de certificados de firma próximos a vencer (§11).
// Diario: cada umbral (30/7/1 días) dispara una sola vez por certificado.
Schedule::command('webhooks:certificados-por-vencer')->dailyAt('08:00');
