<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Salaries = 'salaries';
    case Marketing = 'marketing';
    case Supplies = 'supplies';
    case StockPurchase = 'stock_purchase';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Sewa',
            self::Utilities => 'Utilitas (Listrik, Air, dll)',
            self::Salaries => 'Gaji',
            self::Marketing => 'Marketing',
            self::Supplies => 'Perlengkapan',
            self::StockPurchase => 'Pembelian Stok',
            self::Maintenance => 'Perawatan',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Rent => 'purple',
            self::Utilities => 'amber',
            self::Salaries => 'blue',
            self::Marketing => 'pink',
            self::Supplies => 'cyan',
            self::StockPurchase => 'emerald',
            self::Maintenance => 'orange',
            self::Other => 'zinc',
        };
    }
}
