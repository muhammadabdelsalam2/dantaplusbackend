<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Company\AccountController;
use App\Http\Controllers\Api\Company\BillingController;
use App\Http\Controllers\Api\Company\CommunicationController;
use App\Http\Controllers\Api\Company\DashboardController;
use App\Http\Controllers\Api\Company\ExternalOrderController;
use App\Http\Controllers\Api\Company\InventoryController;
use App\Http\Controllers\Api\Company\OrderController;
use App\Http\Controllers\Api\Company\ProductController;
use App\Http\Controllers\Api\Company\ReportController;
use App\Http\Controllers\Api\Company\SettingController;
use App\Http\Controllers\Api\Company\ShippingZoneController;
use App\Http\Controllers\Api\Company\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', fn() => \App\Support\ApiResponse::success(auth()->user(), 'User fetched successfully'));
    });
});

Route::prefix('company')
    ->middleware(['auth:sanctum', 'api.error', 'role:material_company_admin|sales_rep|delivery_staff'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/order-trends', [DashboardController::class, 'orderTrends']);

        Route::middleware('role:material_company_admin')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::patch('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
        });

        Route::middleware('role:material_company_admin|sales_rep')->group(function () {
            Route::get('/products', [ProductController::class, 'index']);
            Route::post('/products', [ProductController::class, 'store']);
            Route::get('/products/{id}', [ProductController::class, 'show']);
            Route::patch('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
            Route::get('/categories', [ProductController::class, 'categories']);
        });

        Route::middleware('role:material_company_admin')->group(function () {
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::post('/inventory', [InventoryController::class, 'store']);
            Route::get('/inventory/{id}', [InventoryController::class, 'show']);
            Route::patch('/inventory/{id}', [InventoryController::class, 'update']);
            Route::delete('/inventory/{id}', [InventoryController::class, 'destroy']);
            Route::post('/inventory/{id}/stock-adjustments', [InventoryController::class, 'stockAdjustment']);
            Route::get('/inventory/{id}/logs', [InventoryController::class, 'logs']);
        });

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::patch('/orders/{id}', [OrderController::class, 'update']);
        Route::post('/orders/{id}/complete', [OrderController::class, 'complete']);
        Route::get('/orders/{id}/communication-logs', [OrderController::class, 'communicationLogs']);

        Route::post('/external-orders', [ExternalOrderController::class, 'store']);
        Route::get('/external-orders', [ExternalOrderController::class, 'index']);
        Route::patch('/external-orders/{id}', [ExternalOrderController::class, 'update']);
        Route::patch('/external-orders/{id}/status', [ExternalOrderController::class, 'updateStatus']);
        Route::post('/external-orders/{id}/send-whatsapp', [ExternalOrderController::class, 'sendWhatsApp']);

        Route::middleware('role:material_company_admin|sales_rep')->group(function () {
            Route::get('/invoices', [BillingController::class, 'index']);
            Route::get('/invoices/{id}', [BillingController::class, 'show']);
            Route::post('/invoices', [BillingController::class, 'store']);
            Route::patch('/invoices/{id}', [BillingController::class, 'update']);
            Route::patch('/invoices/{id}/mark-paid', [BillingController::class, 'markPaid']);
            Route::post('/invoices/{id}/send', [BillingController::class, 'send']);
            Route::get('/invoices/{id}/download', [BillingController::class, 'download']);
            Route::post('/payments', [BillingController::class, 'payments']);
        });

        Route::middleware('role:material_company_admin|sales_rep')->prefix('accounts')->group(function () {
            Route::get('/summary', [AccountController::class, 'summary']);
            Route::get('/invoices', [AccountController::class, 'invoices']);
            Route::get('/expenses', [AccountController::class, 'expenses']);
            Route::post('/expenses', [AccountController::class, 'storeExpense']);
            Route::get('/bank-transactions', [AccountController::class, 'bankTransactions']);
            Route::post('/bank-transactions/sync', [AccountController::class, 'syncBankTransactions']);
            Route::get('/profit-loss', [AccountController::class, 'profitLoss']);
        });

        Route::get('/conversations', [CommunicationController::class, 'index']);
        Route::get('/conversations/{id}/messages', [CommunicationController::class, 'messages']);
        Route::post('/conversations/{id}/messages', [CommunicationController::class, 'storeMessage']);
        Route::post('/conversations/{id}/files', [CommunicationController::class, 'storeFile']);
        Route::get('/conversations/{id}/files', [CommunicationController::class, 'files']);
        Route::post('/conversations/{id}/send-invoice', [CommunicationController::class, 'sendInvoice']);

        Route::middleware('role:material_company_admin|sales_rep')->prefix('reports')->group(function () {
            Route::get('/orders-by-month', [ReportController::class, 'ordersByMonth']);
            Route::get('/revenue-by-clinic', [ReportController::class, 'revenueByClinic']);
            Route::get('/most-requested-materials', [ReportController::class, 'mostRequestedMaterials']);
        });

        Route::middleware('role:material_company_admin')->prefix('settings')->group(function () {
            Route::get('/', [SettingController::class, 'show']);
            Route::patch('/profile', [SettingController::class, 'updateProfile']);
            Route::patch('/communication', [SettingController::class, 'updateCommunication']);
            Route::post('/communication/test', [SettingController::class, 'testCommunication']);
            Route::patch('/automation', [SettingController::class, 'updateAutomation']);
            Route::get('/whatsapp-logs', [SettingController::class, 'whatsappLogs']);
        });

        Route::middleware('role:material_company_admin')->group(function () {
            Route::get('/shipping-zones', [ShippingZoneController::class, 'index']);
            Route::post('/shipping-zones', [ShippingZoneController::class, 'store']);
            Route::patch('/shipping-zones/{id}', [ShippingZoneController::class, 'update']);
            Route::delete('/shipping-zones/{id}', [ShippingZoneController::class, 'destroy']);
            Route::patch('/shipping-zones/{id}/toggle-status', [ShippingZoneController::class, 'toggleStatus']);
        });
    });

// Route::get('/login', function () {
//     return response()->json(['message' => 'Please login'], 401);
// })->name('login');
