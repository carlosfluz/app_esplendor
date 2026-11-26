<?php

namespace App\Enums;

enum StatusChecklist: string
{
    case AguardandoFormulario = 'aguardando_formulario';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';
    case Expirado = 'expirado';
}
