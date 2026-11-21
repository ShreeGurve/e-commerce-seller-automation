<?php

namespace App\Http\Controllers;

use App\Models\BusinessAutomationEvent;
use App\Models\BusinessAutomationRule;
use App\Models\BusinessKpiTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessAlertIntelligenceController extends Controller
{
    private const METRICS = [
        'sales_value' => ['label' => 'Order sales value', 'unit' => '₹', 'action' => 'Review current sales pace, promotion performance and channel mix.'],
        'orders' => ['label' => 'Non-test orders', 'unit' => '', 'action' => 'Review listing availability, traffic and conversion for priority products.'],
        'fulfilled_orders' => ['label' => 'Delivered / closed orders', 'unit' => '', 'action' => 'Review dispatch backlog, carrier exceptions and delivery delays.'],
        'approved_requisitions' => ['label' => 'Approved / awarded requisitions', 'unit' => '', 'action' => 'Review pending procurement approvals and supplier quote coverage.'],
        'supplier_payments' => ['label' => 'Confirmed supplier payments', 'unit' => '₹', 'action' => 'Review due supplier invoices and payment scheduling.'],
    ];

    private function actuals(string $start, string $end): array
    {
        $orders = Schema::hasTable('orders')
            ? DB::table('orders')->whereBetween('order_date', [$start.' 00:00:00', $end.' 23:59:59'])
                ->where('source', '<>', 'TEST')->whereNull('deleted_at')
            : null;
        $salesStatuses = ['CONFIRMED', 'PACKED', 'SHIPPED', 'DELIVERED', 'CLOSED'];
        $actuals = ['sales_value' => 0.0, 'orders' => 0, 'fulfilled_orders' => 0, 'approved_requisitions' => 0, 'supplier_payments' => 0.0];
        if ($orders) {
            $actuals['sales_value'] = (float) (clone $orders)->whereIn('status', $salesStatuses)->sum('total_amount');
            $actuals['orders'] = (int) (clone $orders)->whereIn('status', $salesStatuses)->count();
            $actuals['fulfilled_orders'] = (int) (clone $orders)->whereIn('status', ['DELIVERED', 'CLOSED'])->count();
        }
        if (Schema::hasTable('procurement_requisitions')) {
            $actuals['approved_requisitions'] = (int) DB::table('procurement_requisitions')
                ->whereBetween('approved_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->whereIn('status', ['APPROVED', 'AWARDED'])->count();
        }
        if (Schema::hasTable('supplier_invoice_payments')) {
            $actuals['supplier_payments'] = (float) DB::table('supplier_invoice_payments')
                ->whereBetween('payment_date', [$start, $end])->where('status', 'CONFIRMED')->sum('amount');
        }

        return $actuals;
    }

    private function period(Request $request): array
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month) || ! checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))) {
            $month = now()->format('Y-m');
        }
        $start = $month.'-01';

        return [$month, $start, date('Y-m-t', strtotime($start))];
    }

    // Package 60: compare saved monthly KPI targets with actuals and create deduplicated alerts.
    public function evaluateKpis(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        if (! Schema::hasTable('business_kpi_targets') || ! Schema::hasTable('business_automation_events') || ! Schema::hasTable('business_automation_rules')) {
            return back()->withErrors(['alerts' => 'Required KPI or automation tables are missing. Run the project migrations first.']);
        }
        $threshold = 90.0;
        $rule = BusinessAutomationRule::updateOrCreate(
            ['rule_key' => 'kpi_below_target'],
            ['name' => 'KPI below target', 'description' => 'Create an internal alert when a monthly KPI is below the configured attainment threshold.', 'threshold_value' => $threshold, 'is_enabled' => true]
        );
        [$month, $start, $end] = [$data['month'], $data['month'].'-01', date('Y-m-t', strtotime($data['month'].'-01'))];
        $actuals = $this->actuals($start, $end);
        $targets = BusinessKpiTarget::whereDate('target_month', $start)->get();
        $created = 0;
        foreach ($targets as $target) {
            if (! isset(self::METRICS[$target->metric_key])) {
                continue;
            }
            $targetValue = (float) $target->target_value;
            if ($targetValue <= 0) {
                continue;
            }
            $actual = (float) ($actuals[$target->metric_key] ?? 0);
            $attainment = ($actual / $targetValue) * 100;
            if ($attainment >= $threshold) {
                continue;
            }
            $meta = self::METRICS[$target->metric_key];
            $fingerprint = 'kpi-target:'.$target->id.':'.$month;
            $event = BusinessAutomationEvent::firstOrCreate(
                ['event_fingerprint' => $fingerprint],
                [
                    'rule_id' => $rule->id, 'event_key' => 'KPI_BELOW_TARGET',
                    'severity' => $attainment < 70 ? 'HIGH' : 'WARNING',
                    'title' => $meta['label'].' below target',
                    'message' => sprintf('%s actual: %s%s; target: %s%s; attainment: %.1f%% for %s.', $meta['label'], $meta['unit'], number_format($actual, 2), $meta['unit'], number_format($targetValue, 2), $attainment, $month),
                    'source_type' => 'business_kpi_targets', 'source_id' => $target->id,
                    'status' => 'OPEN', 'created_by' => $request->user()->id,
                ]
            );
            if ($event->wasRecentlyCreated) {
                $created++;
            }
        }
        $rule->update(['last_evaluated_at' => now()]);

        return redirect()->route('business-alerts.kpi-monitor', ['month' => $month])
            ->with('success', "KPI evaluation completed. {$created} new alert(s) created at the 90% target threshold.");
    }

    public function kpiMonitor(Request $request)
    {
        [$month, $start, $end] = $this->period($request);
        $actuals = $this->actuals($start, $end);
        $targets = Schema::hasTable('business_kpi_targets')
            ? BusinessKpiTarget::whereDate('target_month', $start)->get()->keyBy('metric_key')
            : collect();
        $rows = [];
        foreach (self::METRICS as $key => $meta) {
            $target = (float) ($targets[$key]->target_value ?? 0);
            $actual = (float) $actuals[$key];
            $rows[] = (object) ['key' => $key, 'label' => $meta['label'], 'unit' => $meta['unit'], 'actual' => $actual, 'target' => $target, 'attainment' => $target > 0 ? round(($actual / $target) * 100, 1) : null];
        }
        $events = Schema::hasTable('business_automation_events')
            ? BusinessAutomationEvent::where('event_key', 'KPI_BELOW_TARGET')->where('event_fingerprint', 'like', 'kpi-target:%:'.$month)->latest()->get()
            : collect();

        return view('business-alerts.kpi-monitor', compact('month', 'rows', 'events'));
    }

    // Package 61: turn alert types into practical, non-executing next-step guidance.
    public function recommendations(Request $request)
    {
        $status = $request->query('status', 'OPEN');
        if (! in_array($status, ['OPEN', 'RESOLVED', 'ALL'], true)) {
            $status = 'OPEN';
        }
        $query = BusinessAutomationEvent::query()->with('rule')->latest();
        if ($status !== 'ALL') {
            $query->where('status', $status);
        }
        $events = $query->paginate(25)->withQueryString();
        $guidance = [
            'KPI_BELOW_TARGET' => 'Compare current month progress with the remaining working days. Assign an owner and agree a recovery action.',
            'LOW_STOCK' => 'Check physical stock and reserved quantities before approving a replenishment decision.',
            'OVERDUE_REQUISITION' => 'Confirm the business need, required-by date and approval owner; contact suppliers only after internal approval.',
            'INVOICE_REVIEW' => 'Compare supplier invoice lines with the purchase order and receiving evidence before approving payment.',
        ];

        return view('business-alerts.recommendations', compact('events', 'status', 'guidance'));
    }

    // Package 62: filterable alert register and CSV digest; no emails or external notifications are sent.
    public function digest(Request $request): StreamedResponse
    {
        $status = $request->query('status', 'OPEN');
        $severity = $request->query('severity', 'ALL');
        $query = BusinessAutomationEvent::query()->with('rule')->orderByDesc('created_at');
        if (in_array($status, ['OPEN', 'RESOLVED'], true)) {
            $query->where('status', $status);
        }
        if (in_array($severity, ['HIGH', 'WARNING', 'INFO'], true)) {
            $query->where('severity', $severity);
        }
        $filename = 'business-alert-digest-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Created at', 'Status', 'Severity', 'Rule', 'Alert', 'Details', 'Source type', 'Source ID']);
            $query->chunk(300, function ($events) use ($out) {
                foreach ($events as $event) {
                    fputcsv($out, [$event->created_at, $event->status, $event->severity, optional($event->rule)->name, $event->title, $event->message, $event->source_type, $event->source_id]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
