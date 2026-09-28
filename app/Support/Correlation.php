<?php

namespace App\Support;

use Illuminate\Support\Str;

class Correlation
{
    private ?string $id = null;

    public function id(): string
    {
        return $this->id ??= (string) Str::uuid();
    }

    public function reset(): string
    {
        $this->id = (string) Str::uuid();

        return $this->id;
    }
}
