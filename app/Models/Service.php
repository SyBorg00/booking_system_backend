<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'buffer_minutes',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
    ];


    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    //display the business that owns the service
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    //display the staff members that can provide the service
    public function staff()
    {
        return $this->belongsToMany(
            Staff::class,
            'staff_services'
        );
    }

    //display the appointments that include the service
    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class);
    }

    public function appointments()
    {
        return $this->belongsToMany(Appointment::class, 'appointment_services')
            ->withPivot([
                'price',
                'currency',
                'duration_minutes',
                'buffer_minutes'
            ])
            ->withTimestamps();
    }
}
