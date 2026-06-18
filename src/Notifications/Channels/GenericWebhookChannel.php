<?php

namespace Watchtower\Notifications\Channels;

use Illuminate\Support\Facades\Http;
use Watchtower\Notifications\WatchtowerAlert;

/**
 * Posts alerts as JSON to a generic webhook endpoint.
 */
class GenericWebhookChannel
{
    public function send($notifiable, WatchtowerAlert $notification): void
    {
        $url = config('watchtower.alerts.channels.webhook');

        if (! $url) {
            return;
        }

        Http::asJson()->post($url, $notification->toWebhook());
    }
}
