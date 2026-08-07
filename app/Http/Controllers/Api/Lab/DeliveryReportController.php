<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\DeliveryReport\IndexDeliveryReportRequest;
use App\Services\Lab\DeliveryReportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class DeliveryReportController extends Controller
{
    use ApiResponse;

    public function __construct(private DeliveryReportService $service)
    {
    }

    public function index(IndexDeliveryReportRequest $request)
    {
        $result = $this->service->index($request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function showRep(IndexDeliveryReportRequest $request, int $id)
    {
        $result = $this->service->showRepReport($id, $request->validated());

        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        // Build a temporary signed URL for direct Excel download (valid 60 minutes).
        // The route is accessible without an Authorization header — protected by signature only.
        $exportParams = array_filter([
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
        ], fn ($v) => $v !== null && $v !== '');

        $excelUrl = URL::temporarySignedRoute(
            'lab.delivery-reps.report.export',
            now()->addMinutes(60),
            array_merge(['id' => $id], $exportParams)
        );

        $data               = $result['data'];
        $data['excel_url']  = $excelUrl;

        return ApiResponse::success($data, $result['message'], $result['code']);
    }

    /**
     * Export the delivery rep report as an Excel/CSV file.
     *
     * This route is protected by a temporary signed URL (no Authorization header needed).
     * Expiry: 60 minutes — consistent with other signed exports in this codebase.
     */
    public function exportRep(Request $request, int $id)
    {
        $filters = array_filter([
            'start_date' => $request->query('start_date'),
            'end_date'   => $request->query('end_date'),
        ], fn ($v) => $v !== null && $v !== '');

        $result = $this->service->showRepReport($id, $filters);

        if (! $result['success']) {
            abort($result['code'] ?? 404, $result['message'] ?? 'Report not found.');
        }

        $rows     = $result['data']['deliveries'] ?? [];
        $rep      = $result['data']['rep'] ?? [];
        $summary  = $result['data']['summary'] ?? [];
        $filters  = $result['data']['filters'] ?? [];

        $handle = fopen('php://temp', 'r+');

        // ── Header rows ──────────────────────────────────────────────────────
        fputcsv($handle, ['Delivery Representative Report']);
        fputcsv($handle, ['Rep Name', $rep['name'] ?? 'N/A']);
        fputcsv($handle, ['Area', $rep['area'] ?? 'N/A']);
        fputcsv($handle, ['Phone', $rep['phone'] ?? 'N/A']);
        fputcsv($handle, []);
        fputcsv($handle, ['Period', ($filters['start_date'] ?? '') . ' → ' . ($filters['end_date'] ?? '')]);
        fputcsv($handle, ['Total Deliveries', $summary['total_deliveries'] ?? 0]);
        fputcsv($handle, ['Total Expenses', $summary['total_expenses'] ?? 0]);
        fputcsv($handle, ['On-Time Rate (%)', $summary['on_time_rate'] ?? 0]);
        fputcsv($handle, []);

        // ── Column headers ───────────────────────────────────────────────────
        fputcsv($handle, ['Date', 'Case ID', 'Clinic', 'Expense', 'Status']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['date']    ?? '',
                $row['case_id'] ?? '',
                $row['clinic']  ?? '',
                $row['expense'] ?? 0,
                $row['status']  ?? '',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $filename = 'delivery-rep-report-' . $id . '-' . now()->format('Ymd') . '.csv';

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
