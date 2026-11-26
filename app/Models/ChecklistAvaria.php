<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistAvaria extends Model
{
    protected $table = 'checklist_avarias';

    public $timestamps = false;

    protected $guarded = ['id'];

    public const AREAS = [
        'frente' => 'Frente',
        'traseira' => 'Traseira',
        'lateral_esquerda' => 'Lateral esquerda',
        'lateral_direita' => 'Lateral direita',
        'teto' => 'Teto',
        'interior' => 'Interior',
    ];

    public const TIPOS = [
        'A' => 'Amassado',
        'P' => 'Picotado',
        'Q' => 'Quebrado / Estragado',
        'R' => 'Riscado / Arranhado',
        'T' => 'Trincado',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
