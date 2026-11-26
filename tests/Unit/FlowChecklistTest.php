<?php

namespace Tests\Unit;

use App\Services\Checklist\FlowJsonBuilder;
use App\Services\Checklist\FlowResponseParser;
use PHPUnit\Framework\TestCase;

/** Testes sem banco: rodam com `php artisan test --filter=FlowChecklistTest`. */
class FlowChecklistTest extends TestCase
{
    private function defStatus(): array
    {
        return [
            'codigo' => 'acessorios', 'nome' => 'Vistoria de acessórios', 'tipo_avaliacao' => 'status',
            'pede_km' => true, 'pede_horimetro' => false, 'pede_combustivel' => true, 'pede_avarias' => true,
            'fotos_min' => 4, 'fotos_max' => 6, 'fotos_avaria_max' => 4,
            'secoes' => [['nome' => 'Acessórios', 'itens' => [
                ['id' => 1, 'codigo' => null, 'descricao' => 'ANTENA', 'rotulo_curto' => 'Antena', 'opcoes' => [
                    ['id' => 'ok', 'titulo' => 'OK', 'nao_conforme' => false],
                    ['id' => 'ausente', 'titulo' => 'Ausente', 'nao_conforme' => true],
                ]],
                ['id' => 2, 'codigo' => null, 'descricao' => 'PNEUS', 'rotulo_curto' => 'Pneus', 'opcoes' => [
                    ['id' => 'ok', 'titulo' => 'OK', 'nao_conforme' => false],
                    ['id' => 'careca', 'titulo' => 'Careca', 'nao_conforme' => true],
                ]],
            ]]],
        ];
    }

    private function defConformidade(int $qtd = 21): array
    {
        $itens = [];
        for ($i = 1; $i <= $qtd; $i++) {
            $itens[] = ['id' => 100 + $i, 'codigo' => "1.{$i}", 'descricao' => "Item {$i}", 'rotulo_curto' => "Item {$i}", 'opcoes' => []];
        }

        return [
            'codigo' => 'caminhao_pipa', 'nome' => 'Caminhão pipa', 'tipo_avaliacao' => 'conformidade',
            'pede_km' => true, 'pede_horimetro' => true, 'pede_combustivel' => false, 'pede_avarias' => true,
            'fotos_min' => 4, 'fotos_max' => 6, 'fotos_avaria_max' => 4,
            'secoes' => [['nome' => 'Itens internos', 'itens' => $itens]],
        ];
    }

    public function test_divide_itens_em_telas_equilibradas(): void
    {
        $flow = (new FlowJsonBuilder)->construir($this->defConformidade(21));
        $ids = array_column($flow['screens'], 'id');

        $this->assertSame(['DADOS', 'ITENS_A', 'ITENS_B', 'ITENS_C', 'AVARIAS', 'FOTOS', 'ENVIO'], $ids);
        $this->assertCount(7, $flow['screens'][1]['layout']['children'][1]['data-source']);
    }

    public function test_payload_final_referencia_todos_os_campos(): void
    {
        $flow = (new FlowJsonBuilder)->construir($this->defStatus());
        $envio = end($flow['screens']);
        $payload = end($envio['layout']['children'])['on-click-action']['payload'];

        $this->assertTrue($envio['terminal']);
        $this->assertSame('${screen.ITENS_A.form.s_1}', $payload['s_1']);
        $this->assertSame('${screen.FOTOS.form.fotos_veiculo}', $payload['fotos_veiculo']);
        $this->assertSame('${form.observacoes}', $payload['observacoes']);
        $this->assertArrayHasKey('combustivel', $payload);
    }

    public function test_conformidade_nao_marcado_vira_conforme(): void
    {
        $r = (new FlowResponseParser)->interpretar($this->defConformidade(3), [
            'km' => '123.456', 'horimetro' => '1500,5',
            'nc_1' => ['102'], 'na_1' => ['103'],
            'fotos_veiculo' => [['id' => 'm1'], ['id' => 'm2'], ['id' => 'm3'], ['id' => 'm4']],
        ]);

        $this->assertSame(123456, $r['km']);
        $this->assertSame(1500.5, $r['horimetro']);
        $this->assertSame(['conforme', 'nao_conforme', 'nao_aplicavel'], array_column($r['respostas'], 'valor'));
        $this->assertSame([], $r['alertas']);
    }

    public function test_status_rejeita_opcao_inexistente(): void
    {
        $r = (new FlowResponseParser)->interpretar($this->defStatus(), [
            'km' => '10', 'combustivel' => '4', 's_1' => 'ausente', 's_2' => 'inventada',
            'av_frente' => ['A', 'X'],
        ]);

        $this->assertCount(1, $r['respostas']);
        $this->assertTrue($r['respostas'][0]['nao_conforme']);
        $this->assertSame([['area' => 'frente', 'tipo' => 'A']], $r['avarias']);
        $this->assertNotEmpty($r['alertas']); // item 2 inválido + fotos abaixo do mínimo
    }
}
