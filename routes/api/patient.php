<?php

use Illuminate\Support\Facades\Route;

Route::prefix('patient')
    ->middleware(['auth:sanctum', 'role:patient'])
    ->group(function () {
        // TODO: patient endpoints
    });
