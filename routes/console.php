<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty')->everyMinute();

// Sync automatique Emelia — ne retraite que les contacts déjà liés (économie d'API)
Schedule::command('emelia:sync-all-campaigns --only-linked')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();
