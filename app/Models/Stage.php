<?php

namespace App\Models;

use App\Models\Saison;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Stage extends Model
{
    use HasUuids;
    protected $fillable = ['title', 'type', 'organisateur_id', 'organisateur_type', 'level', 'start_at', 'end_at', 'saison_id', 'price', 'status'];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'price'  => 'decimal:2',
    ];

    public function organisateur()
    {
        return $this->morphTo();
    }
    public function saison()
    {
        return $this->belongsTo(Saison::class);
    }
}
