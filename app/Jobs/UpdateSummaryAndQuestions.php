<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\Summary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use \Faker\Factory as Faker;
use Illuminate\Support\Facades\Http;

class UpdateSummaryAndQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $summary;
    protected $interviews;

    /**
     * Create a new job instance.
     */
    public function __construct(Summary $summary, array $interviews)
    {
        $this->summary = $summary;
        $this->interviews = $interviews;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url = env('API_ML_BASE_URL') . '/user-answer';
        $apiKey = env('API_ML_KEY');

        // Update questions with user answers
        $questions = Question::where('summary_id', $this->summary->id)->orderBy('created_at', 'asc')->get();
        foreach ($questions as $key => $question) {
            $question->update([
                'answer' => $this->interviews[$key]['answer'],
            ]);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post($url, [
            'job_title' => $this->summary->job_title,
            'job_description' => $this->summary->job_description,
            'interviews' => $this->interviews,
        ]);

        if ($response->status() === 200) {
            $data = $response->json();
            $questionDatas = $data['data']['feedbacks'];
            $totalScore = 0.0;

            // Update summary and questions with additional data
            foreach ($questions as $key => $question) {
                $tempScore = $questionDatas[$key]['score'];
                $question->update([
                    'feedback' => $questionDatas[$key]['feedback'],
                    'score' => $tempScore,
                ]);

                $totalScore += $tempScore;
            }

            $this->summary->update([
                'total_score' => $totalScore / count($questions),
            ]);
        }
    }
}
