<?php

namespace App\Models;

use App\Models\OrdrePassage;
use App\Models\RotationArbitre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Note extends Model
{
    use HasUuids;
    protected $table = 'notes';
    protected $fillable = ['ordre_passage_id', 'rotation_arbitre_id', 'valeur', 'note_a'];

    protected $casts = [
        'valeur' => 'decimal:2',
        'note_a' => 'datetime',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    public function ordrePassage()
    {
        return $this->belongsTo(OrdrePassage::class);
    }

    public function rotationArbitre()
    {
        return $this->belongsTo(RotationArbitre::class);
    }
}
