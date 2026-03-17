<?php

namespace App\Enums;

enum ClinicType: string
{
    case GeneralDentist = 'General Dentist';
    case Orthodontics = 'Orthodontics';
    case Prosthodontics = 'Prosthodontics';
    case PediatricDentistry = 'Pediatric Dentistry';
    case Endodontics = 'Endodontics';
    case Periodontics = 'Periodontics';
    case OralSurgery = 'Oral Surgery';
}
