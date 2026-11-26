<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vistoriador extends Model
{
    protected $table = 'vistoriadores';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /** Guarda só dígitos, no formato do wa_id (55 + DDD + número). */
    public function setWhatsappAttribute(string $valor): void
    {
        $this->attributes['whatsapp'] = preg_replace('/\D/', '', $valor);
    }

    public static function porWaId(string $waId): ?self
    {
        return static::where('whatsapp', preg_replace('/\D/', '', $waId))->where('ativo', true)->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }
}
