<?php

namespace App\Http\Controllers\Api\Clinic\Settings;

use App\Http\Controllers\Controller;
use App\Services\Clinic\Settings\CommunicationPermissionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CommunicationPermissionController extends Controller
{
    public function __construct(private CommunicationPermissionService $service)
    {
    }

    public function index()
    {
        return $this->respond($this->service->index());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*.role' => ['required', 'string', 'max:50'],
            'permissions.*.can_send_notes' => ['required', 'boolean'],
            'permissions.*.can_send_voice_notes' => ['required', 'boolean'],
            'permissions.*.can_access_patient_discussions' => ['required', 'boolean'],
            'permissions.*.can_delete_messages' => ['required', 'boolean'],
        ]);

        return $this->respond($this->service->update($data['permissions']));
    }

    private function respond(array $result)
    {
        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
