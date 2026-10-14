<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Centre de notifications — extension météo (voir App\Console\Commands\CheckWeatherAlerts).
// Nécessite le cron standard Laravel en production : `* * * * * php artisan schedule:run`.
Schedule::command('weather:check-alerts')->dailyAt('06:00');
