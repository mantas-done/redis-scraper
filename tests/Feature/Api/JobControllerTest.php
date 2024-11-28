<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Services\ScrapeJob\Jobs\ProcessScrapingJob;
use Services\ScrapeJob\ScrapeJob;
use Tests\TestCase;
use Illuminate\Support\Facades\Queue;

class JobControllerTest extends TestCase
{
    public function testCreateJob()
    {
        Redis::flushAll();
        Queue::fake();

        $payload = [
            "jobs" => [
                [
                    "url" => "https://example.com/page1",
                    "selectors" => ["selector" => ".page-title"],
                ],
                [
                    "url" => "https://example.com/page2",
                    "selectors" => ["selector" => ".header"],
                ]
            ]
        ];

        $response = $this->postJson('/api/jobs', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['id']);

        $jobId = $response->json('id');
        $redisJob = Redis::get("job:{$jobId}");
        $storedData = json_decode($redisJob, true);

        $this->assertEquals('pending', $storedData['status']);
        $this->assertEquals($payload['jobs'], $storedData['jobs']);

        Queue::assertPushed(ProcessScrapingJob::class);
    }

    public function testCreateJobInvalidUrlError()
    {
        $payload = [
            "jobs" => [
                [
                    "url" => "invalid-url",
                    "selectors" => ["title" => ".page-title"]
                ]
            ]
        ];

        $response = $this->postJson('/api/jobs', $payload);

        $response->assertStatus(422);
    }

    public function testCreateJobMissingSelectorError()
    {
        $payload = [
            "jobs" => [
                ["url" => "https://example.com/page1"]
            ]
        ];

        $response = $this->postJson('/api/jobs', $payload);

        $response->assertStatus(422);
    }

    public function testProcessJob()
    {
        Redis::flushAll();
        Queue::fake();

        Http::fake([
            'https://example.com/page1' => Http::response('<div class="page-title">Page 1 Title</div>', 200),
            'https://example.com/page2' => Http::response('<div class="header">Header Content</div>', 200),
        ]);

        $scrapeJob = app(ScrapeJob::class);

        $jobId = $this->createJob([
            'jobs' => [
                ["url" => "https://example.com/page1", "selectors" => ["selector" => ".page-title"]],
                ["url" => "https://example.com/page2", "selectors" => ["selector" => ".header"]],
            ]
        ]);

        $scrapeJob->processJob($jobId);

        $processedData = json_decode(Redis::get("job:{$jobId}"), true);

        $this->assertEquals('completed', $processedData['status']);
        $this->assertCount(2, $processedData['scraped_data']);
        $this->assertEquals('Page 1 Title', $processedData['scraped_data'][0]['data']);
        $this->assertEquals('Header Content', $processedData['scraped_data'][1]['data']);
    }

    public function testProcessJobFailedStatus()
    {
        Http::fake([
            'https://example.com/page1' => Http::response('<div class="page-title">Page 1 Title</div>', 200),
            'https://example.com/page2' => Http::response('<div>No Header Found</div>', 200),
        ]);

        Redis::flushAll();
        Queue::fake();

        $scrapeJob = app(ScrapeJob::class);

        $jobId = $this->createJob([
            'jobs' => [
                ["url" => "https://example.com/page1", "selectors" => ["selector" => ".page-title"]],
                ["url" => "https://example.com/page2", "selectors" => ["selector" => ".non-existent-selector"]],
            ]
        ]);

        $scrapeJob->processJob($jobId);

        $processedData = json_decode(Redis::get("job:{$jobId}"), true);

        $this->assertEquals('failed', $processedData['status']);
        $this->assertCount(1, $processedData['scraped_data']);
        $this->assertEquals('Page 1 Title', $processedData['scraped_data'][0]['data']);

        $this->assertCount(1, $processedData['errors']);
        $this->assertStringContainsString("Selector '.non-existent-selector' not found", $processedData['errors'][0]['error']);
    }

    public function testShowJobByIdSuccess()
    {
        $jobId = $this->createJob();

        $response = $this->getJson("/api/jobs/{$jobId}");

        $response->assertStatus(200)
            ->assertJson(['id' => $jobId]);
    }

    public function testShowJobByIdNotFound()
    {
        $response = $this->getJson('/api/jobs/nonexistent-job-id');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Job not found']);
    }

    public function testDestroyJobByIdSuccess()
    {
        $jobId = $this->createJob();

        $response = $this->deleteJson("/api/jobs/{$jobId}");

        $response->assertStatus(204);

        $this->assertNull(Redis::get("job:{$jobId}"));
    }

    public function testDestroyJobByIdNotFound()
    {
        $response = $this->deleteJson('/api/jobs/nonexistent-job-id');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Job not found']);
    }

    private function createJob(array $overrides = []): string
    {
        $jobId = $overrides['id'] ?? 'test-job-id-' . uniqid();
        $jobData = array_merge([
            'id' => $jobId,
            'jobs' => [['url' => 'https://example.com', 'selectors' => ['selector' => '.class']]],
            'status' => 'pending',
            'scraped_data' => [],
        ], $overrides);

        Redis::set("job:{$jobId}", json_encode($jobData));

        return $jobId;
    }
}
