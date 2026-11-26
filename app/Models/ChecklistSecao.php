<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistSecao extends Model
{
    protected $table = 'checklist_secoes';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ChecklistModelo::class, 'checklist_modelo_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('ordem');
    }
}
