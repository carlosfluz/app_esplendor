<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo da WhatsApp Cloud API: mensagens, mídia e Flows.
 */
class WhatsAppClient
{
    private function http(): PendingRequest
    {
        return Http::withToken(config('whatsapp.token'))
            ->baseUrl('https://graph.facebook.com/'.config('whatsapp.graph_version'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    // ------------------------------------------------------------ mensagens

    public function enviarTexto(string $para, string $texto): ?string
    {
        return $this->enviar($para, [
            'type' => 'text',
            'text' => ['body' => $texto, 'preview_url' => false],
        ]);
    }

    /**
     * Até 3 botões de resposta. Títulos com no máximo 20 caracteres.
     *
     * @param  array<string, string>  $botoes  id => título
     */
    public function enviarBotoes(string $para, string $corpo, array $botoes): ?string
    {
        $buttons = [];
        foreach (array_slice($botoes, 0, 3, true) as $id => $titulo) {
            $buttons[] = ['type' => 'reply', 'reply' => ['id' => (string) $id, 'title' => mb_substr($titulo, 0, 20)]];
        }

        return $this->enviar($para, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $corpo],
                'action' => ['buttons' => $buttons],
            ],
        ]);
    }

    /** Mensagem que abre o Flow já na tela inicial, com os dados do veículo preenchidos. */
    public function enviarFlow(
        string $para,
        string $flowId,
        string $flowToken,
        string $corpo,
        string $textoBotao,
        string $telaInicial,
        array $dadosTela,
        ?string $cabecalho = null,
    ): ?string {
        $parametros = [
            'flow_message_version' => '3',
            'flow_token' => $flowToken,
            'flow_id' => $flowId,
            'flow_cta' => mb_substr($textoBotao, 0, 20),
            'flow_action' => 'navigate',
            'flow_action_payload' => ['screen' => $telaInicial, 'data' => $dadosTela],
        ];
        if (config('whatsapp.flows_modo_rascunho')) {
            $parametros['mode'] = 'draft';
        }

        $interactive = [
            'type' => 'flow',
            'body' => ['text' => $corpo],
            'action' => ['name' => 'flow', 'parameters' => $parametros],
        ];
        if ($cabecalho) {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr($cabecalho, 0, 60)];
        }

        return $this->enviar($para, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    private function enviar(string $para, array $mensagem): ?string
    {
        $resposta = $this->http()->post(config('whatsapp.phone_number_id').'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $para,
            ...$mensagem,
        ]);

        $this->garantirSucesso($resposta, 'enviar mensagem');

        return $resposta->json('messages.0.id');
    }

    // ---------------------------------------------------------------- mídia

    /** @return array{conteudo: string, mime_type: ?string, sha256: ?string, tamanho: ?int} */
    public function baixarMidia(string $mediaId): array
    {
        $info = $this->http()->get($mediaId);
        $this->garantirSucesso($info, 'consultar mídia');

        // A URL expira em poucos minutos e exige o mesmo token.
        $arquivo = Http::withToken(config('whatsapp.token'))->timeout(60)->get($info->json('url'));
        $this->garantirSucesso($arquivo, 'baixar mídia');

        return [
            'conteudo' => $arquivo->body(),
            'mime_type' => $info->json('mime_type'),
            'sha256' => $info->json('sha256'),
            'tamanho' => $info->json('file_size'),
        ];
    }

    // ---------------------------------------------------------------- flows

    public function criarFlow(string $nome): string
    {
        $resposta = $this->http()->post(config('whatsapp.waba_id').'/flows', [
            'name' => $nome,
            'categories' => ['OTHER'],
        ]);
        $this->garantirSucesso($resposta, 'criar flow');

        return $resposta->json('id');
    }

    /** Envia o JSON do Flow. Retorna os erros de validação da Meta (vazio = válido). */
    public function atualizarJsonFlow(string $flowId, string $json): array
    {
        $resposta = Http::withToken(config('whatsapp.token'))
            ->baseUrl('https://graph.facebook.com/'.config('whatsapp.graph_version'))
            ->attach('file', $json, 'flow.json', ['Content-Type' => 'application/json'])
            ->post($flowId.'/assets', [
                'name' => 'flow.json',
                'asset_type' => 'FLOW_JSON',
            ]);
        $this->garantirSucesso($resposta, 'atualizar JSON do flow');

        return $resposta->json('validation_errors', []);
    }

    public function publicarFlow(string $flowId): void
    {
        $this->garantirSucesso($this->http()->post($flowId.'/publish'), 'publicar flow');
    }

    public function statusFlow(string $flowId): array
    {
        $resposta = $this->http()->get($flowId, ['fields' => 'id,name,status,validation_errors,preview.invalidate(false)']);
        $this->garantirSucesso($resposta, 'consultar flow');

        return $resposta->json();
    }

    private function garantirSucesso(Response $resposta, string $acao): void
    {
        if ($resposta->failed()) {
            $erro = $resposta->json('error.error_user_msg')
                ?? $resposta->json('error.message')
                ?? $resposta->body();

            throw new RuntimeException("WhatsApp: falha ao {$acao} (HTTP {$resposta->status()}): {$erro}");
        }
    }
}
