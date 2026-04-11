<?php

namespace App\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Disciplineleague extends Model
{
    use HasUuids;
    protected $table = 'disciplineleagues';
    protected $fillable = ['nom', 'description'];
    protected $keyType = 'string';
    public $incrementing = false;


    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_disciplineleagues')
            ->withTimestamps();
    }
}
