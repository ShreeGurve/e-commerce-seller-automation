<?php

namespace App\Http\Controllers;

use App\Models\BusinessAutomationEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AutomationOperationsController extends Controller
{
    public function tasks(Request $request)
    {
        $user = $request->user();
        $events = BusinessAutomationEvent::with(['assignee', 'creator', 'rule'])
            ->where('status', 'OPEN')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereIn('created_by', User::where('business_id', $user->business_id)->select('id')))
            ->when($request->filled('assignment'), function ($q) use ($request, $user) {
                if ($request->assignment === 'unassigned') {
                    $q->whereNull('assigned_to');
                }
                if ($request->assignment === 'assigned') {
                    $q->whereNotNull('assigned_to');
                }
                if ($request->assignment === 'mine') {
                    $q->where('assigned_to', $user->id);
                }
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->latest()->paginate(20)->withQueryString();
        $users = User::query()->when(! $user->isSuperAdmin(), fn ($q) => $q->where('business_id', $user->business_id))->orderBy('name')->get(['id', 'name']);

        return view('automation.tasks', compact('events', 'users'));
    }

    public function storeFollowup(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:4000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date', 'after_or_equal:now'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);
        if (! empty($data['assigned_to']) && ! $user->isSuperAdmin()) {
            abort_unless(User::where('id', $data['assigned_to'])->where('business_id', $user->business_id)->exists(), 422, 'Assignee must belong to your business.');
        }
        BusinessAutomationEvent::create([
            'rule_id' => null,
            'event_key' => 'manual_followup',
            'severity' => strtoupper($data['priority'] === 'urgent' ? 'critical' : ($data['priority'] === 'high' ? 'warning' : 'info')),
            'title' => $data['title'],
            'message' => $data['message'],
            'source_type' => 'manual_followup',
            'source_id' => null,
            'event_fingerprint' => 'manual-followup-'.Str::uuid(),
            'status' => 'OPEN',
            'created_by' => $user->id,
            'assigned_to' => $data['assigned_to'] ?? null,
            'assigned_at' => ! empty($data['assigned_to']) ? now() : null,
            'due_at' => $data['due_at'] ?? null,
            'priority' => $data['priority'],
        ]);

        return redirect()->route('automation.tasks.index')->with('success', 'Follow-up task added to the automation work queue.');
    }

    public function priorities(Request $request)
    {
        $user = $request->user();
        $events = BusinessAutomationEvent::with(['assignee', 'creator', 'rule'])
            ->where('status', 'OPEN')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereIn('created_by', User::where('business_id', $user->business_id)->select('id')))
            ->latest()->limit(300)->get()->map(function ($event) {
                $score = match (strtolower((string) $event->severity)) {
                    'critical' => 60, 'high' => 45, 'warning' => 30, 'medium' => 25, default => 15,
                };
                $score += match (strtolower((string) $event->priority)) {
                    'urgent' => 25, 'high' => 15, 'normal' => 5, default => 0,
                };
                if ($event->due_at && $event->due_at->isPast()) {
                    $score += 25;
                } elseif ($event->due_at && $event->due_at->lte(now()->addDay())) {
                    $score += 12;
                }
                if (! $event->assigned_to) {
                    $score += 5;
                }
                $event->attention_score = min(100, $score);
                $event->attention_band = $score >= 75 ? 'Urgent attention' : ($score >= 50 ? 'High attention' : 'Normal attention');

                return $event;
            })->sortByDesc('attention_score')->values();

        return view('automation.priorities', compact('events'));
    }

    public function workflowMonitor(Request $request)
    {
        $user = $request->user();
        $base = BusinessAutomationEvent::query()->when(! $user->isSuperAdmin(), fn ($q) => $q->whereIn('created_by', User::where('business_id', $user->business_id)->select('id')));
        $open = (clone $base)->where('status', 'OPEN');
        $summary = [
            'open' => (clone $open)->count(),
            'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
            'due_today' => (clone $open)->whereNotNull('due_at')->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'resolved_30d' => (clone $base)->where('status', 'RESOLVED')->where('resolved_at', '>=', now()->subDays(30))->count(),
        ];
        $events = (clone $open)->with(['assignee', 'creator'])->orderByRaw('CASE WHEN due_at IS NOT NULL AND due_at < ? THEN 0 WHEN due_at IS NOT NULL AND due_at <= ? THEN 1 ELSE 2 END', [now(), now()->addDay()])->orderBy('due_at')->latest()->paginate(20)->withQueryString();

        return view('automation.workflow-monitor', compact('summary', 'events'));
    }
}
