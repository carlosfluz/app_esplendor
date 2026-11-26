<?php

namespace App\Jobs;

use App\Services\Checklist\ChecklistWhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * O controller só valida e enfileira; o trabalho acontece aqui.
 * Assim o webhook responde 200 rápido e a Meta não reenvia.
 */
class ProcessarWebhookWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public array $payload) {}

    public function handle(ChecklistWhatsAppService $servico): void
    {
        foreach ($this->payload['entry'] ?? [] as $entrada) {
            foreach ($entrada['changes'] ?? [] as $mudanca) {
                if (($mudanca['field'] ?? null) !== 'messages') {
                    continue;
                }
                // "statuses" (entregue, lido, falhou) são ignorados por enquanto.
                foreach ($mudanca['value']['messages'] ?? [] as $mensagem) {
                    try {
                        $servico->tratarMensagem($mensagem);
                    } catch (Throwable $e) {
                        Log::error('Falha ao tratar mensagem do WhatsApp', [
                            'mensagem' => $mensagem['id'] ?? null,
                            'erro' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                }
            }
        }
    }
}
