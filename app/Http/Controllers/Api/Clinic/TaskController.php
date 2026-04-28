<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\IndexClinicTasksRequest;
use App\Http\Requests\Clinic\StoreClinicTaskRequest;
use App\Http\Requests\Clinic\UpdateClinicTaskRequest;
use App\Services\Clinic\TaskService;
use App\Support\ApiResponse;

class TaskController extends Controller
{
    use ApiResponse;

    public function __construct(private TaskService $service)
    {
    }

    public function index(IndexClinicTasksRequest $request)
    {
        $result = $this->service->index($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function store(StoreClinicTaskRequest $request)
    {
        $result = $this->service->store($request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function update(UpdateClinicTaskRequest $request, int $id)
    {
        $result = $this->service->update($id, $request->validated());

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }

    public function destroy(int $id)
    {
        $result = $this->service->delete($id);

        return $result['success']
            ? ApiResponse::success($result['data'], $result['message'], $result['code'])
            : ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
    }
}
