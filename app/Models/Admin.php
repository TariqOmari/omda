<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'username', 'password', 'photo'];
    protected $hidden = ['password'];
    protected $casts = ['password' => 'hashed'];

    // Full URL for photo
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }
}