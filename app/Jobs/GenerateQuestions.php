<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\Summary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\error;

class GenerateQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $summary;

    /**
     * Create a new job instance.
     */
    public function __construct(Summary $summary)
    {
        $this->summary = $summary;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url = env('API_ML_BASE_URL') . '/generate-question';
        $apiKey = env('API_ML_KEY');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post($url, [
            'job_title' => $this->summary->job_title,
            'job_description' => $this->summary->job_description,
        ]);

        if ($response->status() === 200) {
            $data = $response->json();
            $questions = $data['data']['questions'];
            $summaryId = $this->summary->id;

            foreach ($questions as $question) {
                Question::create([
                    'summary_id' => $summaryId,
                    'question' => $question['question'],
                ]);
            }
        }
    }
}
