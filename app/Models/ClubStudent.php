<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubStudent extends Model
{
    protected $fillable = [
        'club_id',
        'student_id',
        'saison_id',
        'is_active',
    ];
}
