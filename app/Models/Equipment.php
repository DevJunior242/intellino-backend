<?php

namespace App\Models;

use App\Models\Club;
use App\Models\EquipmentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Equipment extends Model
{
    //
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'club_id',
        'equipment_category_id',
        'name',
        'total_quantity',
        'available_quantity',
        'min_stock_alert',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function equipmentCategory()
    {
        return $this->belongsTo(EquipmentCategory::class);
    }
}
