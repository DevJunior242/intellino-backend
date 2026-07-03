<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PaymentMethod extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'organisateur_id',
        'organisateur_type',
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
    public function organisateur()
    {
        return $this->morphTo();
    }
}
