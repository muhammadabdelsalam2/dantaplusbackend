<?php

use App\Http\Controllers\Api\SuperAdmin\RoleController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use Illuminate\Support\Facades\Route;

/**
 * NOTE:
 * - This file is loaded under "/api" prefix already (from bootstrap/app.php).
 * - So routes here will be: /api/superadmin/...
 */
Route::prefix('superadmin')
    ->middleware(['auth:sanctum', 'role:super-admin'])
    ->group(function () {

        // Users Management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Roles Management
        Route::get('/roles', [RoleController::class, 'index']);                 // list + dropdown
        Route::post('/roles', [RoleController::class, 'store']);               // create
        Route::get('/roles/{role}', [RoleController::class, 'show']);          // show
        Route::patch('/roles/{role}', [RoleController::class, 'update']);      // update
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);    // delete
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']); // assign permissions (sync)
    });
