<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'lastname',
        'email',
        'phone',
        'whatsapp',
        'address',
        'profile_photo',
    ];

    protected $appends = ['profile_photo_url'];

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo
            ? asset('storage/' . $this->profile_photo)
            : null;
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->lastname}");
    }
}