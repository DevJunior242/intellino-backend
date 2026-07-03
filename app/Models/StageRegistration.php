<?php

namespace App\Models;

use App\Models\Club;
use App\Models\User;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StageRegistration extends Model
{
    use HasUuids;

    protected $fillable = [
        'stage_id',
        'user_id',
        'club_id',
        'is_present',
        'payment_status',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
        'is_present' => 'boolean',
    ];

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
