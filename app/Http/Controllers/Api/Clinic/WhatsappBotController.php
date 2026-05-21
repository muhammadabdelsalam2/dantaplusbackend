<?php

namespace App\Http\Controllers\Api\Clinic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinic\Settings\SimulateWhatsappBotRequest;
use App\Http\Requests\Clinic\Settings\ToggleWhatsappBotRequest;
use App\Http\Requests\Clinic\Settings\UpdateWhatsappBotRequest;
use App\Services\Clinic\WhatsappBot\WhatsappBotService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class WhatsappBotController extends Controller
{
    use ApiResponse;

    public function __construct(private WhatsappBotService $service)
    {
    }

    public function index()
    {
        return $this->respond($this->service->index());
    }

    public function update(UpdateWhatsappBotRequest $request)
    {
        return $this->respond($this->service->update($request->validated()));
    }

    public function toggle(ToggleWhatsappBotRequest $request)
    {
        return $this->respond($this->service->toggle($request->validated()['is_enabled']));
    }

    public function simulate(SimulateWhatsappBotRequest $request)
    {
        return $this->respond($this->service->simulate($request->validated()['message']));
    }

    public function webhook(Request $request)
    {
        return $this->respond($this->service->handleWebhook($request->all()));
    }

    private function respond(array $result)
    {
        if (! $result['success']) {
            return ApiResponse::error($result['message'], $result['code'], $result['errors'] ?? null);
        }

        return ApiResponse::success($result['data'], $result['message'], $result['code']);
    }
}
