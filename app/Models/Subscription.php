<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Subscription extends Model
{
    use HasUuids;


    protected $fillable = [
        'club_id',
        'plan_id',
        'amount',
        'start_date',
        'end_date',
        'status',
    ];
    protected $attributes = [
        'status' => 'pending_payment',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public function club()
    {
        return $this->belongsTo(Club::class);
    }
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
