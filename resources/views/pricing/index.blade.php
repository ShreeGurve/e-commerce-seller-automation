@extends('layouts.app')
@section('title', 'Pricing')
@section('subtitle', 'Protect margins, understand channel costs and make confident pricing decisions.')

@section('content')
    @php
        $inr = fn($p) => $p === null ? '—' : '₹' . number_format($p / 100, 0);
        $inr2 = fn($p) => $p === null ? '—' : '₹' . number_format($p / 100, 2);

        $badge = [
            'LOSS' => ['danger', 'Losing money'],
            'NO_BREAK_EVEN' => ['dark', 'Fees too high'],
            'BELOW_MIN' => ['warning', 'Below minimum'],
            'BELOW_TARGET' => ['info', 'Below target'],
            'NO_PRICE' => ['secondary', 'No price set'],
            'OK' => ['success', 'Healthy'],
        ];

        $totalProducts = $counts->sum();
        $healthyProducts = $counts['OK'] ?? 0;
        $riskProducts = ($counts['LOSS'] ?? 0) + ($counts['NO_BREAK_EVEN'] ?? 0) + ($counts['BELOW_MIN'] ?? 0);
        $attentionProducts = ($counts['BELOW_TARGET'] ?? 0) + ($counts['NO_PRICE'] ?? 0);
        $activeStatus = request('status');
    @endphp

    <style>
        .pricing-page { --pr-blue:#386ff4; --pr-ink:#17243b; --pr-muted:#718096; --pr-line:#e7ebf2; color:var(--pr-ink); }
        .pricing-page .pr-hero { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; margin-bottom:1.25rem; }
        .pricing-page .pr-kicker { color:var(--pr-blue); font-size:.72rem; font-weight:800; letter-spacing:.11em; text-transform:uppercase; }
        .pricing-page .pr-heading { font-size:1.45rem; font-weight:800; letter-spacing:-.035em; margin:.2rem 0 .25rem; }
        .pricing-page .pr-description { color:var(--pr-muted); margin:0; font-size:.92rem; }
        .pricing-page .pr-action { border-radius:.7rem; padding:.65rem .95rem; font-weight:700; white-space:nowrap; }
        .pricing-page .pr-stat { border:1px solid var(--pr-line); border-radius:1rem; background:#fff; padding:1rem 1.1rem; height:100%; box-shadow:0 4px 16px rgba(25,42,70,.035); }
        .pricing-page .pr-stat-top { display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
        .pricing-page .pr-stat-label { color:var(--pr-muted); font-size:.77rem; font-weight:700; }
        .pricing-page .pr-stat-value { font-size:1.75rem; line-height:1.2; font-weight:800; letter-spacing:-.04em; margin-top:.55rem; }
        .pricing-page .pr-stat-note { color:var(--pr-muted); font-size:.72rem; margin-top:.2rem; }
        .pricing-page .pr-stat-icon { width:36px; height:36px; display:grid; place-items:center; border-radius:.7rem; font-size:1rem; background:#eef3ff; color:var(--pr-blue); }
        .pricing-page .pr-panel { background:#fff; border:1px solid var(--pr-line); border-radius:1rem; box-shadow:0 4px 16px rgba(25,42,70,.035); overflow:hidden; }
        .pricing-page .pr-toolbar { padding:1rem 1.1rem; border-bottom:1px solid var(--pr-line); }
        .pricing-page .pr-toolbar-title { font-weight:800; font-size:.98rem; margin:0; }
        .pricing-page .pr-toolbar-sub { color:var(--pr-muted); font-size:.78rem; margin:.2rem 0 0; }
        .pricing-page .form-control, .pricing-page .form-select { min-height:42px; border-color:#e1e6ef; border-radius:.65rem; }
        .pricing-page .form-control:focus, .pricing-page .form-select:focus { border-color:#8eacff; box-shadow:0 0 0 .2rem rgba(56,111,244,.1); }
        .pricing-page .pr-search-btn { min-height:42px; border-radius:.65rem; font-weight:700; padding:0 1.1rem; }
        .pricing-page .pr-filter { display:inline-flex; align-items:center; gap:.4rem; border:1px solid var(--pr-line); border-radius:999px; padding:.38rem .7rem; color:#586579; text-decoration:none; font-size:.75rem; font-weight:700; background:#fff; }
        .pricing-page .pr-filter:hover { border-color:#b7c9ff; color:var(--pr-blue); }
        .pricing-page .pr-filter.active { background:#17243b; color:#fff; border-color:#17243b; }
        .pricing-page .pr-filter .pr-count { opacity:.75; font-variant-numeric:tabular-nums; }
        .pricing-page .pr-table { margin:0; min-width:1160px; }
        .pricing-page .pr-table thead th { background:#f8faff; color:#7b8799; font-size:.66rem; letter-spacing:.07em; font-weight:800; text-transform:uppercase; white-space:nowrap; padding:.85rem .75rem; border-bottom:1px solid var(--pr-line); }
        .pricing-page .pr-table tbody td { padding:.85rem .75rem; border-color:#edf0f5; font-size:.8rem; white-space:nowrap; }
        .pricing-page .pr-table tbody tr:hover { background:#fafcff; }
        .pricing-page .pr-sku { color:#24344f; text-decoration:none; font-weight:800; }
        .pricing-page .pr-sku:hover { color:var(--pr-blue); }
        .pricing-page .pr-product-name { color:var(--pr-muted); font-size:.73rem; margin-top:.2rem; max-width:190px; overflow:hidden; text-overflow:ellipsis; }
        .pricing-page .pr-price { font-weight:800; color:#1d2b43; font-variant-numeric:tabular-nums; }
        .pricing-page .pr-muted { color:#8a95a6; }
        .pricing-page .pr-status { border-radius:999px; padding:.38rem .6rem; font-size:.68rem; font-weight:800; }
        .pricing-page .pr-status.text-bg-success { background:#e8f7ef!important;color:#16834a!important; }
        .pricing-page .pr-status.text-bg-danger { background:#fff0ef!important;color:#c63f38!important; }
        .pricing-page .pr-status.text-bg-warning { background:#fff6df!important;color:#946200!important; }
        .pricing-page .pr-status.text-bg-info { background:#eaf4ff!important;color:#2865a9!important; }
        .pricing-page .pr-status.text-bg-dark { background:#edf0f4!important;color:#455064!important; }
        .pricing-page .pr-status.text-bg-secondary { background:#f0f2f5!important;color:#687386!important; }
        .pricing-page .pr-footnote { padding:.85rem 1.1rem; color:var(--pr-muted); font-size:.74rem; line-height:1.55; background:#fbfcfe; }
        .pricing-page .pr-empty { padding:3.5rem 1rem!important; text-align:center; white-space:normal!important; }
        .pricing-page .pr-empty-icon { width:48px;height:48px;border-radius:15px;background:#f0f4ff;color:var(--pr-blue);display:grid;place-items:center;margin:0 auto .8rem;font-size:1.2rem; }
        .pricing-page .pr-empty-title { font-weight:800;color:#34435b;margin-bottom:.25rem; }
        .pricing-page .pr-empty-copy { color:var(--pr-muted);font-size:.8rem; }
        .pricing-page .alert { border-radius:.8rem; }
        @media(max-width:767.98px) {
            .pricing-page .pr-hero { align-items:flex-start; flex-direction:column; }
            .pricing-page .pr-heading { font-size:1.3rem; }
            .pricing-page .pr-stat { padding:.85rem; }
            .pricing-page .pr-stat-value { font-size:1.45rem; }
            .pricing-page .pr-toolbar { padding:.85rem; }
        }
    </style>

    <div class="pricing-page">
        <div class="pr-hero">
            <div>
                <div class="pr-kicker">Revenue intelligence</div>
                <h2 class="pr-heading">Pricing overview</h2>
                <p class="pr-description">Review product economics and spot pricing risks before they affect your margins.</p>
            </div>
            <a href="{{ route('pricing-recommendations.index') }}" class="btn btn-primary pr-action">
                <i class="bi bi-lightning-charge me-1"></i> Recommendations &amp; approvals
            </a>
        </div>

        @if ($noFees)
            <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>No fee rules match {{ $mp->name }} yet, so fees are treated as ₹0. <a class="alert-link" href="{{ route('fees.index') }}">Configure marketplace fee rules</a> for more accurate recommendations.</div>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="pr-stat">
                    <div class="pr-stat-top"><span class="pr-stat-label">Products analysed</span><span class="pr-stat-icon"><i class="bi bi-box-seam"></i></span></div>
                    <div class="pr-stat-value">{{ number_format($totalProducts) }}</div>
                    <div class="pr-stat-note">In {{ $mp->name }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="pr-stat">
                    <div class="pr-stat-top"><span class="pr-stat-label">Healthy pricing</span><span class="pr-stat-icon" style="background:#eaf8f0;color:#16834a"><i class="bi bi-shield-check"></i></span></div>
                    <div class="pr-stat-value text-success">{{ number_format($healthyProducts) }}</div>
                    <div class="pr-stat-note">Meeting target pricing status</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="pr-stat">
                    <div class="pr-stat-top"><span class="pr-stat-label">Needs attention</span><span class="pr-stat-icon" style="background:#fff5e6;color:#b87508"><i class="bi bi-exclamation-circle"></i></span></div>
                    <div class="pr-stat-value" style="color:#b87508">{{ number_format($attentionProducts) }}</div>
                    <div class="pr-stat-note">Below target or missing price</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="pr-stat">
                    <div class="pr-stat-top"><span class="pr-stat-label">Pricing at risk</span><span class="pr-stat-icon" style="background:#fff0ef;color:#c63f38"><i class="bi bi-graph-down-arrow"></i></span></div>
                    <div class="pr-stat-value text-danger">{{ number_format($riskProducts) }}</div>
                    <div class="pr-stat-note">Loss, fee or minimum-price issue</div>
                </div>
            </div>
        </div>

        <div class="pr-panel mb-3">
            <div class="pr-toolbar">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h3 class="pr-toolbar-title">Product price analysis</h3>
                        <p class="pr-toolbar-sub">Compare current price, cost structure and margin health by product.</p>
                    </div>
                    <span class="small text-muted"><i class="bi bi-shop me-1"></i>{{ $mp->name }}</span>
                </div>
                <form class="row g-2 align-items-center" method="GET">
                    <div class="col-12 col-md-3">
                        <select name="marketplace_id" class="form-select" aria-label="Marketplace" onchange="this.form.submit()">
                            @foreach ($marketplaces as $m)
                                <option value="{{ $m->id }}" @selected($mp->id === $m->id)>{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by SKU or product name">
                    </div>
                    <div class="col-12 col-md-auto">
                        <button class="btn btn-outline-primary pr-search-btn"><i class="bi bi-search me-1"></i>Search</button>
                    </div>
                    <div class="col-12 col-md">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                            <a href="{{ route('pricing.index', ['marketplace_id' => $mp->id, 'q' => request('q')]) }}" class="pr-filter {{ !$activeStatus ? 'active' : '' }}">All <span class="pr-count">{{ $totalProducts }}</span></a>
                            @foreach ($badge as $k => [$c, $l])
                                @if (($counts[$k] ?? 0) > 0)
                                    <a href="{{ route('pricing.index', ['marketplace_id' => $mp->id, 'status' => $k, 'q' => request('q')]) }}" class="pr-filter {{ $activeStatus === $k ? 'active' : '' }}">
                                        {{ $l }} <span class="pr-count">{{ $counts[$k] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle pr-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Price now</th>
                            <th class="text-end">Unit cost</th>
                            <th class="text-end">Fees</th>
                            <th class="text-end">Ads / unit</th>
                            <th class="text-end">Returns / unit</th>
                            <th class="text-end">Break-even</th>
                            <th class="text-end">Minimum</th>
                            <th class="text-end">Target</th>
                            <th class="text-end">Margin now</th>
                            <th class="text-end">Max discount</th>
                            <th class="text-end">Vs competitor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            @php
                                [$c, $l] = $badge[$r['status']] ?? ['secondary', $r['status']];
                                $x = $r['at'];
                            @endphp
                            <tr>
                                <td>
                                    <a class="pr-sku" href="{{ route('pricing.show', [$r['product'], $mp]) }}">{{ $r['product']->sku }}</a>
                                    <div class="pr-product-name" title="{{ $r['product']->name }}">{{ $r['product']->name }}</div>
                                </td>
                                <td class="text-end pr-price">{{ $inr($r['current']) }}</td>
                                <td class="text-end">{{ $inr2($r['unit_cost']) }}</td>
                                <td class="text-end">{{ $x ? $inr2($x['fees']) : '—' }}</td>
                                <td class="text-end">{{ $r['history_units'] ? $inr2($r['ads_per_unit']) : '—' }}</td>
                                <td class="text-end">{{ $r['history_units'] ? $inr2($r['return_per_unit']) : '—' }}</td>
                                <td class="text-end">{{ $inr($r['break_even']) }}</td>
                                <td class="text-end">{{ $inr($r['min_price']) }}</td>
                                <td class="text-end fw-semibold">{{ $inr($r['target_price']) }}</td>
                                <td class="text-end fw-bold {{ $x && $x['margin'] < 0 ? 'text-danger' : ($x && $x['margin'] >= 0 ? 'text-success' : '') }}">
                                    {{ $x && $x['margin'] !== null ? round($x['margin'], 1) . '%' : '—' }}
                                </td>
                                <td class="text-end">{{ $r['max_discount'] !== null ? $r['max_discount'] . '%' : '—' }}</td>
                                <td class="text-end">
                                    @if ($r['vs_competitor'] !== null)
                                        <span class="{{ $r['vs_competitor'] > 0 ? 'text-danger' : 'text-success' }}">{{ $r['vs_competitor'] > 0 ? '+' : '' }}{{ $r['vs_competitor'] }}%</span>
                                    @else
                                        <span class="pr-muted">—</span>
                                    @endif
                                </td>
                                <td><span class="badge pr-status text-bg-{{ $c }}">{{ $l }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="pr-empty">
                                    <div class="pr-empty-icon"><i class="bi bi-tags"></i></div>
                                    <div class="pr-empty-title">No products to display</div>
                                    <div class="pr-empty-copy">Try another search or check that active products are available for this marketplace.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="p-3 border-top">{{ $rows->links() }}</div>
            @endif
            <div class="pr-footnote">
                <i class="bi bi-info-circle me-1"></i>
                Prices include GST and are rounded up to the next rupee. Break-even represents ₹0 profit. Minimum and target prices use channel-specific desired profit amounts when configured; otherwise they use the margins in Settings. Fees come from your fee rules (including GST on fees). Ads and returns per unit are averages of recent orders using the configured look-back period. “—” means no order history is available and the calculation assumes ₹0.
                @if ($capped) <strong>Showing the first 300 products.</strong> @endif
            </div>
        </div>
    </div>
@endsection
