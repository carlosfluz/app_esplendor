<?php

namespace App\Services\Checklist;

use App\Enums\StatusChecklist;
use App\Enums\TipoChecklist;
use App\Jobs\BaixarFotoChecklist;
use App\Models\Checklist;
use App\Models\ChecklistAvaria;
use App\Models\Veiculo;
use App\Models\Vistoriador;
use App\Models\WhatsappMensagem;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Conversa do checklist no WhatsApp:
 *
 *   vistoriador: "ABC1D23"
 *   bot:         [Entrega] [Devolução]
 *   vistoriador: toca em "Entrega"
 *   bot:         mensagem com botão "Abrir checklist" (Flow)
 *   vistoriador: preenche e envia
 *   bot:         resumo com protocolo e itens não conformes
 *
 * Não guarda estado de conversa: o id do veículo vai no id do botão e o checklist vai no flow_token.
 */
class ChecklistWhatsAppService
{
    public function __construct(
        private readonly WhatsAppClient $whatsapp,
        private readonly FlowResponseParser $parser,
    ) {}

    public function tratarMensagem(array $mensagem): void
    {
        if (! WhatsappMensagem::registrarEntrada($mensagem)) {
            return; // webhook repetido
        }

        try {
            $this->processar($mensagem);
        } catch (\Throwable $e) {
            // Libera a mensagem para a próxima tentativa do job.
            WhatsappMensagem::where('wa_message_id', $mensagem['id'])->delete();
            throw $e;
        }
    }

    private function processar(array $mensagem): void
    {
        $de = $mensagem['from'];
        $vistoriador = Vistoriador::porWaId($de);

        if (! $vistoriador) {
            $this->whatsapp->enviarTexto($de, 'Este número não está autorizado a registrar checklists. Fale com a administração da Esplendor.');

            return;
        }

        match ($mensagem['type']) {
            'text' => $this->tratarTexto($vistoriador, trim($mensagem['text']['body'] ?? '')),
            'interactive' => $this->tratarInterativo($vistoriador, $mensagem),
            'image', 'video', 'document' => $this->whatsapp->enviarTexto($de, 'As fotos devem ser tiradas dentro do formulário do checklist. Envie a placa do veículo para começar.'),
            default => $this->enviarAjuda($vistoriador),
        };
    }

    // -------------------------------------------------------------- conversa

    private function tratarTexto(Vistoriador $vistoriador, string $texto): void
    {
        if (Veiculo::parecePlaca($texto)) {
            $this->perguntarTipo($vistoriador, Veiculo::normalizarPlaca($texto));

            return;
        }

        $this->enviarAjuda($vistoriador);
    }

    private function perguntarTipo(Vistoriador $vistoriador, string $placa): void
    {
        $veiculo = Veiculo::where('placa', $placa)->where('ativo', true)->first();

        if (! $veiculo) {
            $this->whatsapp->enviarTexto($vistoriador->whatsapp, "Não encontrei a placa {$placa}. Confira e envie de novo.");

            return;
        }
        if (! $veiculo->modeloChecklist?->whatsapp_flow_id) {
            $this->whatsapp->enviarTexto($vistoriador->whatsapp, "O veículo {$placa} ainda não tem formulário de checklist configurado. Avise a administração.");

            return;
        }

        $ultimo = $veiculo->checklists()->concluidos()->latest('concluido_em')->first();
        $sugestao = $ultimo?->tipo === TipoChecklist::Entrega ? "\nÚltimo registro: entrega em {$ultimo->concluido_em->format('d/m H:i')}." : '';

        $this->whatsapp->enviarBotoes(
            $vistoriador->whatsapp,
            "*{$veiculo->placa}* · {$veiculo->modelo}{$sugestao}\n\nQual checklist você vai fazer?",
            [
                "tipo:entrega:{$veiculo->id}" => 'Entrega',
                "tipo:devolucao:{$veiculo->id}" => 'Devolução',
            ],
        );
    }

