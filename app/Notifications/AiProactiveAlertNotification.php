<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification in-app pour les alertes IA proactives critiques.
 * Stockée en base via le channel 'database'.
 */
class AiProactiveAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param array $alert Les données de l'alerte
     */
    public function __construct(private readonly array $alerts) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $titles = array_column($this->alerts, 'title');

        return [
            'title'   => count($this->alerts).' alerte(s) IA critique(s)',
            'message' => implode(' • ', array_slice($titles, 0, 3)),
            'alerts'  => $this->alerts,
            'icon'    => '🤖',
            'url'     => route('dashboard'),
        ];
    }
}
