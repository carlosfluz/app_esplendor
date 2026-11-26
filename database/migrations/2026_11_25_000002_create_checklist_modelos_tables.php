<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos (templates) de checklist. Cada formulário em papel vira um modelo:
 *  - tipo_avaliacao = 'status'       → cada item tem opções próprias (ANTENA: Ausente/OK/Quebrado...)
 *  - tipo_avaliacao = 'conformidade' → cada item é Conforme / Não conforme / Não se aplica
 * Mudar um item aqui e republicar o Flow atualiza o formulário no WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique(); // acessorios, caminhao_pipa, caminhao_munk
            $table->string('nome');
            $table->string('tipo_avaliacao', 20);  // status | conformidade
            $table->boolean('pede_km')->default(true);
            $table->boolean('pede_horimetro')->default(false);
            $table->boolean('pede_combustivel')->default(false);
            $table->boolean('pede_avarias')->default(true);
            $table->unsignedTinyInteger('fotos_min')->default(4);
            $table->unsignedTinyInteger('fotos_max')->default(6);
            $table->unsignedTinyInteger('fotos_avaria_max')->default(4); // fotos_max + fotos_avaria_max <= 10 (limite da Meta)
            $table->string('whatsapp_flow_id')->nullable();
            $table->string('whatsapp_flow_status', 20)->nullable(); // DRAFT | PUBLISHED
            $table->string('whatsapp_flow_hash', 64)->nullable();   // hash do JSON publicado, para saber se mudou
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('checklist_secoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_modelo_id')->constrained('checklist_modelos')->cascadeOnDelete();
            $table->string('nome');
            $table->unsignedSmallInteger('ordem')->default(0);
        });

        Schema::create('checklist_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_modelo_id')->constrained('checklist_modelos')->cascadeOnDelete();
            $table->foreignId('checklist_secao_id')->nullable()->constrained('checklist_secoes')->nullOnDelete();
            $table->string('codigo', 10)->nullable(); // numeração do formulário em papel: 1.15, 7...
            $table->string('descricao');              // texto completo, igual ao papel
            $table->string('rotulo_curto', 30);       // o que cabe na tela do WhatsApp
            // Só para tipo_avaliacao = status: [{"id":"ok","titulo":"OK","nao_conforme":false}, ...]
            $table->json('opcoes')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('veiculos', function (Blueprint $table) {
            $table->foreign('checklist_modelo_id')->references('id')->on('checklist_modelos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('veiculos', fn (Blueprint $table) => $table->dropForeign(['checklist_modelo_id']));
        Schema::dropIfExists('checklist_itens');
        Schema::dropIfExists('checklist_secoes');
        Schema::dropIfExists('checklist_modelos');
    }
};
