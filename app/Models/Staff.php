<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'user_id',
        'phone',
        'position',
        'photo',
        'status',
    ];

    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    //load the user that belongs to the staff 
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    //load the business that the staff belongs to
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    //load the services that the staff provides
    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'staff_services'
        );
    }

    //load the staff hours that the staff has
    public function staffHours()
    {
        return $this->hasMany(StaffHour::class);
    }

    //load the staff time off that the staff has taken
    public function timeOff()
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    //load the appointments that the staff has/is/will be taken
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
