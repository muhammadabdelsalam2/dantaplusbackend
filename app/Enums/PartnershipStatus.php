<?php

namespace App\Enums;

enum PartnershipStatus: string
{
    case Active = 'Active';
    case Pending = 'Pending';
    case Paused = 'Paused';
    case Ended = 'Ended';
}
