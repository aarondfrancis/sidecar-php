<?php

namespace Hammerstone\Sidecar\PHP\Tests\Support\App\Notifications;

use Facades\Hammerstone\Sidecar\PHP\Tests\Support\App\Mail\FailingMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FailingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return FailingMailable::to($notifiable?->routes['mail'] ?? $notifiable?->email ?? $notifiable?->email);
    }
}
