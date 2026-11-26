<?php

namespace App\Enums;

enum TipoChecklist: string
{
    case Entrega = 'entrega';
    case Devolucao = 'devolucao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Entrega => 'Entrega',
            self::Devolucao => 'Devolução',
        };
    }
}
