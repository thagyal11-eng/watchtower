<?php

namespace Watchtower\Notifications\Channels;

use Illuminate\Support\Facades\Http;
use Watchtower\Notifications\WatchtowerAlert;

/**
 * Posts alerts to a Slack incoming-webhook URL. Dependency-free (no Slack
 * notification package required) so it never adds weight to the host app.
 */
class SlackWebhookChannel
{
    public function send($notifiable, WatchtowerAlert $notification): void
    {
        $url = config('watchtower.alerts.channels.slack');

        if (! $url) {
            return;
        }

        Http::asJson()->post($url, $notification->toSlack());
    }
}
