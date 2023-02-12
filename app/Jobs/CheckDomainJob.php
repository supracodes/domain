<?php

namespace App\Jobs;

use App\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CheckDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $list
    ) {
    }

    public function handle()
    {
        $response = Http::seoMetrics()->get('/search', [
            'url' => $this->list,
        ]);

        $attrs = collect($response->json())
            ->only(['host', 'mozRank', 'backlinks', 'DA', 'PA', 'TF'])
            ->merge(['host' => $this->list])
            ->all();

        dump($attrs);

        Domain::query()->updateOrCreate(['host' => $this->list], $attrs);
    }
}
