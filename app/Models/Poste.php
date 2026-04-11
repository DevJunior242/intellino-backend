<?php

namespace App\Models;

use App\Models\Candidat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Poste extends Model
{
    use HasUuids;
    protected $table = 'postes';
    protected $fillable = ['title', 'rang', 'parent_id'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function parent()
    {
        return $this->belongsTo(Poste::class, 'parent_id');
    }


    public function candidates()
    {
        return $this->hasMany(Candidat::class);
    }
    public function children()
    {
        return $this->hasMany(Poste::class, 'parent_id')->orderBy('rang', 'asc');
    }
    protected static function booted()
    {
        static::addGlobalScope('order', function ($builder) {
            $builder->orderBy('rang', 'asc');
        });
    }
}
