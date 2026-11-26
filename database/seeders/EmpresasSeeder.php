<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Seeder;

/** Empresas que aparecem nos cabeçalhos dos formulários em papel. */
class EmpresasSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            [
                'cnpj' => '10.407.743/0001-34',
                'razao_social' => 'Esplendor Transportes',
                'endereco' => 'Avenida 04, Quadra 21, 04',
                'telefone' => '98991876637',
            ],
            [
                'cnpj' => '39.283.959/0001-17',
                'razao_social' => 'Esplendor Transportes e Locação',
                'endereco' => 'Rua São Francisco, nº 222, Itapera, São Luís - MA, 65092-054',
                'telefone' => '98991876637',
            ],
            [
                'cnpj' => '14.415.796/0001-34',
                'razao_social' => 'RR Maraguas Ltda',
                'nome_fantasia' => 'Maraguas',
                'endereco' => 'Rua Santa Maria, nº 52, Maiobinha, São José de Ribamar - MA',
                'telefone' => '98991876637',
            ],
        ];

        foreach ($empresas as $e) {
            Empresa::updateOrCreate(['cnpj' => $e['cnpj']], $e);
        }
    }
}
