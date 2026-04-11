<?php

namespace App\Models;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Like extends Model
{
    use HasUuids;
    protected $table = 'likes';

    protected $fillable = [
        'user_id',
        'club_id',
    ];
    protected $keyType = 'string';
    public $incrementing = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
