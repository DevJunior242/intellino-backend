<?php

namespace App\Models;

use App\Models\Category;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubDiscipline extends Model
{
    use HasUuids;
    protected $table = 'sub_disciplines';
    protected $fillable = ['nom', 'description', 'organisateur_id', 'organisateur_type'];
    protected $keyType = 'string';
    public $incrementing = false;


    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_subdisciplines')
            ->withTimestamps();
    }
    public function organisateur()
    {
        return $this->morphTo();
    }
    public function competitions()
    {
        return $this->hasMany(Competition::class, 'sub_discipline_id');
    }
}
