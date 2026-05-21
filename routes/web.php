<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');

});

Route::get('/debug-orders', function () {
    return [
        'order_count' => App\Models\Order::where('clinic_id', 26)->count(),
        'material_count' => App\Models\MaterialOrder::where('clinic_id', 26)->count(),
        'first_order' => App\Models\Order::where('clinic_id', 26)->first(),
        'with_relation' => App\Models\Order::with('supplierCompany')->where('clinic_id', 26)->first(),
    ];
});
