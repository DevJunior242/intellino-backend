<?php

namespace App\Models;

use App\Models\Inscription;
use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Note;

class OrdrePassage extends Model
{
    use HasUuids;
    protected $fillable = [
        'config_notation_id',
        'ordre',
        'statut',
        'score_final',
        'inscription_id',
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }
    public function configNotation()
    {
        return $this->belongsTo(ConfigNotation::class);
    }
    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
