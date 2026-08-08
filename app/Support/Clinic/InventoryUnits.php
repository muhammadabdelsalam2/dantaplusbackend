<?php

namespace App\Support\Clinic;

class InventoryUnits
{
    public const VALUES = ['Piece', 'Box', 'KG', 'ML', 'Gram'];

    public static function values(): array
    {
        return self::VALUES;
    }
}
