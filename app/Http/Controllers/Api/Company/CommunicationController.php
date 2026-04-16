<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Services\Company\CommunicationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    use ApiResponse;

    public function __construct(private CommunicationService $service) {}

    public function index() { return ApiResponse::success($this->service->conversations(), 'Conversations fetched successfully'); }
    public function messages(Conversation $id) { return ApiResponse::success($this->service->messages($id), 'Messages fetched successfully'); }
    public function storeMessage(SendMessageRequest $request, Conversation $id) { return ApiResponse::success($this->service->sendMessage($id, $request->validated()), 'Message sent successfully', 201); }
    public function storeFile(Request $request, Conversation $id) { $request->validate(['file' => 'required|file|max:5120']); return ApiResponse::success($this->service->uploadFile($id, $request->file('file')), 'File uploaded successfully', 201); }
    public function files(Conversation $id) { return ApiResponse::success($this->service->files($id), 'Shared files fetched successfully'); }
    public function sendInvoice(Conversation $id, Request $request) { $request->validate(['invoice_id' => 'required|exists:invoices,id']); return ApiResponse::success($this->service->sendInvoice($id, Invoice::findOrFail($request->integer('invoice_id'))), 'Invoice sent successfully'); }
}
