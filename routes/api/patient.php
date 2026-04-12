<?php

use Illuminate\Support\Facades\Route;

/*
| ====================================
|  Patient Protected Routes
| ====================================
*/
Route::middleware(['auth:sanctum', 'role:patient'])->group(function () {
    // Route::get('/patient/profile', ...);
});
