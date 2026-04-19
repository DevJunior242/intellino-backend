<?php

namespace App\Models;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SessionModel extends Model
{
    use HasUuids;
    protected $table = 'session_models';
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'session_date',
        'old_session_date',
        'start_time',
        'actual_start_time',
        'end_time',
        'actual_end_time',
        'status',
        'cancel_reason',
        'cancelled_at',
        'parent_session_id',
        'replacement_instructor_id',
        'replacement_start_time',
        'replacement_end_time',
    ];

    protected $attributes = ['status' => 'scheduled'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public static function clubAdminRoles()
    {
        return self::whereIn('name', [
            'admxin_club',
            'instructeur'
        ])->pluck('id');
    }
}
