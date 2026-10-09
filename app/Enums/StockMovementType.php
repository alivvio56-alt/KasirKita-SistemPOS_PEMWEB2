<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stok Masuk',
            self::Out => 'Stok Keluar (Penjualan)',
            self::Adjustment => 'Penyesuaian',
            self::Return => 'Pengembalian (Batal)',
        };
    }
}
