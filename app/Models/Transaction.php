<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Saison;
use App\Models\PaymentMethod;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Unifie 4 anciens systèmes de paiement (licence_payments, affiliation_payments,
 * stage_payments, examen_payments) : tout ce qui fait entrer de l'argent d'un
 * club vers une Ligue/Fédération, même machine à états
 * (pending -> declared -> paid). `payable_type` distingue le type de paiement
 * ('licence_lot'|'affiliation'|'stage'|'examen'), `organisateur_*` porte le
 * côté receveur (Club/Ligue/Federation).
 */
class Transaction extends Model
{
    use HasUuids, SoftDeletes;

    public const PAYABLE_LICENCE_LOT = 'licence_lot';
    public const PAYABLE_AFFILIATION = 'affiliation';
    public const PAYABLE_STAGE = 'stage';
    public const PAYABLE_EXAMEN = 'examen';

    protected $fillable = [
        'club_id',
        'organisateur_id',
        'organisateur_type',
        'saison_id',
        'payable_type',
        'payable_id',
        'amount',
        'status',
        'sender_number',
        'transaction_id',
        'declared_at',
        'payment_method_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'declared_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function organisateur()
    {
        return $this->morphTo();
    }

    public function saison()
    {
        return $this->belongsTo(Saison::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
