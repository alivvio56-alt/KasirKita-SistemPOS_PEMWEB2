<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Kasir = 'kasir';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin / Pemilik',
            self::Kasir => 'Kasir',
        };
    }
}
