<?php

use App\Jobs\GenerateQuestions;
use App\Jobs\UpdateSummaryAndQuestions;
use App\Models\Question;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/login', function (Request $request) {
    try {
        $credentials = $request->only(['email', 'password']);
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken(env('TOKEN_NAME'))->plainTextToken;
            $token = explode('|', $token)[1];

            return response()->json([
                'message' => 'success login',
                'error' => null,
                'data' => [
                    'token' => $token,
                    'user' => $user
                ]
            ], 200);
        }

        return response()->json([
            'message' => 'failed login, account not found',
            'error' => 'Invalid credentials',
            'data' => null
        ], 401);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'failed login, server problem',
            'error' => $e->getMessage(),
            'data' => null
        ], 500);
    }
});

Route::post('/register', function (Request $request) {
    $credentials = $request->only(['email', 'password']);
    $user = User::create($credentials);;

    return response()->json(['message' => 'success register', 'error' => null, 'data' => $user], 201);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', function (Request $request) {
        auth()->user()->tokens()->delete();

        return response()->json(['message' => 'success logout', 'error' => null, 'data' => null], 200);
    });

    Route::post('/generate-question', function (Request $request) {
        $summary = Summary::create([
            'job_title' => $request->job_title,
            'job_description' => $request->job_description,
            'user_id' => auth()->user()->id,
        ]);

        GenerateQuestions::dispatch($summary);

        return response()->json([
            'message' => 'success generate question',
            'error' => null,
            'data' => [
                'question_id' => $summary->id,
            ]
        ], 200);
    });

    Route::get('/questions/{id}', function (Request $request) {
        $summary = Summary::with('questions')->where('id', $request->id)->firstOrFail();

        return response()->json([
            'message' => 'success retrieve questions',
            'error' => null,
            'data' => [
                'job_title' => $summary->job_title,
                'job_description' => $summary->job_description,
                'questions' => $summary->questions,
            ]
        ], 200);
    });


    Route::post('/user-answer', function (Request $request) {
        $interviews = $request->interviews;
        $summary = Summary::where('user_id', auth()->user()->id)->latest('created_at')->first();
        $questions = Question::where('summary_id', $summary->id)->orderBy('created_at', 'asc')->get();

        foreach ($questions as $key => $question) {
            $question->update([
                'answer' => $interviews[$key]['answer'],
            ]);
        }

        UpdateSummaryAndQuestions::dispatch($summary, $interviews);

        return response()->json([
            'message' => 'success answer',
            'error' => null,
            'data' => [
                'summary_id' => $summary->id,
            ]
        ], 200);
    });

    Route::get('/feedback-summary/{id}', function (Request $request) {
        $summary = Summary::with('questions')->where('id', $request->id)->firstOrFail();

        return response()->json([
            'message' => 'success retrieve',
            'error' => null,
            'data' => [
                'id' => $summary->id,
                'job_title' => $summary->job_title,
                'job_description' => $summary->job_description,
                'total_score' => $summary->total_score,
                'date' => $summary->created_at,
                'feedbacks' => $summary->questions,
            ]
        ], 200);
    });

    Route::get('/feedback-summaries', function (Request $request) {
        $summaries = Summary::with('questions')->where('user_id', auth()->user()->id)->get();

        return response()->json([
            'message' => 'success retrieve all feedback summary',
            'error' => null,
            'data' => $summaries,
        ], 200);
    });

    Route::get('/statistics', function (Request $request) {
        $summaries = Summary::with('questions')->where('user_id', auth()->user()->id)->get();

        $totalScore = 0;
        $scoreCount = 0;
        $interviewCount = $summaries->count();
        $interviewsWithAnswersCount = 0;

        foreach ($summaries as $summary) {
            if ($summary->total_score !== null) {
                $totalScore += $summary->total_score;
                $scoreCount++;
            }
            foreach ($summary->questions as $question) {
                if ($question->answer !== null) {
                    $interviewsWithAnswersCount++;
                    break; // Count each interview with at least one answer only once
                }
            }
        }

        $averageScore = $scoreCount > 0 ? $totalScore / $scoreCount : null;

        return response()->json([
            'message' => 'success retrieve all feedback summary',
            'error' => null,
            'data' => [
                'average_score' => $averageScore,
                'interview_count' => $interviewCount,
                'interviews_with_answers_count' => $interviewsWithAnswersCount,
            ],
        ], 200);
    });
});
