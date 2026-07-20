<?php

use Illuminate\Support\Facades\Route;
use App\Models\Invoice;
use App\Services\Company\BillingService;
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
Route::get('/debug-auth', function () {
    $user = auth()->user();
    return [
        'user_id' => $user?->id,
        'clinic_id' => $user?->clinic_id,
        'guard' => auth()->getDefaultDriver(),
    ];
});
Route::get('/run-invoice-backfill-temp-xyz123', function (BillingService $service) {
    $results = [];

    Invoice::whereNull('file_path')->get()->each(function ($invoice) use ($service, &$results) {
        try {
            $path = $service->generateAndStoreInvoicePdf($invoice);
            $invoice->update(['file_path' => $path]);
            $results[] = "OK: invoice #{$invoice->id} -> {$path}";
        } catch (\Throwable $e) {
            $results[] = "FAILED: invoice #{$invoice->id} -> " . $e->getMessage();
        }
    });

    return response()->json($results);
});
