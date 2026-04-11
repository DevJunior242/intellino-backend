<?php

namespace App\Models;

use App\Models\Jury;
use App\Models\Candidat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BureauMembre extends Model
{
    use HasUuids;
    protected $table = 'bureaumembres';
    protected $fillable = ['candidat_id', 'date_nomination', 'jury_id', 'is_elu'];
    protected $keyType = 'string';
    public $incrementing = false;


    public function candidat()
    {
        return $this->belongsTo(Candidat::class);
    }
    public function jury()
    {
        return $this->belongsTo(Jury::class);
    }
}
