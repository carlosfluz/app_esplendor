<?php

namespace App\Services\Checklist;

/**
 * Gera o Flow JSON (WhatsApp Flows) a partir da definição de um modelo de checklist.
 *
 * Flow estático (sem endpoint): os dados do veículo entram pelo flow_action_payload
 * na primeira tela e tudo volta num único webhook (nfm_reply) ao final.
 *
 * Telas:
 *   DADOS        → cabeçalho do veículo, km, horímetro, combustível
 *   ITENS_A..    → itens do checklist (dropdown por item, ou checkboxes de NC / NA)
 *   AVARIAS      → áreas x tipo (A P Q R T), observação e fotos das avarias
 *   FOTOS        → fotos obrigatórias do veículo (só câmera)
 *   ENVIO        → observações gerais e botão de enviar
 *
 * Classe sem dependência do Laravel, para poder ser testada isoladamente.
 */
class FlowJsonBuilder
{
    public const VERSAO_FLOW_JSON = '7.3';

    // Limites de texto da Meta (os que importam aqui). Cortamos antes para não falhar na validação.
    private const LIM_LABEL_INPUT = 20;   // TextInput, TextArea, Dropdown
    private const LIM_LABEL_GRUPO = 30;   // CheckboxGroup, RadioButtonsGroup
    private const LIM_TITULO_OPCAO = 30;
    private const LIM_DESCRICAO_OPCAO = 300;
    private const LIM_TITULO_TELA = 30;

    /** Itens por tela. Abaixo do limite técnico, pensando em quem está no pátio com o celular. */
    public const ITENS_POR_TELA_STATUS = 10;
    public const ITENS_POR_TELA_CONFORMIDADE = 10;

    public const AREAS_AVARIA = [
        'frente' => 'Frente',
        'traseira' => 'Traseira',
        'lateral_esquerda' => 'Lateral esquerda',
        'lateral_direita' => 'Lateral direita',
        'teto' => 'Teto',
        'interior' => 'Interior',
    ];

    public const TIPOS_AVARIA = [
        'A' => 'Amassado',
        'P' => 'Picotado',
        'Q' => 'Quebrado / Estragado',
        'R' => 'Riscado / Arranhado',
        'T' => 'Trincado',
    ];

    /** @var array<string, string> campo do payload final => referência global */
    private array $payloadFinal = [];

    public function construir(array $def): array
    {
        $this->payloadFinal = [];

        $telasItens = $def['tipo_avaliacao'] === 'status'
            ? $this->telasStatus($def)
            : $this->telasConformidade($def);

        $telas = [$this->telaDados($def), ...$telasItens];
        if ($def['pede_avarias']) {
            $telas[] = $this->telaAvarias($def);
        }
        if ($def['fotos_max'] > 0) {
            $telas[] = $this->telaFotos($def);
        }
        $telas[] = $this->telaEnvio();

        // Liga cada tela à próxima.
        $ultima = count($telas) - 1;
        foreach ($telas as $i => &$tela) {
            if ($i === $ultima) {
                continue;
            }
            $tela['layout']['children'][] = [
                'type' => 'Footer',
                'label' => 'Continuar',
                'on-click-action' => [
                    'name' => 'navigate',
                    'next' => ['type' => 'screen', 'name' => $telas[$i + 1]['id']],
                    'payload' => new \stdClass(),
                ],
            ];
        }
        unset($tela);

        return [
            'version' => self::VERSAO_FLOW_JSON,
            'screens' => $telas,
        ];
    }

