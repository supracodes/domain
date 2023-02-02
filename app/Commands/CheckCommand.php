<?php

namespace App\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
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
        $lists = $this->getList();
        $chunks = $lists->chunk(10);

        $cookieJar = new CookieJar();
        $client = new Client([
            RequestOptions::VERIFY => false,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::COOKIES => $cookieJar,
        ]);

        $http = Http::setClient($client);

        $http->get('https://99webtools.com/page-authority-domain-authority-checker.php');

        foreach ($chunks as $chunk) {
            $response = $http->asForm()->post('https://99webtools.com/inc/pada.php', [
                'site' => (string) collect($chunk)->join(PHP_EOL),
            ]);

            $json = $response->json();

            if (! $json) {
                foreach ($chunk as $domain) {
                    render('<div class="text-red-500">'.$domain.' Failed.</div>');
                }

                $this->write('errors.txt', (string) collect($chunk)->join(PHP_EOL));

                continue;
            }

            foreach ($json as $result) {
                $result = collect($result);
                $domain = str($result->get('uu'))->replace(['/'], '');
                $domainAuthority = $result->get('pda');
                $domainPageAuthority = $result->get('upa');

                render(<<<HTML
                    <div>
                        <span class="text-green-500 w-40">
                            {$domain}
                        </span>
                        <span class="text-yellow-500 w-10">
                            {$domainAuthority}
                        </span>
                        <span class="text-purple-500 w-10">
                            {$domainPageAuthority}
                        </span>
                    </div>
                HTML);

                $this->write('results.txt', $domain.'|'.$domainAuthority.'|'.$domainPageAuthority);
            }
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
