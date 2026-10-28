<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use BelongsToBarangay, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'barangay_id',
        'email_verification_code_hash',
        'email_verification_code_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code_hash',
        'email_verification_code_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function residentProfile()
    {
        return $this->hasOne(ResidentProfile::class);
    }

    public function household()
    {
        return $this->belongsToMany(Household::class, 'household_members')->withPivot('relationship')->withTimestamps();
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'resident_id');
    }

    public function disasterAssistanceRequests()
    {
        return $this->hasMany(DisasterAssistanceRequest::class, 'resident_id');
    }
}
