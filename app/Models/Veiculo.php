<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veiculo extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /** Normaliza "abc-1d23" / "ABC 1D23" para "ABC1D23". */
    public static function normalizarPlaca(string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
    }

    /** Placa antiga (ABC1234) ou Mercosul (ABC1D23). */
    public static function parecePlaca(string $texto): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', self::normalizarPlaca($texto));
    }

    public function setPlacaAttribute(string $valor): void
    {
        $this->attributes['placa'] = self::normalizarPlaca($valor);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function modeloChecklist(): BelongsTo
    {
        return $this->belongsTo(ChecklistModelo::class, 'checklist_modelo_id');
    }

    public function contratos(): BelongsToMany
    {
        return $this->belongsToMany(Contrato::class, 'contrato_veiculo');
    }

    public function contratoAtivo(): ?Contrato
    {
        return $this->contratos()
            ->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('data_fim')->orWhere('data_fim', '>=', today()))
            ->latest('data_inicio')
            ->first();
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }
}
