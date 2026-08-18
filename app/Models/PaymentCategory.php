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
        'is_system',
        'club_id',
    ];
    protected $casts = [
        'affects_validity' => 'boolean',
        'is_system' => 'boolean',
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
