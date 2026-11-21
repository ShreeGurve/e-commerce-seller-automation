<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MarketplaceWriteGovernanceAlert extends Notification
{
    use Queueable;

    public function __construct(private readonly array $alert)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->alert;
    }
}
