<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketplaceWriteEmergencyController extends Controller
{
    public function activate(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only a Super Admin can activate the emergency stop.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $businessId = (int) $user->business_id;
        abort_unless($businessId, 403, 'No business is associated with this account.');

        DB::transaction(function () use ($businessId, $user, $data) {
            $current = DB::table('marketplace_write_emergency_controls')->where('business_id', $businessId)->lockForUpdate()->first();
            if ($current && $current->is_active) {
                throw ValidationException::withMessages(['emergency' => 'The emergency stop is already active.']);
            }

            $values = [
                'is_active' => true, 'reason' => $data['reason'], 'activated_by' => $user->id,
                'activated_at' => now(), 'released_by' => null, 'released_at' => null, 'updated_at' => now(),
            ];
            if ($current) {
                DB::table('marketplace_write_emergency_controls')->where('business_id', $businessId)->update($values);
            } else {
                DB::table('marketplace_write_emergency_controls')->insert($values + ['business_id' => $businessId, 'created_at' => now()]);
            }
            DB::table('marketplace_write_governance_events')->insert([
                'business_id' => $businessId, 'actor_id' => $user->id, 'request_id' => null,
                'event_type' => 'EMERGENCY_STOP_ACTIVATED', 'details' => $data['reason'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('marketplaces.writes.governance.index')->with('success', 'Emergency stop activated. Live marketplace submissions are blocked for this business.');
    }

    public function release(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Only a Super Admin can release the emergency stop.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $businessId = (int) $user->business_id;
        abort_unless($businessId, 403, 'No business is associated with this account.');

        DB::transaction(function () use ($businessId, $user, $data) {
            $control = DB::table('marketplace_write_emergency_controls')->where('business_id', $businessId)->lockForUpdate()->first();
            if (! $control || ! $control->is_active) {
                throw ValidationException::withMessages(['emergency' => 'There is no active emergency stop to release.']);
            }
            if ((int) $control->activated_by === (int) $user->id) {
                throw ValidationException::withMessages(['emergency' => 'A different Super Admin must release the emergency stop.']);
            }

            DB::table('marketplace_write_emergency_controls')->where('business_id', $businessId)->update([
                'is_active' => false, 'released_by' => $user->id, 'released_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('marketplace_write_governance_events')->insert([
                'business_id' => $businessId, 'actor_id' => $user->id, 'request_id' => null,
                'event_type' => 'EMERGENCY_STOP_RELEASED', 'details' => $data['reason'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('marketplaces.writes.governance.index')->with('success', 'Emergency stop released by an independent Super Admin.');
    }
}
