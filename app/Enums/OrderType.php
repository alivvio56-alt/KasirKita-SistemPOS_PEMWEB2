<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case TakeAway = 'take_away';
    case Preorder = 'preorder';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Makan di Tempat',
            self::TakeAway => 'Bawa Pulang',
            self::Preorder => 'Preorder',
        };
    }
}
