<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarWebhookWhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /** Verificação feita pela Meta ao cadastrar a URL do webhook. */
    public function verificar(Request $request): Response
    {
        // O PHP troca "." por "_" nos parâmetros: hub.mode chega como hub_mode.
        if ($request->query('hub_mode') === 'subscribe'
            && hash_equals((string) config('whatsapp.verify_token'), (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receber(Request $request): JsonResponse
    {
        ProcessarWebhookWhatsApp::dispatch($request->json()->all());

        return response()->json(['ok' => true]);
    }
}
