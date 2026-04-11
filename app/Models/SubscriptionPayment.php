<?php

namespace App\Models;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubscriptionPayment extends Model
{
    use HasUuids;
    protected $table = 'subscription_payments';

    protected $fillable = [
        'subscription_id',
        'payment_method',
        'transaction_id',
        'amount',
        'paid_at',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
