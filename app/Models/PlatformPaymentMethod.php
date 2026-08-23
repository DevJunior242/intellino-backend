<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlatformPaymentMethod extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'provider',
        'label',
        'account_number',
        'account_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
