<?php

namespace App\Models;

use App\Models\Club;
use App\Models\Equipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EquipmentCategory extends Model
{
    use HasUuids;
    protected $table = 'equipment_categories';

    protected $fillable = [
        'club_id',
        'name',
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function equipments()
    {
        return $this->hasMany(Equipment::class);
    }
}
