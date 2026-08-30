<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffTimeOff extends Model
{
    use HasFactory;
    protected $table = 'staff_time_offs';

    protected $fillable = [
        'staff_id',
        'start_datetime',
        'end_datetime',
        'reason',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
