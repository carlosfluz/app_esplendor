<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklists preenchidos e tudo que vem com eles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // vai como flow_token no WhatsApp
            $table->foreignId('checklist_modelo_id')->constrained('checklist_modelos');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos');
            $table->foreignId('vistoriador_id')->constrained('vistoriadores');
            $table->string('tipo', 20);   // entrega | devolucao
            $table->string('status', 30); // aguardando_formulario | concluido | cancelado | expirado
            $table->unsignedInteger('km')->nullable();
            $table->decimal('horimetro', 10, 1)->nullable();
            $table->unsignedTinyInteger('combustivel_oitavos')->nullable(); // 0..8 (0/8 a 8/8)
            $table->text('observacoes')->nullable();
            $table->text('observacoes_avarias')->nullable();
            $table->unsignedSmallInteger('total_nao_conformes')->default(0);
            // Horários sempre do servidor (recebimento do webhook), nunca digitados.
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->json('resposta_bruta')->nullable(); // response_json do Flow, para auditoria
            $table->timestamps();

            $table->index(['veiculo_id', 'concluido_em']);
            $table->index(['status', 'iniciado_em']);
        });

        Schema::create('checklist_respostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('checklist_itens');
            // status: id da opção (ok, ausente, quebrado...) | conformidade: conforme, nao_conforme, nao_aplicavel
            $table->string('valor', 30);
            $table->boolean('nao_conforme')->default(false);
            $table->string('observacao')->nullable();
            $table->unique(['checklist_id', 'checklist_item_id']);
        });

        // Legenda do papel: A Amassado, P Picotado, Q Quebrado/Estragado, R Riscado/Arranhado, T Trincado
        Schema::create('checklist_avarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->string('area', 30); // frente, traseira, lateral_esquerda, lateral_direita, teto, interior
            $table->char('tipo', 1);    // A P Q R T
            $table->index(['checklist_id', 'area']);
        });

        Schema::create('checklist_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->string('categoria', 20); // veiculo | avaria
            $table->string('wa_media_id')->unique();
            $table->string('nome_original')->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('disco', 20)->nullable();
            $table->string('caminho')->nullable(); // preenchido depois que o job baixa da Meta
            $table->unsignedInteger('tamanho_bytes')->nullable();
            $table->timestamp('baixado_em')->nullable();
            $table->timestamps();
        });

        // Log e idempotência: a Meta pode reenviar o mesmo webhook.
        Schema::create('whatsapp_mensagens', function (Blueprint $table) {
            $table->id();
            $table->string('wa_message_id')->unique();
            $table->string('wa_id', 20)->index();
            $table->string('direcao', 10); // entrada | saida
            $table->string('tipo', 30)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('checklist_id')->nullable()->constrained('checklists')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensagens');
        Schema::dropIfExists('checklist_fotos');
        Schema::dropIfExists('checklist_avarias');
        Schema::dropIfExists('checklist_respostas');
        Schema::dropIfExists('checklists');
    }
};
