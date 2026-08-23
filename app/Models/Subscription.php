<?php

namespace App\Models;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Subscription extends Model
{
    use HasUuids;

    const STATUS_PENDING = 'pending_payment';
    const STATUS_PAID = 'paid';
    const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'organisateur_id',
        'organisateur_type',
        'plan_id',
        'amount',
        'start_date',
        'end_date',
        'status',
    ];
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function organisateur()
    {
        return $this->morphTo();
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
