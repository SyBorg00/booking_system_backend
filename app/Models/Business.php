<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'currency',
        'phone',
        'email',
        'address',
        'timezone',
        'logo',
        'status',
    ];

    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    //load the staff members that belongs to the business
    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    //load the customer within the business
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    //display the multiple services that the business offers
    public function services()
    {
        return $this->hasMany(Service::class);
    }

    //display the multiple business hours that the business has
    public function businessHours()
    {
        return $this->hasMany(BusinessHour::class);
    }

    //display the multiple appointments that the business has
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
