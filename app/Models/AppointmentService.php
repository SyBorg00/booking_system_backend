<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


//this model is a pivot model between services and appointments, which allows us to store additional information about the relationship between the two models, 
//such as the price and duration of the service for that specific appointment.
class AppointmentService extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'service_id',
        'price',
        'currency',
        'duration_minutes',
        'buffer_minutes'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
    ];

    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    //this two will be used as verficiation to ensure that the appointment and service exist before creating the pivot model?

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
