<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use hasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'last_name',
        'first_name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*==========================================
            RELATIONSHIPS SECTION
    ============================================*/

    //to show that a user can be associated with a single staff member
    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    //Ensure that a user can be associated with multiple businesses, and that a business can have multiple users (uses BusinessUser pivot table for this)
    public function businesses()
    {
        return $this->belongsToMany(
            Business::class,
            'business_user'
        )->withPivot('role')
            ->withTimestamps();
    }
}
