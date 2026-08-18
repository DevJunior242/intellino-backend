<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PaymentCategory extends Model
{
    use  HasUuids;
    protected $fillable = [
        'name',
        'slug',
        'affects_validity',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
}
