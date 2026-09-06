<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'staff_id',
        'start_datetime',
        'end_datetime',
        'status',
        'notes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Ensure that the datetime variables are always formatted correctly
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    //to show that the appointment was/is issued to a specific business
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    //to show that the appointment belongs to a specific customer (as in, it was that specific customer who booked the appointment)
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    //to show that the appointment belongs to (is operated by) a specific staff member
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    //to show that a single appointment might contain multiple services to offer
    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class);
    }

    //similar to appointmentServices but instead conveniently get the actual service model itself (more detailed than the appointmentServices relationship)
    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'appointment_services'
        )->withPivot([
            'price',
            'currency',
            'duration_minutes',
            'buffer_minutes',
        ])
            ->withTimestamps();
    }

    /*==========================================
            RULES SECTION
    ============================================*/

    /*  
    | This ensures that the appointment can only transition to certain statuses based on its current status. For example, an 
    | appointment that is currently "pending" can only transition to "confirmed" or "cancelled", but not to "completed" or "no_show". 
    | This helps maintain the integrity of the appointment's lifecycle and prevents invalid status changes. 
    */
    public function canTransitionTo(string $newStatus): bool
    {
        /*
        |--------------------------------------------------------------------------
        | If there is no change in status, allow the transition (no-op)
        |--------------------------------------------------------------------------
        */
        if ($this->status === $newStatus) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Define allowed transitions for each status
        |--------------------------------------------------------------------------
        */
        $allowedTransitions = [
            'pending' => [
                'confirmed',
                'cancelled',
            ],

            'confirmed' => [
                'completed',
                'cancelled',
                'no_show',
            ],

            'completed' => [],

            'cancelled' => [],

            'no_show' => [],
        ];

        return in_array(
            $newStatus,
            $allowedTransitions[$this->status] ?? [],
            true
        );
    }
}
