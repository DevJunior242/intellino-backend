<?php

namespace App\Models;

use App\Models\Club;
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
    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
