<?php

namespace Watchtower\Support;

/**
 * Turns a cron expression into a short human-readable phrase for the common
 * cases Laravel's scheduler produces (->everyMinute(), ->hourly(), ->daily(),
 * ->weekly(), etc). Falls back to the raw expression for anything exotic, so it
 * is always safe to call.
 */
class CronDescriber
{
    public static function describe(?string $expression): string
    {
        if (! $expression) {
            return '—';
        }

        $known = [
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/10 * * * *' => 'Every 10 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            '0 * * * *' => 'Hourly',
            '0 0 * * *' => 'Daily at midnight',
            '0 0 * * 0' => 'Weekly on Sunday',
            '0 0 1 * *' => 'Monthly on the 1st',
            '0 0 1 1 *' => 'Yearly on Jan 1',
        ];

        if (isset($known[$expression])) {
            return $known[$expression];
        }

        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) === 5) {
            [$min, $hour, $dom, $month, $dow] = $parts;

            // Daily at a fixed time, e.g. "30 13 * * *".
            if (ctype_digit($min) && ctype_digit($hour) && $dom === '*' && $month === '*' && $dow === '*') {
                return sprintf('Daily at %02d:%02d', (int) $hour, (int) $min);
            }

            // Every N minutes past the hour, e.g. "15 * * * *".
            if (ctype_digit($min) && $hour === '*' && $dom === '*' && $month === '*' && $dow === '*') {
                return sprintf('Hourly at :%02d', (int) $min);
            }
        }

        return $expression;
    }
}
