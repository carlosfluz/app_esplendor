<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastros básicos: empresas do grupo, clientes, contratos, veículos e vistoriadores.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Esplendor Transportes, Esplendor Transportes e Locação, RR Maraguas...
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj', 18)->unique();
            $table->string('endereco')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('documento', 18)->nullable()->index(); // CPF/CNPJ
            $table->string('telefone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('numero')->nullable()->index();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Modelos de checklist são criados na migration seguinte; aqui só a FK fica pendente.
        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->unsignedBigInteger('checklist_modelo_id')->nullable();
            $table->string('placa', 7)->unique(); // sempre sem hífen e em maiúsculas: ABC1D23
            $table->string('tipo', 30);           // carro, caminhonete, onibus, micro_onibus, caminhao_pipa, caminhao_munk
            $table->string('modelo')->nullable(); // ex.: MICRO-ONIBUS, Renault Master
            $table->string('grupo')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('contrato_veiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->unique(['contrato_id', 'veiculo_id']);
        });

        // Quem pode abrir checklist pelo WhatsApp. O número é a identidade.
        Schema::create('vistoriadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
            $table->string('nome');
            $table->string('whatsapp', 15)->unique(); // formato wa_id: 5598991876637
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vistoriadores');
        Schema::dropIfExists('contrato_veiculo');
        Schema::dropIfExists('veiculos');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('empresas');
    }
};