    public function construirJson(array $def): string
    {
        return json_encode($this->construir($def), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    // ---------------------------------------------------------------- telas

    private function telaDados(array $def): array
    {
        $filhos = [
            ['type' => 'TextSubheading', 'text' => '${data.cabecalho}'],
            ['type' => 'TextBody', 'text' => '${data.detalhe}'],
        ];

        if ($def['pede_km']) {
            $filhos[] = [
                'type' => 'TextInput',
                'name' => 'km',
                'label' => 'Quilometragem',
                'input-type' => 'number',
                'required' => true,
                'helper-text' => 'Valor do hodômetro, só números',
            ];
            $this->ref('km', 'DADOS', 'km');
        }

        if ($def['pede_horimetro']) {
            $filhos[] = [
                'type' => 'TextInput',
                'name' => 'horimetro',
                'label' => 'Horímetro',
                'input-type' => 'number',
                'required' => true,
            ];
            $this->ref('horimetro', 'DADOS', 'horimetro');
        }

        if ($def['pede_combustivel']) {
            $opcoes = [];
            for ($o = 0; $o <= 8; $o++) {
                $rotulo = match ($o) {
                    0 => 'Reserva (0/8)',
                    4 => 'Meio tanque (4/8)',
                    8 => 'Cheio (8/8)',
                    default => "{$o}/8",
                };
                $opcoes[] = ['id' => (string) $o, 'title' => $rotulo];
            }
            $filhos[] = [
                'type' => 'Dropdown',
                'name' => 'combustivel',
                'label' => 'Combustível',
                'required' => true,
                'data-source' => $opcoes,
            ];
            $this->ref('combustivel', 'DADOS', 'combustivel');
        }

        return [
            'id' => 'DADOS',
            'title' => 'Dados do veículo',
            'data' => [
                'cabecalho' => ['type' => 'string', '__example__' => 'ABC1D23 · Micro-ônibus'],
                'detalhe' => ['type' => 'string', '__example__' => 'Entrega · Contrato 123 · Cliente Exemplo'],
            ],
            'layout' => ['type' => 'SingleColumnLayout', 'children' => $filhos],
        ];
    }

    /** Um dropdown por item, com as opções próprias do item. */
    private function telasStatus(array $def): array
    {
        $telas = [];
        foreach ($this->blocos($def, self::ITENS_POR_TELA_STATUS) as $n => [$titulo, $itens]) {
            $id = $this->idTelaItens($n);
            $filhos = [];
            foreach ($itens as $item) {
                $campo = 's_'.$item['id'];
                $filhos[] = [
                    'type' => 'Dropdown',
                    'name' => $campo,
                    'label' => $this->cortar($item['rotulo_curto'], self::LIM_LABEL_INPUT),
                    'required' => true,
                    'data-source' => array_map(fn ($op) => [
                        'id' => $op['id'],
                        'title' => $this->cortar($op['titulo'], self::LIM_TITULO_OPCAO),
                    ], $item['opcoes']),
                ];
                $this->ref($campo, $id, $campo);
            }
            $telas[] = $this->tela($id, $titulo, $filhos);
        }

        return $telas;
    }

    /**
     * Em vez de 3 botões por item (35 itens = 105 toques), o vistoriador marca só as exceções:
     * o que estiver NÃO CONFORME e o que NÃO SE APLICA. O resto é registrado como conforme.
     */
    private function telasConformidade(array $def): array
    {
        $telas = [];
        foreach ($this->blocos($def, self::ITENS_POR_TELA_CONFORMIDADE) as $n => [$titulo, $itens]) {
            $id = $this->idTelaItens($n);
            $opcoes = array_map(fn ($item) => [
                'id' => (string) $item['id'],
                'title' => $this->cortar(trim(($item['codigo'] ? $item['codigo'].' ' : '').$item['rotulo_curto']), self::LIM_TITULO_OPCAO),
                'description' => $this->cortar($item['descricao'], self::LIM_DESCRICAO_OPCAO),
            ], $itens);

            $campoNc = 'nc_'.($n + 1);
            $campoNa = 'na_'.($n + 1);
            $filhos = [
                ['type' => 'TextBody', 'text' => 'Marque só os itens com problema. Os itens não marcados serão registrados como CONFORME.'],
                [
                    'type' => 'CheckboxGroup',
                    'name' => $campoNc,
                    'label' => 'Itens NÃO conformes',
                    'required' => false,
                    'data-source' => $opcoes,
                ],
                [
                    'type' => 'CheckboxGroup',
                    'name' => $campoNa,
                    'label' => 'Itens que NÃO se aplicam',
                    'required' => false,
                    'data-source' => array_map(fn ($o) => array_diff_key($o, ['description' => 1]), $opcoes),
                ],
            ];
            $this->ref($campoNc, $id, $campoNc);
            $this->ref($campoNa, $id, $campoNa);
            $telas[] = $this->tela($id, $titulo, $filhos);
        }

        return $telas;
    }

    private function telaAvarias(array $def): array
    {
        $tipos = array_map(
            fn ($cod, $nome) => ['id' => $cod, 'title' => "{$nome} ({$cod})"],
            array_keys(self::TIPOS_AVARIA),
            self::TIPOS_AVARIA,
        );

        $filhos = [
            ['type' => 'TextBody', 'text' => 'Marque as avarias encontradas em cada área. Se não houver nenhuma, apenas continue.'],
        ];
        foreach (self::AREAS_AVARIA as $area => $rotulo) {
            $campo = 'av_'.$area;
            $filhos[] = [
                'type' => 'CheckboxGroup',
                'name' => $campo,
                'label' => $rotulo,
                'required' => false,
                'data-source' => $tipos,
            ];
            $this->ref($campo, 'AVARIAS', $campo);
        }

        $filhos[] = [
            'type' => 'TextArea',
            'name' => 'obs_avarias',
            'label' => 'Detalhe das avarias',
            'required' => false,
            'max-length' => 600,
        ];
        $this->ref('obs_avarias', 'AVARIAS', 'obs_avarias');

        if ($def['fotos_avaria_max'] > 0) {
            $filhos[] = [
                'type' => 'PhotoPicker',
                'name' => 'fotos_avaria',
                'label' => 'Fotos das avarias',
                'description' => 'Tire de perto, mostrando o dano.',
                'photo-source' => 'camera',
                'min-uploaded-photos' => 0,
                'max-uploaded-photos' => (int) $def['fotos_avaria_max'],
                'max-file-size-kb' => 10240,
            ];
            $this->ref('fotos_avaria', 'AVARIAS', 'fotos_avaria');
        }

        return $this->tela('AVARIAS', 'Avarias', $filhos);
    }

    private function telaFotos(array $def): array
    {
        $filhos = [
            ['type' => 'TextBody', 'text' => 'Fotografe o veículo agora: frente, traseira, lateral esquerda, lateral direita e o painel mostrando o km.'],
            [
                'type' => 'PhotoPicker',
                'name' => 'fotos_veiculo',
                'label' => 'Fotos do veículo',
                'photo-source' => 'camera', // só câmera: impede foto antiga da galeria
                'min-uploaded-photos' => (int) $def['fotos_min'],
                'max-uploaded-photos' => (int) $def['fotos_max'],
                'max-file-size-kb' => 10240,
            ],
        ];
        $this->ref('fotos_veiculo', 'FOTOS', 'fotos_veiculo');

        return $this->tela('FOTOS', 'Fotos do veículo', $filhos);
    }

    private function telaEnvio(): array
    {
        $this->ref('observacoes', 'ENVIO', 'observacoes');

        return [
            'id' => 'ENVIO',
            'title' => 'Finalizar',
            'terminal' => true,
            'success' => true,
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    ['type' => 'TextBody', 'text' => 'Confira se está tudo certo. Depois de enviar, o checklist não pode ser alterado pelo WhatsApp.'],
                    [
                        'type' => 'TextArea',
                        'name' => 'observacoes',
                        'label' => 'Observações',
                        'required' => false,
                        'max-length' => 1000,
                    ],
                    [
                        'type' => 'Footer',
                        'label' => 'Enviar checklist',
                        'on-click-action' => [
                            'name' => 'complete',
                            'payload' => $this->payloadFinal,
                        ],
                    ],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------- helpers

    /**
     * Divide os itens em telas, por seção, em blocos de tamanho parecido
     * (21 itens viram 7+7+7, não 10+10+1).
     *
     * @return array<int, array{0: string, 1: array}>
     */
    private function blocos(array $def, int $max): array
    {
        $blocos = [];
        foreach ($def['secoes'] as $secao) {
            $itens = $secao['itens'];
            $partes = (int) ceil(count($itens) / $max);
            $tamanho = (int) ceil(count($itens) / $partes);
            $pedacos = array_chunk($itens, $tamanho);
            foreach ($pedacos as $i => $pedaco) {
                $titulo = $partes > 1 ? sprintf('%s (%d/%d)', $secao['nome'], $i + 1, $partes) : $secao['nome'];
                $blocos[] = [$this->cortar($titulo, self::LIM_TITULO_TELA), $pedaco];
            }
        }

        return $blocos;
    }

    /** ITENS_A, ITENS_B... (ids de tela só com letras e "_", por segurança). */
    private function idTelaItens(int $n): string
    {
        $sufixo = '';
        $n++;
        while ($n > 0) {
            $resto = ($n - 1) % 26;
            $sufixo = chr(65 + $resto).$sufixo;
            $n = intdiv($n - 1, 26);
        }

        return 'ITENS_'.$sufixo;
    }

    private function tela(string $id, string $titulo, array $filhos): array
    {
        return [
            'id' => $id,
            'title' => $this->cortar($titulo, self::LIM_TITULO_TELA),
            'layout' => ['type' => 'SingleColumnLayout', 'children' => $filhos],
        ];
    }

    private function ref(string $chave, string $tela, string $campo): void
    {
        // Na própria tela terminal usa-se ${form.x}; nas anteriores, referência global.
        $this->payloadFinal[$chave] = $tela === 'ENVIO'
            ? '${form.'.$campo.'}'
            : '${screen.'.$tela.'.form.'.$campo.'}';
    }

    private function cortar(string $texto, int $limite): string
    {
        return mb_strlen($texto) <= $limite ? $texto : rtrim(mb_substr($texto, 0, $limite - 1)).'…';
    }
}
