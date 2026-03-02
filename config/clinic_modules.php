<?php

return [
    // Keys ثابتة تتبعت من الفرونت وتتحفظ في DB
    'items' => [
        ['key' => 'insurance',    'label' => 'Insurance'],
        ['key' => 'patients',     'label' => 'Patients'],
        ['key' => 'billing',      'label' => 'Billing'],
        ['key' => 'appointments', 'label' => 'Appointments'],
        ['key' => 'messaging',    'label' => 'Messaging'],
        ['key' => 'equipment',    'label' => 'Equipment'],
        ['key' => 'materials',    'label' => 'Materials'],
        ['key' => 'labs',         'label' => 'Labs'],
    ],

    // عشان الـ validation
    'keys' => [
        'insurance',
        'patients',
        'billing',
        'appointments',
        'messaging',
        'equipment',
        'materials',
        'labs',
    ],
];
