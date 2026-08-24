<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'notes',
    ];

    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    // A customer belongs to a specific business
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    // A customer can have many appointments
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
