<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffHour extends Model
{
    use HasFactory;

    //NOTE: day_of_week uses 0-6 to represent Sunday-Saturday respectively
    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_off',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_off' => 'boolean',
    ];


    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    //ensure that the staff hour is linked to a staff member
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
