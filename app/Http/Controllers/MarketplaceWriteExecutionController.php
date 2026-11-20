<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceWriteExecutionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MarketplaceWriteExecutionController extends Controller
{
    public function index(Request $request, MarketplaceWriteExecutionService $service)
    {
        return view('marketplaces.write-execution', $service->dashboard((int) $request->user()->business_id));
    }

    public function togglePolicy(Request $request, int $connection, string $operation, MarketplaceWriteExecutionService $service)
    {
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        try {
            $service->setPolicy($connection, $operation, (bool) $data['is_enabled'], $request->user());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['policy' => $e->getMessage()]);
        }
        return back()->with('success', 'Marketplace write policy updated. Environment-level gates remain independently enforced.');
    }

    public function prepare(Request $request, MarketplaceWriteExecutionService $service)
    {
        $data = $request->validate([
            'connection_id' => ['required', 'integer'],
            'task_id' => ['required', 'integer'],
            'operation' => ['required', 'in:PRICE_UPDATE,INVENTORY_UPDATE'],
            'selling_price' => ['nullable', 'numeric', 'gt:0'],
            'mrp' => ['nullable', 'integer', 'gt:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'location_id' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        $attempt = $service->prepare($request->user(), $data);
        return back()->with('success', 'Local preview prepared as attempt #'.$attempt->id.'. No marketplace API call was made. A different user must approve the exact payload before submission.');
    }

    public function approveExecute(Request $request, int $attempt, MarketplaceWriteExecutionService $service)
    {
        $request->validate(['confirm_submit' => ['required', 'accepted']]);
        try {
            $result = $service->approveAndExecute($attempt, $request->user());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['attempt' => $e->getMessage()]);
        }
        $message = $result->error_message ?: 'Marketplace submission status: '.$result->status.'.';
        return back()->with($result->status === 'ACCEPTED' ? 'success' : 'warning', $message);
    }
}
