<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Stage;
use App\Models\PaymentMethod;
use App\Models\StagePaymentItem;
use App\Models\StageRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StagePayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'stage_id',
        'club_id',
        'payment_method_id',
        'amount',
        'payment_method',
        'sender_number',
        'transaction_id',
        'status',
        'declared_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'declared_at' => 'datetime',
    ];

    public function stage()
    {
        return $this->belongsTo(Stage::class);
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
        return $this->hasMany(StagePaymentItem::class);
    }

    public function stageRegistrations()
    {
        return $this->hasManyThrough(
            StageRegistration::class,
            StagePaymentItem::class,
            'stage_payment_id',
            'id',
            'id',
            'stage_registration_id'
        );
    }
}
