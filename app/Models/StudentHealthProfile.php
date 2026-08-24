<?php

namespace App\Models;

use App\Models\User;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudentHealthProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'student_id',
        'groupe_sanguin',
        'allergies',
        'conditions_medicales',
        'medecin_nom',
        'medecin_telephone',
        'contact_urgence_nom',
        'contact_urgence_telephone',
        'contact_urgence_relation',
        'certificat_medical_fourni',
        'certificat_medical_expire_le',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'certificat_medical_fourni' => 'boolean',
        'certificat_medical_expire_le' => 'date',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
