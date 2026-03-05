<?php

use Illuminate\Support\Facades\Route;

Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'role:doctor'])
    ->group(function () {
        // TODO: doctor endpoints
    });
