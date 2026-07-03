<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Saison;
use App\Models\Licence;
use App\Models\Federation;
use App\Models\PaymentMethod;
use App\Models\LicencePaymentItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LicencePayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'saison_id',
        'federation_id',
        'club_id',
        'payment_method_id',
        'amount',
        'sender_number',
        'status',
        'declared_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'declared_at' => 'datetime',
    ];

    public function saison()
    {
        return $this->belongsTo(Saison::class);
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

    public function items()
    {
        return $this->hasMany(LicencePaymentItem::class);
    }

    public function licences()
    {
        return $this->hasManyThrough(Licence::class, LicencePaymentItem::class, 'licence_payment_id', 'id', 'id', 'licence_id');
    }
}
