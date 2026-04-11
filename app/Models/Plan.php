<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Plan extends Model
{

    use HasUuids;
    protected $fillable = [
        'name',
        'description',
        'amount',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
