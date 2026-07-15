<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programación de Backup Diario a la medianoche (00:00)
// Se usa la versión PHP para compatibilidad con Hostinger (sin exec)
Schedule::command('db:backup-php')->daily();

// Programación para procesar los pedidos programados (ahora por minuto para mayor precisión de datetime)
Schedule::command('pedidos:procesar-programados')->everyMinute();
Schedule::command('monitor:microservice')->everyFiveMinutes();
Schedule::command('monitor:sitrad')->everyFiveMinutes();
