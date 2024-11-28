<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Services\ScrapeJob\ScrapeJob;

class JobController extends Controller
{
    protected ScrapeJob $scrapeJob;

    public function __construct(ScrapeJob $scrapeJob)
    {
        $this->scrapeJob = $scrapeJob;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'jobs' => 'required|array',
            'jobs.*.url' => 'required|url',
            'jobs.*.selectors' => 'required|array',
        ]);

        $jobId = $this->scrapeJob->createJob($validated);

        return response()->json(['id' => $jobId], Response::HTTP_CREATED);
    }

    public function show($id)
    {
        $scrapeJobData = $this->scrapeJob->getJobById($id);

        if (!$scrapeJobData) {
            return response()->json(['message' => 'Job not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($scrapeJobData, Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $result = $this->scrapeJob->deleteJobById($id);

        if (!$result) {
            return response()->json(['message' => 'Job not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Job deleted successfully'], Response::HTTP_NO_CONTENT);
    }

}
