<?php

namespace App\Services\Checklist;

/**
 * Converte o response_json do Flow (webhook nfm_reply) em dados prontos para gravar.
 * Usa a mesma definição de modelo que gerou o Flow, então só aceita itens e opções que existem.
 *
 * Retorno:
 * [
 *   'km' => ?int, 'horimetro' => ?float, 'combustivel_oitavos' => ?int,
 *   'observacoes' => ?string, 'observacoes_avarias' => ?string,
 *   'respostas' => [['item_id' => int, 'valor' => string, 'nao_conforme' => bool], ...],
 *   'avarias' => [['area' => string, 'tipo' => string], ...],
 *   'fotos' => [['categoria' => 'veiculo'|'avaria', 'id' => string, 'file_name' => ?, 'mime_type' => ?, 'sha256' => ?], ...],
 *   'alertas' => [string, ...],   // inconsistências que não impedem gravar
 * ]
 */
class FlowResponseParser
{
    public function interpretar(array $def, array $resposta): array
    {
        $alertas = [];

        $saida = [
            'km' => $this->inteiro($resposta['km'] ?? null),
            'horimetro' => $this->decimal($resposta['horimetro'] ?? null),
            'combustivel_oitavos' => $this->inteiro($resposta['combustivel'] ?? null),
            'observacoes' => $this->texto($resposta['observacoes'] ?? null),
            'observacoes_avarias' => $this->texto($resposta['obs_avarias'] ?? null),
            'respostas' => [],
            'avarias' => [],
            'fotos' => [],
            'alertas' => [],
        ];

        if ($def['pede_km'] && $saida['km'] === null) {
            $alertas[] = 'Quilometragem não informada.';
        }
        if ($saida['combustivel_oitavos'] !== null && ($saida['combustivel_oitavos'] < 0 || $saida['combustivel_oitavos'] > 8)) {
            $alertas[] = 'Nível de combustível fora da faixa 0..8; descartado.';
            $saida['combustivel_oitavos'] = null;
        }

        $itens = [];
        foreach ($def['secoes'] as $secao) {
            foreach ($secao['itens'] as $item) {
                $itens[$item['id']] = $item;
            }
        }

        $saida['respostas'] = $def['tipo_avaliacao'] === 'status'
            ? $this->respostasStatus($itens, $resposta, $alertas)
            : $this->respostasConformidade($itens, $resposta, $alertas);

        foreach (FlowJsonBuilder::AREAS_AVARIA as $area => $_) {
            foreach ($this->lista($resposta['av_'.$area] ?? []) as $tipo) {
                if (isset(FlowJsonBuilder::TIPOS_AVARIA[$tipo])) {
                    $saida['avarias'][] = ['area' => $area, 'tipo' => $tipo];
                }
            }
        }

        foreach (['fotos_veiculo' => 'veiculo', 'fotos_avaria' => 'avaria'] as $campo => $categoria) {
            foreach ($this->lista($resposta[$campo] ?? []) as $foto) {
                if (is_array($foto) && ! empty($foto['id'])) {
                    $saida['fotos'][] = [
                        'categoria' => $categoria,
                        'id' => (string) $foto['id'],
                        'file_name' => $foto['file_name'] ?? null,
                        'mime_type' => $foto['mime_type'] ?? null,
                        'sha256' => $foto['sha256'] ?? null,
                    ];
                }
            }
        }

        $qtdFotosVeiculo = count(array_filter($saida['fotos'], fn ($f) => $f['categoria'] === 'veiculo'));
        if ($qtdFotosVeiculo < ($def['fotos_min'] ?? 0)) {
            $alertas[] = "Recebidas {$qtdFotosVeiculo} fotos do veículo; o mínimo é {$def['fotos_min']}.";
        }

        $saida['alertas'] = $alertas;

        return $saida;
    }

    private function respostasStatus(array $itens, array $resposta, array &$alertas): array
    {
        $saida = [];
        foreach ($itens as $id => $item) {
            $valor = $resposta['s_'.$id] ?? null;
            $opcao = null;
            foreach ($item['opcoes'] as $op) {
                if ($op['id'] === $valor) {
                    $opcao = $op;
                    break;
                }
            }
            if ($opcao === null) {
                $alertas[] = "Item \"{$item['descricao']}\" sem resposta válida.";

                continue;
            }
            $saida[] = ['item_id' => $id, 'valor' => $opcao['id'], 'nao_conforme' => (bool) $opcao['nao_conforme']];
        }

        return $saida;
    }

    private function respostasConformidade(array $itens, array $resposta, array &$alertas): array
    {
        $naoConformes = [];
        $naoAplicaveis = [];
        foreach ($resposta as $chave => $valor) {
            if (str_starts_with($chave, 'nc_')) {
                $naoConformes = [...$naoConformes, ...$this->lista($valor)];
            } elseif (str_starts_with($chave, 'na_')) {
                $naoAplicaveis = [...$naoAplicaveis, ...$this->lista($valor)];
            }
        }
        $naoConformes = array_flip(array_map('intval', $naoConformes));
        $naoAplicaveis = array_flip(array_map('intval', $naoAplicaveis));

        $saida = [];
        foreach ($itens as $id => $item) {
            $nc = isset($naoConformes[$id]);
            $na = isset($naoAplicaveis[$id]);
            if ($nc && $na) {
                // Marcado nos dois: vale o mais conservador.
                $alertas[] = "Item \"{$item['descricao']}\" marcado como não conforme e não aplicável; registrado como não conforme.";
            }
            $valor = $nc ? 'nao_conforme' : ($na ? 'nao_aplicavel' : 'conforme');
            $saida[] = ['item_id' => $id, 'valor' => $valor, 'nao_conforme' => $nc];
        }

        return $saida;
    }

    private function lista(mixed $valor): array
    {
        if (is_string($valor)) {
            $decodificado = json_decode($valor, true);
            $valor = is_array($decodificado) ? $decodificado : ($valor === '' ? [] : [$valor]);
        }

        return is_array($valor) ? array_values($valor) : [];
    }

    private function inteiro(mixed $v): ?int
    {
        $v = is_string($v) ? preg_replace('/\D/', '', $v) : $v;

        return ($v === null || $v === '') ? null : (int) $v;
    }

    private function decimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $v);
    }

    private function texto(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;

        return $v === '' ? null : $v;
    }
}
