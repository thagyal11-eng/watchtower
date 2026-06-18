<?php

namespace Watchtower\Commands;

use Illuminate\Console\Command;
use Watchtower\Support\AlertManager;

class MonitorCommand extends Command
{
    protected $signature = 'watchtower:monitor';

    protected $description = 'Run Watchtower alert checks (missed schedules, failing tasks, failed-job threshold)';

    public function handle(AlertManager $alerts): int
    {
        if (! $alerts->enabled()) {
            $this->components->info('Watchtower alerts are disabled. Set watchtower.alerts.enabled to true.');

            return self::SUCCESS;
        }

        $sent = $alerts->run();

        $this->components->info("Watchtower monitor ran. {$sent} alert(s) sent.");

        return self::SUCCESS;
    }
}
