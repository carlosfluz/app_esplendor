<?php

namespace App\Enums;

enum TipoAvaliacao: string
{
    /** Cada item tem opções próprias (ex.: PNEUS → Careca / Furado / Meia vida / OK). */
    case Status = 'status';

    /** Cada item é Conforme / Não conforme / Não se aplica. */
    case Conformidade = 'conformidade';
}
