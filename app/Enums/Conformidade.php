<?php

namespace App\Enums;

enum Conformidade: string
{
    case Conforme = 'conforme';
    case NaoConforme = 'nao_conforme';
    case NaoAplicavel = 'nao_aplicavel';
}
