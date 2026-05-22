<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class SyncEmeliaCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public readonly bool $onlyLinked = true,
    ) {}

    public function handle(): void
    {
        $args = $this->onlyLinked ? ['--only-linked' => true] : [];
        Artisan::call('emelia:sync-all-campaigns', $args);
    }
}
