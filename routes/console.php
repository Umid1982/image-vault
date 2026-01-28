<?php
use Illuminate\Support\Facades\Schedule;

Schedule::command('images:retry-failed --hours=24 --limit=100')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/retry-conversions.log'));
