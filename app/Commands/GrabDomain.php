<?php

/** @noinspection ALL */

namespace App\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class GrabDomain extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'domain:grab';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $link = 'https://member.expireddomains.net/domains/combinedexpired/?start=200&fabirth_year=2016&flimit=200&fbl=1000&fnocctlds=1&fwhois=22#listing';
        $link = \Spatie\Url\Url::fromString($link);

        $params = $link->getAllQueryParameters();

        $link = (string) \Spatie\Url\Url::create()
            ->withHost('member.expireddomains.net')
            ->withScheme('https')
            ->withPath('/domains/combinedexpired/');

        $domains = [];
        $currentPage = 1;
        $lastPage = 1;

        $cookie = file_get_contents(base_path('cookie.txt'));
        $cookieJar = new CookieJar();
        $cookieJar->setCookie(
            new SetCookie([
                'Domain' => 'member.expireddomains.net',
                'Name' => 'ExpiredDomainssessid',
                'Value' => $cookie,
                'Discard' => true,
            ])
        );

        file_put_contents(
            base_path('cookies.json'),
            json_encode($cookieJar->toArray())
        );

        $client = new Client([
            RequestOptions::COOKIES => new FileCookieJar(
                cookieFile: base_path('cookies.json'),
                storeSessionCookies: true
            ),
            RequestOptions::HEADERS => [
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'accept-encoding' => 'gzip, deflate, br',
                'accept-language' => 'en-US,en;q=0.9,id;q=0.8',
                'sec-ch-ua' => '"Chromium";v="110", "Not A(Brand";v="24", "Google Chrome";v="110"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Windows"',
                'sec-fetch-dest' => 'document',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-user' => '?1',
                'upgrade-insecure-requests' => '1',
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            ],
        ]);

        $http = Http::setClient($client);

        $request = function (int $page) use ($http, $link, $params) {
            return $http->get($link, [
                ...$params,
                'start' => 200 * $page,
            ]);
        };

        $response = $request($currentPage);

        if ($response->effectiveUri()->getPath() === '/login/') {
            throw new \RuntimeException('Please login first');
        }

        $responseBody = str($response->body());
        $lastPage = $responseBody->match('/Page\s\d+\sof\s(?P<lastPage>\d+)/')->toInteger();

        $totals = ($lastPage * 200);

        $this->info('Totals '.$totals.' domains');

        while ($currentPage < $lastPage) {
            $response = $request($currentPage);
            $responseBody = str($response->body());
            $domains = $responseBody
                ->matchAll('/field_domain"><a href=".*" target="_blank" title="(?P<domain>.*?)"/')
                ->map(fn ($match) => strtolower($match));

            file_put_contents('domains.txt', implode(PHP_EOL, $domains->toArray()), FILE_APPEND);

            $this->info('Page '.($currentPage).' of '.$lastPage.' done');

            $currentPage++;
        }
    }
}
