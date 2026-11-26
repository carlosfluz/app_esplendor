<?php

namespace App\Console\Commands;

use App\Models\ChecklistModelo;
use App\Services\Checklist\FlowJsonBuilder;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Console\Command;

/**
 * Gera o Flow JSON de um modelo e envia para a Meta.
 *
 *   php artisan checklist:flow acessorios --arquivo=storage/app/flow.json   (só gera, para colar no Flow Builder)
 *   php artisan checklist:flow caminhao_pipa                                  (cria/atualiza rascunho na Meta)
 *   php artisan checklist:flow caminhao_pipa --publicar                       (publica; depois disso não pode mais editar)
 *
 * Flow publicado não aceita alteração: se o modelo mudar, o comando cria um Flow novo
 * e aponta o modelo para ele. Os checklists antigos continuam válidos.
 */
class PublicarFlowChecklist extends Command
{
    protected $signature = 'checklist:flow
        {modelo : código do modelo (acessorios, caminhao_pipa, caminhao_munk)}
        {--arquivo= : só grava o JSON neste caminho, sem falar com a Meta}
        {--publicar : publica o Flow depois de enviar}';

    protected $description = 'Gera e envia o WhatsApp Flow de um modelo de checklist';

    public function handle(FlowJsonBuilder $builder, WhatsAppClient $whatsapp): int
    {
        $modelo = ChecklistModelo::where('codigo', $this->argument('modelo'))->firstOrFail();
        $json = $builder->construirJson($modelo->paraDefinicao());
        $hash = hash('sha256', $json);

        if ($arquivo = $this->option('arquivo')) {
            file_put_contents($arquivo, $json);
            $this->info("Flow JSON gravado em {$arquivo}");

            return self::SUCCESS;
        }

        $publicado = $modelo->whatsapp_flow_status === 'PUBLISHED';

        if ($publicado && $modelo->whatsapp_flow_hash === $hash) {
            $this->info("Nada mudou. Flow {$modelo->whatsapp_flow_id} já publicado com este conteúdo.");

            return self::SUCCESS;
        }

        if (! $modelo->whatsapp_flow_id || $publicado) {
            $nome = 'checklist_'.$modelo->codigo.'_'.now()->format('Ymd_His');
            $flowId = $whatsapp->criarFlow($nome);
            $modelo->update(['whatsapp_flow_id' => $flowId, 'whatsapp_flow_status' => 'DRAFT', 'whatsapp_flow_hash' => null]);
            $this->info("Flow criado: {$nome} ({$flowId})");
        }

        $erros = $whatsapp->atualizarJsonFlow($modelo->whatsapp_flow_id, $json);
        if ($erros) {
            $this->error('A Meta recusou o JSON:');
            foreach ($erros as $erro) {
                $this->line(sprintf(
                    ' - %s: %s (linha %s)',
                    $erro['error'] ?? '?',
                    $erro['message'] ?? json_encode($erro),
                    $erro['line_start'] ?? '?',
                ));
            }

            return self::FAILURE;
        }
        $modelo->update(['whatsapp_flow_hash' => $hash]);
        $this->info('JSON enviado e validado.');

        if ($this->option('publicar')) {
            if (! $this->confirm('Depois de publicado o Flow não pode ser editado. Publicar?', true)) {
                return self::SUCCESS;
            }
            $whatsapp->publicarFlow($modelo->whatsapp_flow_id);
            $modelo->update(['whatsapp_flow_status' => 'PUBLISHED']);
            $this->info('Flow publicado. Lembre de WHATSAPP_FLOWS_RASCUNHO=false no .env.');
        } else {
            $preview = $whatsapp->statusFlow($modelo->whatsapp_flow_id)['preview']['preview_url'] ?? null;
            $this->line('Rascunho pronto para teste'.($preview ? ": {$preview}" : '.'));
        }

        return self::SUCCESS;
    }
}
