<?php

namespace App\Http\Controllers;

use App\Models\Marketplace;
use App\Models\MarketplaceConnection;
use App\Services\MarketplaceConnectionVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MarketplaceConnectionController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;
        $connections = MarketplaceConnection::with('marketplace')
            ->where('business_id', $businessId)->latest()->get();
        // Keep platform definitions available so the same marketplace can have
        // separate Sandbox and Production connections. The view decides which
        // environment buttons are still available.
        $availablePlatforms = Marketplace::active()->get();

        return view('marketplaces.connections', compact('connections', 'availablePlatforms'));
    }

    public function create(Request $request, Marketplace $marketplace)
    {
        abort_unless($marketplace->is_active, 404);
        abort_unless(in_array($marketplace->code, ['amazon', 'flipkart'], true), 422, 'A verification adapter is not available for this platform yet.');

        $environment = $request->query('environment', 'production');
        abort_unless($marketplace->code === 'amazon' || $environment === 'production', 422, 'Sandbox mode is currently available for Amazon only.');
        abort_unless(in_array($environment, ['sandbox', 'production'], true), 422);

        return view('marketplaces.connection-form', compact('marketplace', 'environment'));
    }

    public function verify(Request $request, Marketplace $marketplace, MarketplaceConnectionVerifier $verifier)
    {
        abort_unless($marketplace->is_active, 404);

        $environment = $request->input('environment', 'production');
        abort_unless(in_array($environment, ['sandbox', 'production'], true), 422);
        abort_unless($marketplace->code === 'amazon' || $environment === 'production', 422, 'Sandbox mode is currently available for Amazon only.');

        $rules = $marketplace->code === 'amazon'
            ? ['account_label' => 'required|string|max:100', 'environment' => 'required|in:sandbox,production', 'client_id' => 'required|string|max:255', 'client_secret' => 'required|string|max:1000', 'refresh_token' => 'required|string|max:2000', 'marketplace_id' => 'required|string|max:50', 'region' => 'required|in:na,eu,fe']
            : ['account_label' => 'required|string|max:100', 'environment' => 'required|in:production', 'app_id' => 'required|string|max:255', 'app_secret' => 'required|string|max:1000', 'seller_id' => 'nullable|string|max:100'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput(['account_label' => $request->input('account_label'), 'environment' => $environment]);
        }
        $data = $validator->validated();

        $credentials = collect($data)->except(['account_label', 'environment'])->all();
        $result = $verifier->verify($marketplace->code, $credentials, $environment);

        if (!$result['ok']) {
            // Deliberately do not persist failed credentials or replace an existing connection.
            return back()->withErrors(['credentials' => $result['message']])->withInput(['account_label' => $data['account_label'], 'environment' => $environment]);
        }

        DB::transaction(function () use ($request, $marketplace, $data, $credentials, $result) {
            MarketplaceConnection::updateOrCreate(
                ['business_id' => $request->user()->business_id, 'marketplace_id' => $marketplace->id, 'environment' => $environment],
                [
                    'environment' => $environment,
                    'account_label' => $data['account_label'],
                    'credentials' => $credentials,
                    'status' => 'connected',
                    'verified_at' => now(),
                    'last_verified_at' => now(),
                    'last_verified_message' => $result['message'],
                ]
            );
        });

        return redirect()->route('marketplaces.connections')->with('success', $marketplace->name.' '.ucfirst($environment).' credentials verified and saved securely.');
    }

    public function destroy(Request $request, MarketplaceConnection $connection)
    {
        abort_unless($connection->business_id === $request->user()->business_id, 404);
        $connection->delete();
        return redirect()->route('marketplaces.connections')->with('success', 'Marketplace connection removed.');
    }
}
