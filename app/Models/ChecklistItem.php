<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $table = 'checklist_itens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opcoes' => 'array',
            'ativo' => 'boolean',
        ];
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ChecklistModelo::class, 'checklist_modelo_id');
    }

    public function secao(): BelongsTo
    {
        return $this->belongsTo(ChecklistSecao::class, 'checklist_secao_id');
    }
}
