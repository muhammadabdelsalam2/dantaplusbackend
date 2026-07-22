<?php

return [
    'clinic' => [
        'appointments' => ['appointments.view', 'appointments.create', 'appointments.update'],
        'billing' => ['billing.manage'],
        'communications' => ['communication.send'],
        'dashboard' => [],
        'equipment' => ['equipment.view'],
        'insurance' => ['insurance.view', 'insurance.create', 'insurance.update', 'insurance.delete'],
        'inventories' => ['inventory.view', 'inventory.manage'],
        'labs' => ['labs.send', 'labs.track', 'dental_labs.view', 'dental_labs.manage'],
        'materials' => ['materials.view'],
        'notifications' => [],
        'orders' => ['orders.view', 'orders.manage'],
        'patient-messages' => [],   
        'patients' => ['patients.view', 'patients.create', 'patients.update'],
        'settings' => ['settings.manage'],
        'support-tickets' => ['support.view', 'support.create', 'support.manage'],
        'tasks' => ['tasks.view', 'tasks.manage'],
        'treatments' => ['treatments.manage'],
         'team-chat' => [],
    ],

    'lab' => [
        'dashboard' => [],
        'accounting' => [],       // ⚠️ لسه فاضية — شوف "الحل الجذري" تحت
        'clinics' => [],          // ⚠️ لسه فاضية — شوف "الحل الجذري" تحت
        'analytics' => [],        // ⚠️ لسه فاضية — شوف "الحل الجذري" تحت
        'delivery-reports' => ['delivery.view'],
        'delivery-reps' => ['delivery.assign'],
        'delivery-tasks' => [],   // ⬅️ جديدة، كانت ناقصة خالص
        'communication' => [],
        'equipment' => ['equipment.view'],
        'inventories' => ['inventory.view', 'inventory.manage'],
        'orders' => ['cases.view', 'cases.create', 'cases.update'],
        'settings' => ['settings.manage'],
        'support' => ['support.view', 'support.create', 'support.manage'],
    ],

    'patient' => [
        'appointments' => [],
        'dashboard' => [],
        'documents' => [],
        'invoices' => [],
        'payments' => [],
        'profile' => [],
        'radiology' => [],
    ],

    'supplier' => [
        'accounts' => ['billing.manage'],
        'communication' => [],
        'dashboard' => [],
        'inventories' => ['inventory.view', 'inventory.manage'],
        'invoices' => ['billing.manage'],
        'orders' => ['orders.view', 'orders.manage'],
        'products' => ['products.manage'],
        'reports' => ['reports.view'],
        'settings' => ['settings.manage'],
        'users' => ['users.manage'],
    ],

    'super-admin' => [
        'clinics' => [],
        'labs' => [],
        'materials-companies' => [],
        'materials-orders' => [],
        'materials-commission' => [],
        'equipment' => [],
        'communication' => [],
        'notifications' => [],
        'notification-logs' => [],
        'feedback-reports' => [],
        'support-tickets' => [],
        'renewal-alerts' => [],
        'users' => [],
        'roles' => [],
        'subscriptions' => [],
        'settings' => [],
    ],
    'lab_role_modules' => [
    'lab_admin' => ['dashboard', 'accounting', 'analytics', 'clinics', 'orders', 'inventories', 'support', 'delivery-reps', 'delivery-reports', 'delivery-tasks', 'communication', 'equipment', 'settings'],
    'lab_receptionist' => ['dashboard', 'accounting', 'analytics', 'clinics', 'orders', 'inventories', 'support', 'delivery-reps', 'delivery-reports', 'delivery-tasks', 'communication'],
    'lab_technician' => ['dashboard', 'orders', 'inventories', 'support'],
    'delivery_representative' => ['dashboard', 'support', 'delivery-tasks'],
],
];
