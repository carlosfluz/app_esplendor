<?php

namespace Database\Seeders;

use App\Models\ChecklistModelo;
use App\Models\Empresa;
use App\Models\Veiculo;
use App\Models\Vistoriador;
use Illuminate\Database\Seeder;

/**
 * Dados para testar no WhatsApp: um veículo de cada modelo e você como vistoriador.
 * Defina WHATSAPP_TESTE_NUMERO no .env (ex.: 5598999999999) antes de rodar.
 */
class DemoChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $esplendor = Empresa::where('cnpj', '39.283.959/0001-17')->firstOrFail();
        $maraguas = Empresa::where('cnpj', '14.415.796/0001-34')->firstOrFail();

        $veiculos = [
            ['TST1A11', 'micro_onibus', 'MICRO-ONIBUS', $esplendor, 'acessorios'],
            ['TST1A22', 'caminhonete', 'Caminhonete', $esplendor, 'acessorios'],
            ['TST1A33', 'caminhao_pipa', 'Caminhão pipa', $maraguas, 'caminhao_pipa'],
            ['TST1A44', 'caminhao_munk', 'Caminhão munck', $esplendor, 'caminhao_munk'],
        ];

        foreach ($veiculos as [$placa, $tipo, $modelo, $empresa, $codigoModelo]) {
            Veiculo::updateOrCreate(['placa' => $placa], [
                'tipo' => $tipo,
                'modelo' => $modelo,
                'empresa_id' => $empresa->id,
                'checklist_modelo_id' => ChecklistModelo::where('codigo', $codigoModelo)->value('id'),
            ]);
        }

        if ($numero = env('WHATSAPP_TESTE_NUMERO')) {
            Vistoriador::updateOrCreate(['whatsapp' => $numero], ['nome' => 'Vistoriador teste']);
        }
    }
}
