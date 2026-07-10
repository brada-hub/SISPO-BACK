<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\LimpiarArchivosTemporal;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpiar archivos temporales de postulación huérfanos cada día a las 3 AM
// Elimina archivos en postulaciones/tmp con más de 12 horas de antigüedad
Schedule::command(LimpiarArchivosTemporal::class, ['--horas=12'])
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/limpieza-temporales.log'));

