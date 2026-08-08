<?php

namespace App\Support\Clinic;

class RadiologyModalities
{
    public const VALUES = ['Periapical', 'Panoramic', 'CBCT', 'Cephalometric', 'Bitewing'];

    public static function values(): array
    {
        return self::VALUES;
    }
}
