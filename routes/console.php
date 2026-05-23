<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty')->everyMinute();

// Sync automatique Emelia — ne retraite que les contacts déjà liés (économie d'API)
Schedule::command('emelia:sync-all-campaigns --only-linked')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();

// Préchauffe le cache IA des deals actifs chaque nuit à 03:00 (avant score-contacts)
Schedule::command('ai:precompute')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Score IA contacts — top 50 contacts liés à Emelia, chaque nuit à 04:00
Schedule::command('ai:score-contacts --limit=50')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();
