<?php

namespace App\Models;

use App\Enums\TipoAvaliacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistModelo extends Model
{
    protected $table = 'checklist_modelos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo_avaliacao' => TipoAvaliacao::class,
            'pede_km' => 'boolean',
            'pede_horimetro' => 'boolean',
            'pede_combustivel' => 'boolean',
            'pede_avarias' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function secoes(): HasMany
    {
        return $this->hasMany(ChecklistSecao::class)->orderBy('ordem');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('ordem');
    }

    public function veiculos(): HasMany
    {
        return $this->hasMany(Veiculo::class);
    }

    /**
     * Estrutura neutra (array puro) usada pelo FlowJsonBuilder e pelo FlowResponseParser.
     * Mantém os dois independentes do Eloquent, o que facilita testar.
     */
    public function paraDefinicao(): array
    {
        $itens = $this->itens()->where('ativo', true)->get();
        $secoes = $this->secoes()->get();

        $mapItem = fn (ChecklistItem $i) => [
            'id' => $i->id,
            'codigo' => $i->codigo,
            'descricao' => $i->descricao,
            'rotulo_curto' => $i->rotulo_curto,
            'opcoes' => $i->opcoes ?? [],
        ];

        $blocos = [];
        foreach ($secoes as $secao) {
            $doBloco = $itens->where('checklist_secao_id', $secao->id)->values();
            if ($doBloco->isNotEmpty()) {
                $blocos[] = ['nome' => $secao->nome, 'itens' => $doBloco->map($mapItem)->all()];
            }
        }
        $semSecao = $itens->whereNull('checklist_secao_id')->values();
        if ($semSecao->isNotEmpty()) {
            $blocos[] = ['nome' => 'Itens do checklist', 'itens' => $semSecao->map($mapItem)->all()];
        }

        return [
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'tipo_avaliacao' => $this->tipo_avaliacao->value,
            'pede_km' => $this->pede_km,
            'pede_horimetro' => $this->pede_horimetro,
            'pede_combustivel' => $this->pede_combustivel,
            'pede_avarias' => $this->pede_avarias,
            'fotos_min' => $this->fotos_min,
            'fotos_max' => $this->fotos_max,
            'fotos_avaria_max' => $this->fotos_avaria_max,
            'secoes' => $blocos,
        ];
    }
}
