<?php

namespace Workbench\App\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Watchtower\Models\ExceptionRecord;
use Watchtower\Models\JobRecord;
use Watchtower\Models\ScheduleRun;
use Watchtower\Support\TaskKey;

class DemoSeedCommand extends Command
{
    protected $signature = 'watchtower:demo-seed {--fresh : Wipe existing demo data first}';

    protected $description = 'Seed sample Watchtower data for the preview dashboard';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            ScheduleRun::query()->delete();
            JobRecord::query()->delete();
            ExceptionRecord::query()->delete();
            app('db')->table('failed_jobs')->delete();
        }

        $this->seedScheduleRuns();
        $this->seedJobRecords();
        $this->seedExceptions();
        $this->seedFailedJobs();

        $this->components->info('Watchtower demo data seeded.');

        return self::SUCCESS;
    }

    protected function seedScheduleRuns(): void
    {
        foreach (app(Schedule::class)->events() as $i => $event) {
            $key = TaskKey::for($event);
            $command = $event->description ?? $event->getSummaryForDisplay() ?? (string) $event->command;

            // One task is intentionally left stale so missed-detection lights up.
            $stale = $i === 1;

            for ($r = 0; $r < 6; $r++) {
                $startedAt = $stale
                    ? Carbon::now()->subDays(3)->subMinutes($r * 60)
                    : Carbon::now()->subMinutes($r * 65 + random_int(0, 10));

                $status = ($r === 0 && $i === 2) ? 'failed' : 'success';
                $duration = random_int(80, 4200);

                ScheduleRun::create([
                    'task_key' => $key,
                    'command' => $command,
                    'expression' => $event->expression ?? null,
                    'timezone' => $event->timezone ? (string) $event->timezone : 'UTC',
                    'started_at' => $startedAt,
                    'finished_at' => (clone $startedAt)->addMilliseconds($duration),
                    'duration_ms' => $duration,
                    'status' => $status,
                    'exit_code' => $status === 'failed' ? 1 : 0,
                    'output' => $status === 'failed' ? "Error: connection timed out\nRetrying…" : "Done in {$duration}ms",
                    'host' => 'preview-host',
                ]);
            }
        }
    }

    protected function seedJobRecords(): void
    {
        $jobs = [
            'App\\Jobs\\SendInvoiceEmail' => 'emails',
            'App\\Jobs\\GenerateThumbnail' => 'media',
            'App\\Jobs\\SyncToCrm' => 'default',
            'App\\Jobs\\ExportReport' => 'reports',
        ];

        foreach (range(1, 220) as $n) {
            $name = array_rand($jobs);
            $queue = $jobs[$name];
            $finishedAt = Carbon::now()->subMinutes(random_int(0, 1440));
            $duration = random_int(40, 6000);
            $failed = random_int(1, 12) === 1;

            JobRecord::create([
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => $queue,
                'name' => $name,
                'status' => $failed ? 'failed' : 'processed',
                'attempts' => $failed ? random_int(1, 3) : 1,
                'queued_at' => (clone $finishedAt)->subSeconds(random_int(1, 30)),
                'started_at' => (clone $finishedAt)->subMilliseconds($duration),
                'finished_at' => $finishedAt,
                'duration_ms' => $duration,
                'payload' => null,
            ]);
        }

        // A handful currently queued / processing.
        foreach (range(1, 6) as $n) {
            JobRecord::create([
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'name' => 'App\\Jobs\\SyncToCrm',
                'status' => $n <= 2 ? 'processing' : 'queued',
                'attempts' => 0,
                'queued_at' => Carbon::now()->subSeconds(random_int(1, 60)),
                'started_at' => $n <= 2 ? Carbon::now()->subSeconds(5) : null,
            ]);
        }
    }

    protected function seedExceptions(): void
    {
        $samples = [
            ['Illuminate\\Database\\QueryException', 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded', 'app/Repositories/OrderRepository.php', 88, 'job', 47, false],
            ['Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException', 'No query results for model [App\\Models\\Invoice] 9931', 'app/Http/Controllers/InvoiceController.php', 122, 'request', 12, false],
            ['GuzzleHttp\\Exception\\ConnectException', 'cURL error 28: Operation timed out after 10000 ms', 'app/Services/CrmClient.php', 54, 'job', 9, false],
            ['RuntimeException', 'Disk [s3] is not configured', 'app/Jobs/ExportReport.php', 31, 'schedule', 3, true],
        ];

        foreach ($samples as [$class, $message, $file, $line, $context, $count, $resolved]) {
            ExceptionRecord::create([
                'fingerprint' => hash('sha256', $class.$file.$line),
                'class' => $class,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'trace' => "#0 {$file}({$line}): handle()\n#1 [internal function]\n#2 {main}",
                'context_type' => $context,
                'count' => $count,
                'first_seen_at' => Carbon::now()->subDays(random_int(2, 9)),
                'last_seen_at' => Carbon::now()->subMinutes(random_int(1, 600)),
                'resolved_at' => $resolved ? Carbon::now()->subHours(2) : null,
            ]);
        }
    }

    protected function seedFailedJobs(): void
    {
        $failer = app('queue.failer');

        $jobs = [
            ['App\\Jobs\\SendInvoiceEmail', 'emails', 'Illuminate\\Database\\QueryException: SQLSTATE[HY000] Lock wait timeout'],
            ['App\\Jobs\\GenerateThumbnail', 'media', 'RuntimeException: Image driver [imagick] not available'],
            ['App\\Jobs\\SyncToCrm', 'default', 'GuzzleHttp\\Exception\\ConnectException: cURL error 28: timed out'],
            ['App\\Jobs\\SendInvoiceEmail', 'emails', 'Illuminate\\Database\\QueryException: SQLSTATE[HY000] Lock wait timeout'],
        ];

        foreach ($jobs as [$name, $queue, $exception]) {
            $payload = json_encode([
                'uuid' => (string) Str::uuid(),
                'displayName' => $name,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => $name],
            ]);

            $failer->log('database', $queue, $payload, new \Exception($exception));
        }
    }
}
