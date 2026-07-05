<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Federation;
use App\Models\Affiliation;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;

class AffiliationPayment extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'affiliation_id',
        'federation_id',
        'club_id',
        'payment_method_id',
        'amount',
        'sender_number',
        'status',
        'transaction_id',
        'declared_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'declared_at' => 'datetime',
    ];

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
