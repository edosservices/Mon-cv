<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProviderSetting extends Model
{
    protected $fillable = ['provider', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
