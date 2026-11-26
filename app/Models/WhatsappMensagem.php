<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMensagem extends Model
{
    protected $table = 'whatsapp_mensagens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    /**
     * Registra a mensagem recebida. Retorna false se ela já foi processada
     * (a Meta reenvia webhooks quando não recebe 200 a tempo).
     */
    public static function registrarEntrada(array $mensagem): bool
    {
        $registro = static::firstOrCreate(
            ['wa_message_id' => $mensagem['id']],
            [
                'wa_id' => $mensagem['from'],
                'direcao' => 'entrada',
                'tipo' => $mensagem['type'] ?? null,
                'payload' => $mensagem,
            ],
        );

        return $registro->wasRecentlyCreated;
    }
}