    private function tratarInterativo(Vistoriador $vistoriador, array $mensagem): void
    {
        $interativo = $mensagem['interactive'];

        if ($interativo['type'] === 'button_reply'
            && preg_match('/^tipo:(entrega|devolucao):(\d+)$/', $interativo['button_reply']['id'], $m)) {
            $this->abrirChecklist($vistoriador, (int) $m[2], TipoChecklist::from($m[1]));

            return;
        }

        if ($interativo['type'] === 'nfm_reply') {
            $this->receberFormulario($vistoriador, $mensagem);

            return;
        }

        $this->enviarAjuda($vistoriador);
    }

    private function abrirChecklist(Vistoriador $vistoriador, int $veiculoId, TipoChecklist $tipo): void
    {
        $veiculo = Veiculo::with('modeloChecklist')->findOrFail($veiculoId);
        $modelo = $veiculo->modeloChecklist;
        $contrato = $veiculo->contratoAtivo()?->load('cliente');

        // Um checklist aberto por vistoriador/veículo: descarta tentativas anteriores não enviadas.
        Checklist::pendentes()
            ->where('vistoriador_id', $vistoriador->id)
            ->where('veiculo_id', $veiculo->id)
            ->update(['status' => StatusChecklist::Cancelado]);

        $checklist = Checklist::create([
            'checklist_modelo_id' => $modelo->id,
            'veiculo_id' => $veiculo->id,
            'empresa_id' => $veiculo->empresa_id,
            'contrato_id' => $contrato?->id,
            'vistoriador_id' => $vistoriador->id,
            'tipo' => $tipo,
            'status' => StatusChecklist::AguardandoFormulario,
            'iniciado_em' => now(),
        ]);

        $detalhe = collect([
            $tipo->rotulo(),
            $contrato?->numero ? "Contrato {$contrato->numero}" : null,
            $contrato?->cliente?->nome,
        ])->filter()->implode(' · ');

        $this->whatsapp->enviarFlow(
            para: $vistoriador->whatsapp,
            flowId: $modelo->whatsapp_flow_id,
            flowToken: $checklist->uuid,
            corpo: "Checklist de *{$tipo->rotulo()}* do veículo *{$veiculo->placa}*.\nToque no botão para preencher. Tenha o veículo à sua frente: as fotos são tiradas na hora.",
            textoBotao: 'Abrir checklist',
            telaInicial: 'DADOS',
            dadosTela: [
                'cabecalho' => trim("{$veiculo->placa} · {$veiculo->modelo}", ' ·'),
                'detalhe' => $detalhe,
            ],
            cabecalho: $modelo->nome,
        );
    }

    // ---------------------------------------------------------- formulário

    private function receberFormulario(Vistoriador $vistoriador, array $mensagem): void
    {
        $resposta = json_decode($mensagem['interactive']['nfm_reply']['response_json'] ?? '{}', true) ?: [];
        $token = $resposta['flow_token'] ?? null;

        $checklist = $token ? Checklist::with(['modelo', 'veiculo'])->where('uuid', $token)->first() : null;

        if (! $checklist || (int) $checklist->vistoriador_id !== (int) $vistoriador->id) {
            Log::warning('Checklist: flow_token desconhecido ou de outro vistoriador', ['token' => $token, 'de' => $vistoriador->whatsapp]);
            $this->whatsapp->enviarTexto($vistoriador->whatsapp, 'Não consegui identificar este checklist. Envie a placa para começar um novo.');

            return;
        }

        if ($checklist->status === StatusChecklist::Concluido) {
            return; // já gravado
        }
        if ($checklist->status === StatusChecklist::Cancelado) {
            $this->whatsapp->enviarTexto($vistoriador->whatsapp, "Este formulário do veículo {$checklist->veiculo->placa} foi substituído por um mais recente. Use o último que enviei.");

            return;
        }

        $dados = $this->parser->interpretar($checklist->modelo->paraDefinicao(), $resposta);

        DB::transaction(function () use ($checklist, $dados, $resposta, $mensagem) {
            $checklist->update([
                'status' => StatusChecklist::Concluido,
                'km' => $dados['km'],
                'horimetro' => $dados['horimetro'],
                'combustivel_oitavos' => $dados['combustivel_oitavos'],
                'observacoes' => $dados['observacoes'],
                'observacoes_avarias' => $dados['observacoes_avarias'],
                'total_nao_conformes' => collect($dados['respostas'])->where('nao_conforme', true)->count(),
                'concluido_em' => now(), // horário do servidor, não do celular
                'resposta_bruta' => $resposta,
            ]);

            $checklist->respostas()->createMany(array_map(fn ($r) => [
                'checklist_item_id' => $r['item_id'],
                'valor' => $r['valor'],
                'nao_conforme' => $r['nao_conforme'],
            ], $dados['respostas']));

            $checklist->avarias()->createMany($dados['avarias']);

            foreach ($dados['fotos'] as $foto) {
                $registro = $checklist->fotos()->create([
                    'categoria' => $foto['categoria'],
                    'wa_media_id' => $foto['id'],
                    'nome_original' => $foto['file_name'],
                    'mime_type' => $foto['mime_type'],
                    'sha256' => $foto['sha256'],
                ]);
                BaixarFotoChecklist::dispatch($registro->id)->afterCommit();
            }

            WhatsappMensagem::where('wa_message_id', $mensagem['id'])->update(['checklist_id' => $checklist->id]);
        });

        if ($dados['alertas']) {
            Log::notice('Checklist com alertas', ['checklist' => $checklist->id, 'alertas' => $dados['alertas']]);
        }

        $this->whatsapp->enviarTexto($vistoriador->whatsapp, $this->resumo($checklist->fresh(['respostas.item', 'avarias', 'veiculo']), $dados));
    }

