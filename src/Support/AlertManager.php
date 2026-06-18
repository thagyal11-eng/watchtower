<?php

namespace Watchtower\Support;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Watchtower\Models\JobRecord;
use Watchtower\Notifications\WatchtowerAlert;

/**
 * Decides when to fire alerts and dispatches them to the configured channels.
 * Everything here is gated behind alerts.enabled (off by default).
 */
class AlertManager
{
    public function __construct(protected ScheduleInspector $inspector)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('watchtower.alerts.enabled', false);
    }

    /**
     * Run every configured check and send any triggered alerts. Returns the
     * number of alerts sent (used by the monitor command + tests).
     */
    public function run(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $sent = 0;
        $on = (array) config('watchtower.alerts.on', []);

        if ($on['schedule_missed'] ?? true) {
            $sent += $this->checkMissedSchedules();
        }
        if ($on['schedule_failed'] ?? true) {
            $sent += $this->checkFailingSchedules();
        }
        if ($on['failed_jobs_threshold'] ?? true) {
            $sent += $this->checkFailedJobsThreshold();
        }

        return $sent;
    }

    protected function checkMissedSchedules(): int
    {
        $missed = $this->inspector->tasks()->where('missed', true);

        if ($missed->isEmpty()) {
            return 0;
        }

        $this->notify(new WatchtowerAlert(
            'Scheduled task(s) missed',
            $missed->count().' scheduled task(s) have not run as expected. The scheduler may have stopped.',
            ['tasks' => $missed->pluck('command')->all()],
        ));

        return 1;
    }

    protected function checkFailingSchedules(): int
    {
        $failing = $this->inspector->tasks()->where('last_status', 'failed');

        if ($failing->isEmpty()) {
            return 0;
        }

        $this->notify(new WatchtowerAlert(
            'Scheduled task(s) failing',
            $failing->count().' scheduled task(s) failed on their last run.',
            ['tasks' => $failing->pluck('command')->all()],
        ));

        return 1;
    }

    protected function checkFailedJobsThreshold(): int
    {
        $threshold = (int) config('watchtower.alerts.failed_jobs.threshold', 25);
        $window = (int) config('watchtower.alerts.failed_jobs.window_minutes', 60);

        $count = JobRecord::query()
            ->where('status', 'failed')
            ->where('finished_at', '>=', Carbon::now()->subMinutes($window))
            ->count();

        if ($count < $threshold) {
            return 0;
        }

        $this->notify(new WatchtowerAlert(
            'Failed-job threshold crossed',
            "{$count} jobs failed in the last {$window} minute(s) (threshold {$threshold}).",
            ['count' => $count, 'window_minutes' => $window],
        ));

        return 1;
    }

    public function notify(WatchtowerAlert $alert): void
    {
        $notifiable = new AnonymousNotifiable;

        $mail = array_filter((array) config('watchtower.alerts.channels.mail', []));
        if (! empty($mail)) {
            $notifiable->route('mail', $mail);
        }

        $notifiable->notify($alert);
    }
}
