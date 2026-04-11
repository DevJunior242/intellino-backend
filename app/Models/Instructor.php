<?php

namespace App\Models;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Instructor extends Model
{
    use HasUuids;
    protected $table = 'instructors';

    protected $fillable = [
        'club_id',
        'fullname',
        'phone',
        'grade',
    ];
    public $incrementing = false;
    protected $keyType = 'string';

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
