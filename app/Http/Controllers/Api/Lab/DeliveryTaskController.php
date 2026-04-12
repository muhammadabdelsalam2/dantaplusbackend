<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\DeliveryTask;
use App\Services\Lab\DeliveryTrackingService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryTaskController extends Controller
{
    use ApiResponse;

    public function __construct(private DeliveryTrackingService $deliveryTrackingService)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:assigned,picked_up,in_transit,delivered,cancelled'],
            'delivery_rep_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tasks = $this->deliveryTrackingService->paginateForUser($request->user(), $validated);

        return ApiResponse::success([
            'items' => collect($tasks->items())->map(fn (DeliveryTask $task) => $this->deliveryTrackingService->mapTask($task))->all(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], 'Delivery tasks fetched successfully');
    }

    public function assign(Request $request, int $caseId)
    {
        $validated = $request->validate([
            'delivery_rep_user_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_for' => ['nullable', 'date'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'pickup_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $case = CaseModel::query()->where('lab_id', $request->user()?->lab_id)->findOrFail($caseId);
        $this->authorize('update', $case);
        $deliveryRep = $this->deliveryTrackingService->deliveryRepresentativeForLab((int) $request->user()?->lab_id, (int) $validated['delivery_rep_user_id']);

        $task = $this->deliveryTrackingService->assign($case, $deliveryRep, $validated);

        return ApiResponse::success($this->deliveryTrackingService->mapTask($task->fresh(['deliveryRep:id,name', 'case:id,case_number,status'])), 'Delivery assigned successfully');
    }

    public function updateLocation(Request $request, int $taskId)
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $task = $this->task($taskId);
        $this->authorize('update', $task);

        $task = $this->deliveryTrackingService->updateLocation($task, $validated);

        return ApiResponse::success($this->deliveryTrackingService->mapTask($task), 'Delivery location updated successfully');
    }

    public function updateStatus(Request $request, int $taskId)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(DeliveryTask::STATUSES)],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $task = $this->task($taskId);
        $this->authorize('update', $task);

        $task = $this->deliveryTrackingService->updateStatus($task, $validated);

        return ApiResponse::success($this->deliveryTrackingService->mapTask($task), 'Delivery status updated successfully');
    }

    private function task(int $taskId): DeliveryTask
    {
        return DeliveryTask::query()
            ->with('case')
            ->where('lab_id', auth()->user()?->lab_id)
            ->findOrFail($taskId);
    }
}
