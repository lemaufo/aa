<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('aa:sincronizar-cancelaciones')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('aa:importar-traspasos')->everyMinute()->withoutOverlapping()
    ->when(fn () => config('aa.origen') === 'firebird');
Schedule::command('aa:purgar-evidencias')->dailyAt('03:00');