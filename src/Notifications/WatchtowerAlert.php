<?php

namespace Watchtower\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Watchtower\Notifications\Channels\GenericWebhookChannel;
use Watchtower\Notifications\Channels\SlackWebhookChannel;

/**
 * A single alert delivered to whichever channels the user has configured.
 * Implemented as a standard Laravel notification so users can subclass it or
 * add channels of their own.
 */
class WatchtowerAlert extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $context = [],
    ) {
    }

    public function via($notifiable): array
    {
        $channels = [];
        $configured = (array) config('watchtower.alerts.channels', []);

        if (! empty($configured['slack'])) {
            $channels[] = SlackWebhookChannel::class;
        }
        if (! empty($configured['webhook'])) {
            $channels[] = GenericWebhookChannel::class;
        }
        if (! empty($configured['mail'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[Watchtower] '.$this->title)
            ->line($this->body);

        foreach ($this->context as $key => $value) {
            $mail->line(ucfirst((string) $key).': '.(is_scalar($value) ? $value : json_encode($value)));
        }

        return $mail;
    }

    public function toSlack(): array
    {
        return [
            'text' => "*🚨 {$this->title}*\n{$this->body}",
        ];
    }

    public function toWebhook(): array
    {
        return [
            'source' => 'watchtower',
            'title' => $this->title,
            'body' => $this->body,
            'context' => $this->context,
        ];
    }
}
