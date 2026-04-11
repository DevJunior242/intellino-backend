<?php

namespace App\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CategoryDisciplineleague extends Model
{
    use HasUuids;
    protected $table = 'category_disciplineleagues';
    protected $fillable = ['category_id', 'disciplineleague_id'];

    protected $keyType = 'string';
    public $incrementing = false;

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
