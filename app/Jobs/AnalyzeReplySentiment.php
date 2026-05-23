<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Services\AiInsightService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeReplySentiment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(public Activity $activity) {}

    public function handle(AiInsightService $ai): void
    {
        $preview = $this->activity->metadata['preview'] ?? $this->activity->body ?? '';
        if (strlen(trim($preview)) < 10) {
            return;
        }

        try {
            $result = $ai->analyzeSentiment($preview);
        } catch (\Exception) {
            return; // sentiment is non-critical — fail silently
        }

        $meta             = $this->activity->metadata ?? [];
        $meta['sentiment'] = $result;
        $this->activity->update(['metadata' => $meta]);
    }
}
