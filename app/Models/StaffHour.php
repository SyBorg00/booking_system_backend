<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffHour extends Model
{
    use HasFactory;

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


    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
