<?php

use App\Http\Controllers\Api\Chat\Message\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('doctor')
    ->middleware(['auth:sanctum', 'role:doctor'])
    ->group(function () {

    
    });
