<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\DeliveryReport\IndexDeliveryReportRequest;
use App\Services\Lab\DeliveryReportService;
use App\Support\ApiResponse;

class DeliveryReportController extends Controller
{
    use ApiResponse;

    public function __construct(private DeliveryReportService $service)
    {
    }

    public function index(IndexDeliveryReportRequest $request)
    {
        $result = $this->service->index($request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }

    public function showRep(IndexDeliveryReportRequest $request, int $id)
    {
        $result = $this->service->showRepReport($id, $request->validated());

        if (!$result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        if ($request->query('export') === 'excel') {
            $rows = $result['data']['deliveries'] ?? [];
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['Date', 'Case ID', 'Clinic', 'Expense', 'Status']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['date'] ?? '',
                    $row['case_id'] ?? '',
                    $row['clinic'] ?? '',
                    $row['expense'] ?? 0,
                    $row['status'] ?? '',
                ]);
            }
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content, 200, [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="delivery-rep-report-' . $id . '.xls"',
            ]);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
