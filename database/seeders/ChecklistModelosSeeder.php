<?php

namespace Database\Seeders;

use App\Enums\TipoAvaliacao;
use App\Models\ChecklistModelo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Transcrição dos formulários em papel para modelos de checklist.
 *
 *  - "acessorios": formulário de carro, caminhonete e ônibus/micro-ônibus (os três são iguais)
 *  - "caminhao_pipa": formulário RR Maraguas, itens internos e externos
 *  - "caminhao_munk": formulário do munck (o item 34 repetia o 1 e foi removido)
 *
 * Pode rodar de novo sem duplicar: o modelo é localizado pelo código e os itens são recriados.
 * Atenção: recriar itens muda os IDs. Depois que houver checklists reais, edite pelo painel em vez de re-semear.
 */
class ChecklistModelosSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->acessorios();
            $this->caminhaoPipa();
            $this->caminhaoMunk();
        });
    }

    private function acessorios(): void
    {
        $ok = ['id' => 'ok', 'titulo' => 'OK', 'nao_conforme' => false];
        $ausente = ['id' => 'ausente', 'titulo' => 'Ausente', 'nao_conforme' => true];
        $quebrado = ['id' => 'quebrado', 'titulo' => 'Quebrado / Estragado', 'nao_conforme' => true];
        $riscado = ['id' => 'riscado', 'titulo' => 'Riscado / Arranhado', 'nao_conforme' => true];
        $rasgado = ['id' => 'rasgado', 'titulo' => 'Rasgado', 'nao_conforme' => true];
        $pneu = [
            $ok,
            ['id' => 'meia_vida', 'titulo' => 'Meia vida', 'nao_conforme' => false],
            ['id' => 'careca', 'titulo' => 'Careca', 'nao_conforme' => true],
            ['id' => 'furado', 'titulo' => 'Furado', 'nao_conforme' => true],
        ];
        $radio = [$ok, $ausente, $quebrado];

        $itens = [
            ['ANTENA', 'Antena', [$ok, $ausente, $quebrado]],
            ['BANCOS', 'Bancos', [
                $ok,
                ['id' => 'furado', 'titulo' => 'Furado', 'nao_conforme' => true],
                ['id' => 'manchado', 'titulo' => 'Manchado', 'nao_conforme' => true],
                $rasgado,
                $riscado,
            ]],
            ['DOCUMENTOS DO VEÍCULO', 'Documentos', [$ok, $ausente]],
            ['MACACO / CHAVE DE RODA / TRIÂNGULO / EXTINTOR', 'Macaco/triâng/extint', [$ok, $ausente, $quebrado]],
            ['PNEU (ESTEPE)', 'Estepe', $pneu],
            ['PNEUS', 'Pneus', $pneu],
            ['RÁDIO AM/FM', 'Rádio AM/FM', $radio],
            ['RÁDIO CD PLAYER', 'Rádio CD', $radio],
            ['RÁDIO MP3 PLAYER', 'Rádio MP3', [
                $ok, $ausente, $quebrado, $riscado,
                ['id' => 'sem_frente', 'titulo' => 'Sem a frente', 'nao_conforme' => true],
                ['id' => 'trincado', 'titulo' => 'Trincado', 'nao_conforme' => true],
            ]],
            ['TAPETE', 'Tapete', [$ok, $ausente, $rasgado]],
        ];

        $modelo = $this->modelo('acessorios', 'Vistoria de acessórios (carro, caminhonete, ônibus)', TipoAvaliacao::Status, [
            'pede_combustivel' => true,
        ]);

        foreach ($itens as $ordem => [$descricao, $rotulo, $opcoes]) {
            $modelo->itens()->create([
                'descricao' => $descricao,
                'rotulo_curto' => $rotulo,
                'opcoes' => $opcoes,
                'ordem' => $ordem + 1,
            ]);
        }
    }

    private function caminhaoPipa(): void
    {
        $modelo = $this->modelo('caminhao_pipa', 'Caminhão pipa', TipoAvaliacao::Conformidade);

        $secoes = [
            'Itens internos' => [
                ['1.1', 'Crachá de identificação do motorista', 'Crachá do motorista'],
                ['1.2', 'Pontos de apoio para subir e descer', 'Pontos de apoio'],
                ['1.3', 'CNH do motorista', 'CNH do motorista'],
                ['1.4', 'Documentos do veículo e carga', 'Documentos veíc./carga'],
                ['1.5', 'Motorista usando/portando os EPIs', 'EPIs do motorista'],
                ['1.6', 'Computador de bordo Raízen', 'Computador bordo Raízen'],
                ['1.7', 'Módulo de telemetria', 'Módulo de telemetria'],
                ['1.8', 'Cinto de segurança retrátil de 3 pontos', 'Cinto 3 pontos'],
                ['1.9', 'Banco em bom estado', 'Banco'],
                ['1.10', 'Direção em bom estado', 'Direção'],
                ['1.11', 'Luzes do painel', 'Luzes do painel'],
                ['1.12', 'Tacógrafo', 'Tacógrafo'],
                ['1.13', 'Extintor de incêndio (prazo de validade)', 'Extintor (validade)'],
                ['1.14', 'Portas e janelas', 'Portas e janelas'],
                ['1.15', 'Limpador de para-brisa (funcionando e com água)', 'Limpador para-brisa'],
                ['1.16', 'Buzina', 'Buzina'],
                ['1.17', 'Freio de mão', 'Freio de mão'],
                ['1.18', 'Alarme de maneco', 'Alarme de maneco'],
                ['1.19', 'Alarme de caçamba', 'Alarme de caçamba'],
                ['1.20', 'Cabine limpa e organizada', 'Cabine limpa/organizada'],
                ['1.21', 'Ausência de objetos soltos no interior do veículo', 'Sem objetos soltos'],
            ],
            'Itens externos' => [
                ['1.22', 'Pneus (estado de conservação e fixação das rodas)', 'Pneus e fixação rodas'],
                ['1.23', 'Ausência de vazamentos', 'Sem vazamentos'],
                ['1.24', 'Sinalização (triângulo/cones)', 'Triângulo/cones'],
                ['1.25', 'Espelhos retrovisores', 'Retrovisores'],
                ['1.26', 'Lona da carga (condição de conservação/funcionamento)', 'Lona da carga'],
                ['1.27', 'Faixas refletivas', 'Faixas refletivas'],
                ['1.28', 'Luzes laterais', 'Luzes laterais'],
                ['1.29', 'Luzes de freios', 'Luzes de freio'],
                ['1.30', 'Farol alto / baixo', 'Farol alto/baixo'],
                ['1.31', 'Pisca alerta', 'Pisca alerta'],
                ['1.32', 'Luz e sinal sonoro de ré', 'Luz e alarme de ré'],
                ['1.33', 'Setas', 'Setas'],
                ['1.34', 'Macaco / chave de rodas / estepe', 'Macaco/chave/estepe'],
                ['1.35', 'Lanternas traseiras e luz de placa', 'Lanternas e luz placa'],
            ],
        ];

        $this->criarItensConformidade($modelo, $secoes);
    }

    private function caminhaoMunk(): void
    {
        $modelo = $this->modelo('caminhao_munk', 'Caminhão munck', TipoAvaliacao::Conformidade, [
            'pede_horimetro' => true,
        ]);

        $itens = [
            ['1', 'A manutenção preventiva está em dia e identificada no manual do caminhão?', 'Manutenção preventiva'],
            ['2', 'Ar condicionado', 'Ar condicionado'],
            ['3', 'Bancos e assentos', 'Bancos e assentos'],
            ['4', 'Bateria', 'Bateria'],
            ['5', 'Braço hidráulico, articulações da concha (buchas e pinos)', 'Braço hidráulico'],
            ['6', 'Buzina', 'Buzina'],
            ['7', 'Carga e validade do extintor de incêndio', 'Extintor (carga/valid.)'],
            ['8', 'Cinto de segurança', 'Cinto de segurança'],
            ['9', 'Eixos (folgas e trincas)', 'Eixos (folgas/trincas)'],
            ['10', 'Estado geral dos pneus (desgaste, calibração etc.)', 'Pneus'],
            ['11', 'Faixas refletivas', 'Faixas refletivas'],
            ['12', 'Faróis, setas e lanternas', 'Faróis/setas/lanternas'],
            ['13', 'Freio motor', 'Freio motor'],
            ['14', 'Funcionamento do munck (lança) e identificação do limite máximo de carga', 'Munck e limite de carga'],
            ['15', 'Funcionamento do pisca alerta, luz de ré, luz de freio e alarme de ré', 'Pisca/ré/freio/alarme'],
            ['16', 'Giroflex', 'Giroflex'],
            ['17', 'Iluminação e funcionamento de instrumentos do painel', 'Painel e instrumentos'],
            ['18', 'Indicador de restrição do filtro de ar', 'Indicador filtro de ar'],
            ['19', 'Limpadores de para-brisas', 'Limpadores para-brisa'],
            ['20', 'Dispositivos de içamento (cabos de aço, cintas, manilhas etc.)', 'Dispositivos de içamento'],
            ['21', 'Para-choque traseiro / dianteiro e chaparias', 'Para-choques/chaparia'],
            ['22', 'Partes móveis possuem proteções adequadas', 'Proteção partes móveis'],
            ['23', 'Patolamento, sistema hidráulico e calços', 'Patolamento/hidr./calços'],
            ['24', 'Retrovisores', 'Retrovisores'],
            ['25', 'Sistema de direção', 'Direção'],
            ['26', 'Sistema de embreagem', 'Embreagem'],
            ['27', 'Sistema de freio, estacionário e de serviço', 'Freios'],
            ['28', 'Suspensão (folgas)', 'Suspensão'],
            ['29', 'Tacógrafo', 'Tacógrafo'],
            ['30', 'Telescópio', 'Telescópio'],
            ['31', 'Trava de segurança do gancho do moitão e roldanas', 'Trava gancho/roldanas'],
            ['32', 'Triângulo / macaco / chave de rodas', 'Triângulo/macaco/chave'],
            ['33', 'Vazamentos (líquidos e ar)', 'Vazamentos'],
        ];

        $this->criarItensConformidade($modelo, ['Itens para inspecionar' => $itens]);
    }

    private function modelo(string $codigo, string $nome, TipoAvaliacao $tipo, array $extra = []): ChecklistModelo
    {
        $modelo = ChecklistModelo::updateOrCreate(
            ['codigo' => $codigo],
            array_merge(['nome' => $nome, 'tipo_avaliacao' => $tipo], $extra),
        );
        $modelo->itens()->delete();
        $modelo->secoes()->delete();

        return $modelo;
    }

    private function criarItensConformidade(ChecklistModelo $modelo, array $secoes): void
    {
        $ordemItem = 0;
        $ordemSecao = 0;
        foreach ($secoes as $nomeSecao => $itens) {
            $secao = $modelo->secoes()->create(['nome' => $nomeSecao, 'ordem' => ++$ordemSecao]);
            foreach ($itens as [$codigo, $descricao, $rotulo]) {
                $modelo->itens()->create([
                    'checklist_secao_id' => $secao->id,
                    'codigo' => $codigo,
                    'descricao' => $descricao,
                    'rotulo_curto' => $rotulo,
                    'ordem' => ++$ordemItem,
                ]);
            }
        }
    }
}
