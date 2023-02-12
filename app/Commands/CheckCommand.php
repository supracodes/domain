<?php

namespace App\Commands;

use App\Domain;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use Spatie\Fork\Fork;
use function Termwind\render;

class CheckCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'domain:check {--list=list.txt : The list of domains}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Check domains';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $size = 50;
        $lists = $this->getList();
        $chunks = $lists->chunk($size);

        foreach ($chunks as $chunk) {
            $tasks = collect($chunk)
                ->filter(fn ($domain) => Domain::query()->where([
                    'host' => strtoupper($domain),
                ])->exists())
                ->map(fn ($domain) => function () use ($domain) {
                    $response = Http::seoMetrics()->get('/search', [
                        'url' => $domain,
                    ]);

                    if ($response->failed()) {
                        dump($response->body());
                    }

                    $attrs = collect($response->json())
                        ->only(['host', 'mozRank', 'backlinks', 'DA', 'PA', 'TF'])
                        ->merge(['host' => $domain]);

                    Domain::query()->updateOrCreate(['host' => $domain], $attrs->all());

                    render("<div>{$attrs->get('host')}  {$attrs->get('DA')}</div>");
                });

            Fork::new()
                ->concurrent($size / 2)
                ->run(...$tasks->all());

            $this->info('Waiting for 50 seconds...');
            sleep(2);
        }
    }

    protected function write(string $file, $data): void
    {
        file_put_contents($file, $data.PHP_EOL, FILE_APPEND);
    }

    protected function getList(): Collection
    {
        $fileName = $this->option('list', 'list.txt');
        $listFile = $this->resolveFile($fileName);

        if (! is_file($listFile)) {
            $fileName = $this->ask('Where you put your domain list?', 'list.txt');
            $listFile = $this->resolveFile($fileName);

            if (! is_file($listFile)) {
                throw new \RuntimeException(`File {$listFile} not found`);
            }
        }

        return collect(file($listFile))->map(fn ($domain) => trim($domain));
    }

    protected function resolveFile(string $file): string
    {
        return app()->environment(['development']) ? base_path($file) : getcwd().'/'.$file;
    }
}
