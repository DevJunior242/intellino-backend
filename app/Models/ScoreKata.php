<?php

namespace App\Models;

use App\Models\Inscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ScoreKata extends Model
{
    use HasUuids;
    protected $fillable = ['inscription_id', 'notes', 'score_final', 'tour'];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }
}
