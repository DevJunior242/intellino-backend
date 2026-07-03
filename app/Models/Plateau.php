<?php

namespace App\Models;

use App\Models\Evenement;
use App\Models\ConfigNotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Plateau extends Model
{
    use HasUuids;
    protected $table = 'plateaux';
    protected $fillable = [
        'nom',
        'evenement_id',
        'config_notation_id',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    public function evenement()
    {
        return $this->belongsTo(Evenement::class);
    }
    public function configNotations()
    {
        return $this->belongsTo(ConfigNotation::class);
    }
}
