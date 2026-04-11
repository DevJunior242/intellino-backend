<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Contact extends Model
{
    use HasUuids;
    protected $table = 'contacts';
    protected $fillable = [
        'title',
        'message',
    ];
    protected $keyType = 'string';

    public $incrementing = false;
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
