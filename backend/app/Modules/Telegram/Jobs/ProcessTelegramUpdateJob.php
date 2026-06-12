<?php

namespace App\Modules\Telegram\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param  array<string, mixed>  $update */
    public function __construct(public array $update) {}

    public function handle(): void
    {
        Log::info('telegram.update', ['update_id' => $this->update['update_id'] ?? null]);
        // Port handlers from includes/bot/handlers/
    }
}
