<?php

namespace App\Jobs;

use App\Models\ChecklistFoto;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Baixa a foto da Meta e guarda no nosso storage.
 * A mídia fica disponível na Meta por tempo limitado: não deixe a fila parada.
 */
class BaixarFotoChecklist implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public int $fotoId) {}

    public function handle(WhatsAppClient $whatsapp): void
    {
        $foto = ChecklistFoto::with('checklist')->find($this->fotoId);
        if (! $foto || $foto->baixado_em) {
            return;
        }

        $midia = $whatsapp->baixarMidia($foto->wa_media_id);

        $sha = hash('sha256', $midia['conteudo']);
        $esperado = $foto->sha256 ?: $midia['sha256'];
        if ($esperado && ! hash_equals(strtolower($esperado), $sha)) {
            // Não descarta: grava e registra, para alguém olhar.
            Log::warning('Foto do checklist com hash diferente do informado', ['foto' => $foto->id]);
        }

        $extensao = match ($midia['mime_type'] ?? $foto->mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $disco = config('whatsapp.disco_fotos');
        $caminho = sprintf('checklists/%s/%s/%s-%d.%s',
            $foto->checklist->concluido_em?->format('Y/m') ?? now()->format('Y/m'),
            $foto->checklist->uuid,
            $foto->categoria,
            $foto->id,
            $extensao,
        );

        Storage::disk($disco)->put($caminho, $midia['conteudo']);

        $foto->update([
            'disco' => $disco,
            'caminho' => $caminho,
            'mime_type' => $midia['mime_type'] ?? $foto->mime_type,
            'sha256' => $sha,
            'tamanho_bytes' => strlen($midia['conteudo']),
            'baixado_em' => now(),
        ]);
    }
}
