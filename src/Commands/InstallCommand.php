<?php

namespace Watchtower\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'watchtower:install
                            {--force : Overwrite any existing published files}';

    protected $description = 'Install Watchtower: publish config & assets and run migrations';

    public function handle(): int
    {
        $this->components->info('Installing Watchtower…');

        $this->callSilent('vendor:publish', [
            '--tag' => 'watchtower-config',
            '--force' => $this->option('force'),
        ]);
        $this->components->task('Published config', fn () => true);

        $this->callSilent('vendor:publish', [
            '--tag' => 'watchtower-assets',
            '--force' => true,
        ]);
        $this->components->task('Published dashboard assets', fn () => true);

        $this->call('migrate');

        $this->newLine();
        $this->components->info('Watchtower installed.');
        $this->components->bulletList([
            'Visit /'.config('watchtower.path', 'watchtower').' (local environment is allowed by default).',
            'To allow other environments, define the "viewWatchtower" gate or call Watchtower::auth().',
            'Schedule pruning daily:  $schedule->command(\'watchtower:prune\')->daily();',
        ]);

        return self::SUCCESS;
    }
}
