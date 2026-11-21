<?php

namespace App\Http\Controllers;

use App\Notifications\MarketplaceWriteGovernanceAlert;
use App\Services\MarketplaceWriteGovernanceAlertService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarketplaceWriteGovernanceAlertController extends Controller
{
    public function index(Request $request, MarketplaceWriteGovernanceAlertService $service)
    {
        abort_unless($request->user()->hasPermission('listings.manage'), Response::HTTP_FORBIDDEN);

        return view('marketplaces.write-alerts', $service->inbox($request->user()));
    }

    public function markRead(Request $request, string $notification)
    {
        abort_unless($request->user()->hasPermission('listings.manage'), Response::HTTP_FORBIDDEN);
        abort_unless((bool) preg_match('/^[0-9a-fA-F-]{36}$/', $notification), Response::HTTP_NOT_FOUND);

        $record = $request->user()->notifications()
            ->whereKey($notification)
            ->where('type', MarketplaceWriteGovernanceAlert::class)
            ->firstOrFail();
        $record->markAsRead();

        return redirect()->route('marketplaces.writes.alerts.index')->with('success', 'Alert marked as read.');
    }

    public function markAllRead(Request $request)
    {
        abort_unless($request->user()->hasPermission('listings.manage'), Response::HTTP_FORBIDDEN);
        $request->user()->unreadNotifications()
            ->where('type', MarketplaceWriteGovernanceAlert::class)
            ->update(['read_at' => now()]);

        return redirect()->route('marketplaces.writes.alerts.index')->with('success', 'All marketplace write alerts marked as read.');
    }
}
