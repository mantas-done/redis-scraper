<?php

namespace Services\ScrapeJob\Other;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class Scraper
{
    public function scrapeUrl($url, $selector)
    {
        $response = Http::get($url);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => "Failed to fetch $url",
            ];
        }

        $html = $response->body();
        $crawler = new Crawler($html);

        try {
            $data = $crawler->filter($selector)->text();
            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => "Selector '$selector' not found on $url",
            ];
        }
    }
}
