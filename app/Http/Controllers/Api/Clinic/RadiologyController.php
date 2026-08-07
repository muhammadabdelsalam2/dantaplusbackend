<?php
 
namespace App\Http\Controllers\Api\Clinic;
 
use App\Http\Controllers\Controller;
use App\Services\Clinic\RadiologyService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
 
class RadiologyController extends Controller
{
    public function __construct(private RadiologyService $service)
    {
    }
 
    public function index(int $patientId)
    {
        return $this->respond($this->service->index($patientId));
    }
 
    public function compare(Request $request)
    {
        $data = $request->validate([
            'case_1_id' => ['required', 'integer'],
            'case_2_id' => ['required', 'integer', 'different:case_1_id'],
        ]);
 
        return $this->respond($this->service->compare((int) $data['case_1_id'], (int) $data['case_2_id']));
    }
 
    public function downloadPdf(int $radiologyId)
    {
        return $this->respond($this->service->downloadPdfLink($radiologyId));
    }
 
    public function pdfFile(int $radiologyId)
    {
        $result = $this->service->downloadPdf($radiologyId);
 
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }
 
        return response($result['data']['content'], 200, [
            'Content-Type' => $result['data']['content_type'],
            'Content-Disposition' => 'attachment; filename="' . $result['data']['filename'] . '"',
        ]);
    }
 
    
    public function comparePdfFile(Request $request)
    {
        $data = $request->validate([
            'case_1' => ['required', 'integer'],
            'case_2' => ['required', 'integer', 'different:case_1'],
        ]);
 
        $result = $this->service->downloadComparePdf((int) $data['case_1'], (int) $data['case_2']);
 
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }
 
        return response($result['data']['content'], 200, [
            'Content-Type' => $result['data']['content_type'],
            'Content-Disposition' => 'attachment; filename="' . $result['data']['filename'] . '"',
        ]);
    }
 
    private function respond(array $result)
    {
        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}