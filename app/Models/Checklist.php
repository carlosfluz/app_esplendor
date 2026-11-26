<?php

namespace App\Models;

use App\Enums\StatusChecklist;
use App\Enums\TipoChecklist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Checklist extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoChecklist::class,
            'status' => StatusChecklist::class,
            'horimetro' => 'decimal:1',
            'iniciado_em' => 'datetime',
            'concluido_em' => 'datetime',
            'resposta_bruta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Checklist $c) {
            $c->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ChecklistModelo::class, 'checklist_modelo_id');
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function vistoriador(): BelongsTo
    {
        return $this->belongsTo(Vistoriador::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(ChecklistResposta::class);
    }

    public function avarias(): HasMany
    {
        return $this->hasMany(ChecklistAvaria::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ChecklistFoto::class);
    }

    public function scopeConcluidos(Builder $q): Builder
    {
        return $q->where('status', StatusChecklist::Concluido);
    }

    public function scopePendentes(Builder $q): Builder
    {
        return $q->where('status', StatusChecklist::AguardandoFormulario);
    }
}
