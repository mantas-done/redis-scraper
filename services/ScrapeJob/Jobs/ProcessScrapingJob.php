<?php

namespace Services\ScrapeJob\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Services\ScrapeJob\ScrapeJob;

class ProcessScrapingJob implements ShouldQueue
{
    public $jobId;

    public function __construct($job_id)
    {
        $this->jobId = $job_id;
    }

    public function handle()
    {
        $scrapeJob = app(ScrapeJob::class);
        $scrapeJob->processJob($this->jobId);
    }
}
