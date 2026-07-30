<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Examen;
use App\Models\PaymentMethod;
use App\Models\ExamenCandidat;
use App\Models\ExamenPaymentItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ExamenPayment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'examen_id',
        'club_id',
        'payment_method_id',
        'amount',
        'sender_number',
        'transaction_id',
        'status',
        'declared_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'declared_at' => 'datetime',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items()
    {
        return $this->hasMany(ExamenPaymentItem::class);
    }

    public function candidats()
    {
        return $this->hasManyThrough(
            ExamenCandidat::class,
            ExamenPaymentItem::class,
            'examen_payment_id',
            'id',
            'id',
            'examen_candidat_id'
        );
    }
}
