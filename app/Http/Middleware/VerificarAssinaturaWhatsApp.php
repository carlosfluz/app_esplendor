<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que o POST do webhook veio da Meta:
 * X-Hub-Signature-256 = "sha256=" + HMAC-SHA256(corpo bruto, app secret).
 */
class VerificarAssinaturaWhatsApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $segredo = config('whatsapp.app_secret');
        $assinatura = (string) $request->header('X-Hub-Signature-256');

        if (! $segredo || ! str_starts_with($assinatura, 'sha256=')) {
            abort(401);
        }

        $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), $segredo);

        if (! hash_equals($esperada, $assinatura)) {
            abort(401);
        }

        return $next($request);
    }
}
