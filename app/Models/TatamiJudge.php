<?php

namespace App\Models;

use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;

class TatamiJudge extends Model
{
    protected $fillable = [
        'config_notation_id',
        'ip_address',
        'juge_numero',
        'last_seen_at',
    ];

    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }
}
