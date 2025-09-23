<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    protected $fillable = [
        'auth_user_id',      // Référence vers authentificationService
        'bio',               // Champs étendus uniquement
        'avatar',
        'phone',
        'address',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
    ];

}