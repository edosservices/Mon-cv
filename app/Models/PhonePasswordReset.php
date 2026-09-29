<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhonePasswordReset extends Model
{
    protected $fillable = ['phone', 'token', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
