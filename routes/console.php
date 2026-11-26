<?php

/*
| Cole no seu routes/console.php.
| Em produção, o cron do servidor precisa rodar: * * * * * php artisan schedule:run
*/

use App\Services\Checklist\ChecklistWhatsAppService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => ChecklistWhatsAppService::expirarPendentes())
    ->everyFifteenMinutes()
    ->name('checklist:expirar-pendentes')
    ->withoutOverlapping();
