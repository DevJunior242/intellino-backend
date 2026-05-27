<?php

namespace App\Models;

use App\Models\Combat;
use Illuminate\Database\Eloquent\Model;

class JudgeVote extends Model
{
    protected $fillable = [
        'combat_id',
        'juge_numero',
        'combattant',
        'type',
        'clicked_at',
    ];
    protected $casts = [
        'clicked_at' => 'datetime:Y-m-d H:i:s.u',
    ];
    public function combat()
    {
        return $this->belongsTo(Combat::class);
    }
}
