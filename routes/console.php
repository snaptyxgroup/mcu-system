<?php

use Illuminate\Support\Facades\Schedule;

/**
 * routes/console.php — Scheduled Tasks for Snaptyx MCU
 *
 * cPanel Deployment:
 *   Add ONE cron job in cPanel: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
 *   This single cron drives ALL scheduled tasks below.
 *
 * Queue Worker (cPanel):
 *   Add a cPanel cron every 5 minutes to restart the queue worker:
 *   *\/5 * * * * php /path/to/artisan queue:work --queue=ai,default --tries=3 --timeout=90 --stop-when-empty
 *
 *   Note: `--stop-when-empty` ensures the worker exits after processing the queue,
 *   preventing cPanel from killing long-running processes.
 */

// ── Queue Health Check ────────────────────────────────────────────────────────
// Restart queue worker daily to prevent memory leaks on shared hosting
Schedule::command('queue:restart')->daily()->at('03:00');

// ── Data Maintenance ──────────────────────────────────────────────────────────
// Prune old activity log entries (keep 90 days)
Schedule::command('activitylog:clean --days=90')->weekly()->sundays()->at('02:00');

// Prune soft-deleted records older than 30 days
Schedule::command('model:prune --model=App\\Models\\McuRegistration')->monthly();
Schedule::command('model:prune --model=App\\Models\\Patient')->monthly();
Schedule::command('model:prune --model=App\\Models\\Organization')->monthly();

// ── Queue Monitoring (cPanel alternative to Horizon) ──────────────────────────
// Log failed jobs count to system log for alerting
Schedule::call(function () {
    $failedCount = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
    if ($failedCount > 0) {
        \Illuminate\Support\Facades\Log::warning("Snaptyx MCU: {$failedCount} failed job(s) in queue. Review at /admin/queue-monitor.");
    }
})->hourly()->name('check_failed_jobs')->withoutOverlapping();
