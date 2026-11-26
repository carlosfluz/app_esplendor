<?php

/*
| Cole estas linhas no seu routes/api.php
| (se o projeto ainda não tem rotas de API: php artisan install:api).
| URL do webhook no painel da Meta: https://SEU_DOMINIO/api/whatsapp/webhook
*/

use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\VerificarAssinaturaWhatsApp;
use Illuminate\Support\Facades\Route;

Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verificar']);

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receber'])
    ->middleware(VerificarAssinaturaWhatsApp::class);
