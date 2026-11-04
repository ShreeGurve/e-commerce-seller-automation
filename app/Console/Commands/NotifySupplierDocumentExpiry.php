<?php

namespace App\Console\Commands;

use App\Models\SupplierDocument;
use App\Models\User;
use App\Notifications\SupplierDocumentExpiryNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifySupplierDocumentExpiry extends Command
{
    protected $signature = 'suppliers:documents-expiry-notify {--days=30 : Upcoming expiry window in days}';
    protected $description = 'Create internal notifications for expired and soon-to-expire supplier documents';

    public function handle(): int
    {
        $days = max(1, min(365, (int) $this->option('days')));
        $today = today();
        $documents = SupplierDocument::with('supplier:id,name')
            ->whereNotNull('expires_at')
            ->whereIn('status', ['PENDING', 'APPROVED'])
            ->whereDate('expires_at', '<=', $today->copy()->addDays($days)->toDateString())
            ->orderBy('expires_at')
            ->limit(1000)
            ->get();

        $recipients = User::query()->where('is_active', true)->get()
            ->filter(fn (User $user) => $user->isSuperAdmin()
                || $user->hasPermission('purchases.view')
                || $user->hasPermission('automation.view'));

        $created = 0;
        foreach ($documents as $document) {
            $expired = $document->expires_at->lt($today);
            $fingerprint = hash('sha256', implode('|', [
                'supplier-document-expiry', $document->id,
                $document->expires_at->toDateString(), $expired ? 'expired' : 'upcoming',
            ]));

            // A notification fingerprint prevents repeat reminders on every scheduled run.
            $alreadySent = DB::table('notifications')
                ->where('type', SupplierDocumentExpiryNotification::class)
                ->where('data->fingerprint', $fingerprint)
                ->exists();
            if ($alreadySent) {
                continue;
            }

            $daysLeft = $today->diffInDays($document->expires_at, false);
            $title = $expired ? 'Supplier document expired' : 'Supplier document expiring soon';
            $message = ($document->supplier->name ?? 'Supplier') . ' — ' . $document->title
                . ($expired
                    ? ' expired on ' . $document->expires_at->format('d M Y') . '.'
                    : ' expires in ' . $daysLeft . ' day(s), on ' . $document->expires_at->format('d M Y') . '.');

            foreach ($recipients as $recipient) {
                $recipient->notify(new SupplierDocumentExpiryNotification([
                    'fingerprint' => $fingerprint,
                    'title' => $title,
                    'message' => $message,
                    'url' => route('supplier-documents.index', ['supplier_id' => $document->supplier_id]),
                    'severity' => $expired ? 'critical' : ($daysLeft <= 7 ? 'high' : 'medium'),
                    'supplier_document_id' => $document->id,
                    'supplier_id' => $document->supplier_id,
                    'expires_at' => $document->expires_at->toDateString(),
                ]));
                $created++;
            }
        }

        $this->info("Checked {$documents->count()} supplier document(s); created {$created} internal notification(s).");
        return self::SUCCESS;
    }
}
