<?php

namespace App\Models;

use App\Models\Licence;
use App\Models\Transaction;
use App\Models\StageRegistration;
use App\Models\ExamenCandidat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TransactionItem extends Model
{
    use HasUuids;

    public const ITEMABLE_LICENCE = 'licence';
    public const ITEMABLE_STAGE_REGISTRATION = 'stage_registration';
    public const ITEMABLE_EXAMEN_CANDIDAT = 'examen_candidat';

    protected $fillable = [
        'transaction_id',
        'itemable_type',
        'itemable_id',
    ];

    private const ITEMABLE_MODELS = [
        self::ITEMABLE_LICENCE => Licence::class,
        self::ITEMABLE_STAGE_REGISTRATION => StageRegistration::class,
        self::ITEMABLE_EXAMEN_CANDIDAT => ExamenCandidat::class,
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * `itemable_type` est une chaîne fixe ('licence'|'stage_registration'|
     * 'examen_candidat'), pas un nom de classe — cette appli n'utilise pas
     * de morph map global, donc on résout manuellement plutôt que via
     * morphTo().
     */
    public function itemable(): ?Model
    {
        $modelClass = self::ITEMABLE_MODELS[$this->itemable_type] ?? null;

        return $modelClass ? $modelClass::find($this->itemable_id) : null;
    }
}
