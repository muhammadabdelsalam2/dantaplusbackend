<?php

namespace App\Enums;

enum LabRole: string
{
    case LabAdmin = 'lab_admin';
    case LabTechnician = 'lab_technician';
    case LabReceptionist = 'lab_receptionist';
    case DeliveryRep = 'delivery_rep';
}