    private function resumo(Checklist $c, array $dados): string
    {
        $linhas = [
            "✅ *Checklist registrado* — protocolo #{$c->id}",
            "{$c->veiculo->placa} · {$c->tipo->rotulo()} · {$c->concluido_em->format('d/m/Y H:i')}",
        ];
        if ($c->km !== null) {
            $linhas[] = 'KM: '.number_format($c->km, 0, ',', '.');
        }
        if ($c->horimetro !== null) {
            $linhas[] = "Horímetro: {$c->horimetro}";
        }
        if ($c->combustivel_oitavos !== null) {
            $linhas[] = "Combustível: {$c->combustivel_oitavos}/8";
        }

        $nc = $c->respostas->where('nao_conforme', true);
        if ($nc->isNotEmpty()) {
            $linhas[] = '';
            $linhas[] = "⚠️ *{$nc->count()} item(ns) com problema:*";
            foreach ($nc as $r) {
                $opcao = collect($r->item->opcoes ?? [])->firstWhere('id', $r->valor)['titulo'] ?? null;
                $linhas[] = '• '.trim(($r->item->codigo ? $r->item->codigo.' ' : '').$r->item->rotulo_curto).($opcao ? " — {$opcao}" : '');
            }
        } else {
            $linhas[] = 'Nenhum item com problema.';
        }

        if ($c->avarias->isNotEmpty()) {
            $linhas[] = '';
            $linhas[] = '*Avarias:*';
            foreach ($c->avarias->groupBy('area') as $area => $lista) {
                $tipos = $lista->map(fn ($a) => ChecklistAvaria::TIPOS[$a->tipo] ?? $a->tipo)->implode(', ');
                $linhas[] = '• '.(ChecklistAvaria::AREAS[$area] ?? $area).": {$tipos}";
            }
        }

        $linhas[] = '';
        $linhas[] = count($dados['fotos']).' foto(s) recebida(s).';

        return implode("\n", $linhas);
    }

    private function enviarAjuda(Vistoriador $vistoriador): void
    {
        $this->whatsapp->enviarTexto(
            $vistoriador->whatsapp,
            "Olá, {$vistoriador->nome}! Para fazer um checklist, envie a *placa* do veículo (ex.: ABC1D23).",
        );
    }

    /** Marca como expirados os checklists abertos e nunca enviados. Agende a cada 15 minutos. */
    public static function expirarPendentes(): int
    {
        return Checklist::pendentes()
            ->where('iniciado_em', '<', now()->subMinutes((int) config('whatsapp.checklist_expira_minutos')))
            ->update(['status' => StatusChecklist::Expirado]);
    }
}
