<?php

namespace Services\ScrapeJob;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Services\ScrapeJob\Jobs\ProcessScrapingJob;
use Services\ScrapeJob\Other\Scraper;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeJob
{
    protected $scraper;
    public function __construct(Scraper $scraper)
    {
        $this->scraper = $scraper;
    }
    public function createJob(array $data)
    {
        $jobId = Str::uuid();
        $jobData = [
            'id' => $jobId,
            'jobs' => $data['jobs'],
            'status' => 'pending',
            'created_at' => now(),
        ];

        Redis::set("job:{$jobId}", json_encode($jobData));

        dispatch(new ProcessScrapingJob($jobId));

        return $jobId;
    }

    public function processJob($jobId)
    {
        $jobKey = "job:{$jobId}";
        $jobData = json_decode(Redis::get($jobKey), true);

        $allScrapedData = [];
        $errors = [];

        foreach ($jobData['jobs'] as $task) {
            $scraped_data = $this->scraper->scrapeUrl($task['url'], $task['selectors']['selector']);

            if ($scraped_data['success']) {
                $allScrapedData[] = [
                    'url' => $task['url'],
                    'data' => $scraped_data['data'],
                ];
            } else {
                $errors[] = [
                    'url' => $task['url'],
                    'error' => $scraped_data['error'],
                ];
            }
        }

        $jobData['status'] = empty($errors) ? 'completed' : 'failed';
        $jobData['scraped_data'] = $allScrapedData;
        $jobData['errors'] = $errors;

        Redis::set($jobKey, json_encode($jobData));
    }

    public function getJobById($jobId)
    {
        $jobKey = "job:{$jobId}";
        $jobData = Redis::get($jobKey);

        return $jobData ? json_decode($jobData, true) : null;
    }

    public function deleteJobById($jobId)
    {
        $jobKey = "job:{$jobId}";

        if (Redis::exists($jobKey)) {
            Redis::del($jobKey);
            return true;
        }

        return false;
    }
}
