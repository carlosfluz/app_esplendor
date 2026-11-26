<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistFoto extends Model
{
    protected $table = 'checklist_fotos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['baixado_em' => 'datetime'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
