<?php

namespace App\Models;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubscriptionPayment extends Model
{
    use HasUuids;
    protected $table = 'subscription_payments';

    const STATUS_DECLARED = 'declared';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'subscription_id',
        'platform_payment_method_id',
        'sender_number',
        'transaction_id',
        'amount',
        'status',
        'declared_at',
        'confirmed_at',
        'confirmed_by_user_id',
    ];
    protected $casts = [
        'declared_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function platformPaymentMethod()
    {
        return $this->belongsTo(PlatformPaymentMethod::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
