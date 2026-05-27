<?php

namespace App\Models;

use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;

class TatamiJudges extends Model
{
    protected $table = 'tatami_judges';
    protected $fillable = ['config_notation_id', 'ip_address', 'judge_token', 'juge_numero', 'last_seen_at'];

    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }
}
