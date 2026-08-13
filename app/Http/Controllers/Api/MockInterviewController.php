<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MockInterview;
use App\Models\AIUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MockInterviewController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Start Mock Interview
    |--------------------------------------------------------------------------
    */

    public function start(Request $request)
    {
        $request->validate([
            'role' => 'required|string|max:100',
            'experience' => 'required|string|max:100',
        ]);

        $prompt = "
You are an expert technical interviewer.

Generate ONLY ONE interview question.

Role: {$request->role}
Experience: {$request->experience}

Return only the question.
";

        try {

            $response = $this->sendGroqRequest(
                $prompt,
                0.7
            );

            /*
            |--------------------------------------------------------------------------
            | Groq Failed
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {

                $errorMessage =
                    $response->json('error.message')
                    ?? 'Groq API Error';

                $this->saveAIUsage(
                    $request,
                    $response,
                    'failed',
                    $errorMessage
                );

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Question
            |--------------------------------------------------------------------------
            */

            $question = trim(
                (string) $response->json(
                    'choices.0.message.content'
                )
            );

            if (empty($question)) {

                $this->saveAIUsage(
                    $request,
                    $response,
                    'failed',
                    'AI returned an empty interview question.'
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'AI ne interview question return nahi kiya.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Save AI Usage
            |--------------------------------------------------------------------------
            */

            $this->saveAIUsage(
                $request,
                $response,
                'success'
            );

            /*
            |--------------------------------------------------------------------------
            | Create Interview
            |--------------------------------------------------------------------------
            */

            $sessionId = (string) Str::uuid();

            $chat = MockInterview::create([
                'user_id' => auth()->id(),

                'interview_session_id' =>
                    $sessionId,

                'role' =>
                    $request->role,

                'experience' =>
                    $request->experience,

                'question_no' => 1,

                'question' =>
                    $question,

                'is_completed' =>
                    false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $chat,
            ]);

        } catch (\Throwable $error) {

            Log::error(
                'Mock interview start error',
                [
                    'message' =>
                        $error->getMessage(),

                    'user_id' =>
                        $request->user()?->id,
                ]
            );

            $this->saveFailedUsageWithoutResponse(
                $request,
                $error->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Mock interview start nahi ho saka.',
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Submit Answer
    |--------------------------------------------------------------------------
    */

    public function answer(Request $request)
    {
        $request->validate([
            'interview_id' =>
                'required|exists:mock_interviews,id',

            'answer' =>
                'required|string|min:10',
        ]);

        $interview =
            MockInterview::where(
                'id',
                $request->interview_id
            )
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Prevent Duplicate Answer
        |--------------------------------------------------------------------------
        */

        if (!empty($interview->answer)) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Is question ka answer pehle hi submit ho chuka hai.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Evaluation Prompt
        |--------------------------------------------------------------------------
        */

        $evaluationPrompt = "
You are an expert technical interviewer.

Evaluate the candidate's answer.

Role: {$interview->role}
Experience: {$interview->experience}
Question: {$interview->question}
Candidate Answer: {$request->answer}

Return valid JSON only in this exact format:

{
    \"score\": 8,
    \"feedback\": \"Clear and useful feedback\",
    \"improvement\": \"How the candidate can improve\"
}

Important rules:
- Score must be between 1 and 10.
- Do not return markdown.
- Do not return code blocks.
- Return JSON only.
";

        try {

            /*
            |--------------------------------------------------------------------------
            | Evaluate Answer
            |--------------------------------------------------------------------------
            */

            $evaluationResponse =
                $this->sendGroqRequest(
                    $evaluationPrompt,
                    0.3
                );

            /*
            |--------------------------------------------------------------------------
            | Evaluation Failed
            |--------------------------------------------------------------------------
            */

            if (
                !$evaluationResponse->successful()
            ) {

                $errorMessage =
                    $evaluationResponse->json(
                        'error.message'
                    )
                    ?? 'Groq API se answer evaluate nahi ho saka.';

                $this->saveAIUsage(
                    $request,
                    $evaluationResponse,
                    'failed',
                    $errorMessage
                );

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Groq API se answer evaluate nahi ho saka.',

                    'error' =>
                        $evaluationResponse->json(),
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Read Evaluation
            |--------------------------------------------------------------------------
            */

            $content =
                $evaluationResponse->json(
                    'choices.0.message.content'
                );

            $content = preg_replace(
                '/```json|```/i',
                '',
                (string) $content
            );

            $evaluation =
                json_decode(
                    trim($content),
                    true
                );

            /*
            |--------------------------------------------------------------------------
            | Invalid Evaluation
            |--------------------------------------------------------------------------
            */

            if (!is_array($evaluation)) {

                $this->saveAIUsage(
                    $request,
                    $evaluationResponse,
                    'failed',
                    'AI evaluation response format invalid.'
                );

                return response()->json([
                    'success' => false,

                    'message' =>
                        'AI evaluation response ka format invalid hai.',

                    'raw_response' =>
                        $content,
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Save Evaluation AI Usage
            |--------------------------------------------------------------------------
            */

            $this->saveAIUsage(
                $request,
                $evaluationResponse,
                'success'
            );

            /*
            |--------------------------------------------------------------------------
            | Score + Feedback
            |--------------------------------------------------------------------------
            */

            $score = max(
                1,
                min(
                    10,
                    (int) (
                        $evaluation['score']
                        ?? 1
                    )
                )
            );

            $feedback =
                $evaluation['feedback']
                ?? 'Feedback available nahi hai.';

            $improvement =
                $evaluation['improvement']
                ?? '';

            $fullFeedback =
                $feedback;

            if (!empty($improvement)) {

                $fullFeedback .=
                    "\n\nImprovement: "
                    . $improvement;
            }

            /*
            |--------------------------------------------------------------------------
            | Update Current Interview
            |--------------------------------------------------------------------------
            */

            $interview->update([
                'answer' =>
                    trim($request->answer),

                'feedback' =>
                    $fullFeedback,

                'score' =>
                    $score,
            ]);

            $evaluatedInterview =
                $interview->fresh();

            /*
            |--------------------------------------------------------------------------
            | Complete After Question 5
            |--------------------------------------------------------------------------
            */

            if (
                (int) $interview->question_no
                >= 5
            ) {

                $interview->update([
                    'is_completed' => true,
                ]);

                $sessionRecords =
                    MockInterview::where(
                        'user_id',
                        auth()->id()
                    )
                        ->where(
                            'interview_session_id',
                            $interview
                                ->interview_session_id
                        )
                        ->get();

                MockInterview::where(
                    'user_id',
                    auth()->id()
                )
                    ->where(
                        'interview_session_id',
                        $interview
                            ->interview_session_id
                    )
                    ->update([
                        'is_completed' =>
                            true,
                    ]);

                $averageScore = round(
                    (float) $sessionRecords
                        ->whereNotNull('score')
                        ->avg('score'),
                    1
                );

                return response()->json([
                    'success' => true,

                    'message' =>
                        'Mock interview completed successfully.',

                    'completed' =>
                        true,

                    'data' =>
                        $interview->fresh(),

                    'average_score' =>
                        $averageScore,

                    'total_questions' =>
                        5,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Previous Questions
            |--------------------------------------------------------------------------
            */

            $previousQuestions =
                MockInterview::where(
                    'user_id',
                    auth()->id()
                )
                    ->where(
                        'interview_session_id',
                        $interview
                            ->interview_session_id
                    )
                    ->orderBy(
                        'question_no'
                    )
                    ->pluck(
                        'question'
                    )
                    ->filter()
                    ->values()
                    ->toArray();

            $previousQuestionsText =
                collect(
                    $previousQuestions
                )
                    ->map(
                        fn (
                            $question,
                            $index
                        ) =>
                            ($index + 1)
                            . '. '
                            . $question
                    )
                    ->implode("\n");

            $nextQuestionNo =
                (int)
                $interview->question_no
                + 1;

            /*
            |--------------------------------------------------------------------------
            | Next Question Prompt
            |--------------------------------------------------------------------------
            */

            $nextQuestionPrompt = "
You are an expert technical interviewer.

Generate exactly ONE next interview question.

Role: {$interview->role}
Experience level: {$interview->experience}
Current question number: {$nextQuestionNo} of 5

Questions already asked:
{$previousQuestionsText}

Important rules:
- Do not repeat any previous question.
- Keep the question suitable for the selected role.
- Keep the difficulty suitable for the experience level.
- Return only the question.
- Do not include numbering.
- Do not include explanation.
";

            /*
            |--------------------------------------------------------------------------
            | Generate Next Question
            |--------------------------------------------------------------------------
            */

            $nextQuestionResponse =
                $this->sendGroqRequest(
                    $nextQuestionPrompt,
                    0.7
                );

            /*
            |--------------------------------------------------------------------------
            | Next Question Failed
            |--------------------------------------------------------------------------
            */

            if (
                !$nextQuestionResponse
                    ->successful()
            ) {

                $errorMessage =
                    $nextQuestionResponse
                        ->json(
                            'error.message'
                        )
                    ?? 'Next interview question generate nahi ho saka.';

                $this->saveAIUsage(
                    $request,
                    $nextQuestionResponse,
                    'failed',
                    $errorMessage
                );

                return response()->json([
                    'success' => false,

                    'message' =>
                        'Next interview question generate nahi ho saka.',

                    'error' =>
                        $nextQuestionResponse
                            ->json(),
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Next Question
            |--------------------------------------------------------------------------
            */

            $nextQuestion = trim(
                (string)
                $nextQuestionResponse->json(
                    'choices.0.message.content'
                )
            );

            if (empty($nextQuestion)) {

                $this->saveAIUsage(
                    $request,
                    $nextQuestionResponse,
                    'failed',
                    'AI returned empty next question.'
                );

                return response()->json([
                    'success' => false,

                    'message' =>
                        'AI ne next question return nahi kiya.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | Save Next Question Usage
            |--------------------------------------------------------------------------
            */

            $this->saveAIUsage(
                $request,
                $nextQuestionResponse,
                'success'
            );

            /*
            |--------------------------------------------------------------------------
            | Create Next Interview Question
            |--------------------------------------------------------------------------
            */

            $nextInterview =
                MockInterview::create([
                    'user_id' =>
                        auth()->id(),

                    'interview_session_id' =>
                        $interview
                            ->interview_session_id,

                    'role' =>
                        $interview->role,

                    'experience' =>
                        $interview->experience,

                    'question_no' =>
                        $nextQuestionNo,

                    'question' =>
                        $nextQuestion,

                    'is_completed' =>
                        false,
                ]);

            return response()->json([
                'success' => true,

                'message' =>
                    'Answer evaluated successfully.',

                'completed' =>
                    false,

                'previous_result' =>
                    $evaluatedInterview,

                'next_question' =>
                    $nextInterview,

                'current_question' =>
                    $nextQuestionNo,

                'total_questions' =>
                    5,
            ]);

        } catch (\Throwable $error) {

            Log::error(
                'Mock interview answer error',
                [
                    'message' =>
                        $error->getMessage(),

                    'user_id' =>
                        $request->user()?->id,

                    'interview_id' =>
                        $request->interview_id,
                ]
            );

            $this->saveFailedUsageWithoutResponse(
                $request,
                $error->getMessage()
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Mock interview process nahi ho saka.',
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    */

    public function history()
    {
        $history =
            MockInterview::where(
                'user_id',
                auth()->id()
            )
                ->latest()
                ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Delete History
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $interview =
            MockInterview::where(
                'id',
                $id
            )
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->first();

        if (!$interview) {

            return response()->json([
                'success' => false,

                'message' =>
                    'Mock interview record not found.',
            ], 404);
        }

        $interview->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Mock interview history deleted successfully.',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Send Groq Request
    |--------------------------------------------------------------------------
    */

    private function sendGroqRequest(
        string $prompt,
        float $temperature = 0.7
    ) {
        $apiKey =
            config('services.groq.key');

        $model =
            config(
                'services.groq.model',
                'llama-3.1-8b-instant'
            );

        if (!$apiKey) {

            throw new \RuntimeException(
                'GROQ_API_KEY is missing.'
            );
        }

        return Http::withToken(
            $apiKey
        )
            ->acceptJson()
            ->timeout(60)
            ->post(
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'model' =>
                        $model,

                    'messages' => [
                        [
                            'role' =>
                                'user',

                            'content' =>
                                $prompt,
                        ],
                    ],

                    'temperature' =>
                        $temperature,

                    'max_tokens' =>
                        1200,
                ]
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Save AI Usage
    |--------------------------------------------------------------------------
    */

    private function saveAIUsage(
        Request $request,
        $response,
        string $status,
        ?string $errorMessage = null
    ): void {

        try {

            $promptTokens =
                (int) (
                    $response->json(
                        'usage.prompt_tokens'
                    )
                    ?? 0
                );

            $completionTokens =
                (int) (
                    $response->json(
                        'usage.completion_tokens'
                    )
                    ?? 0
                );

            $totalTokens =
                (int) (
                    $response->json(
                        'usage.total_tokens'
                    )
                    ??
                    (
                        $promptTokens
                        +
                        $completionTokens
                    )
                );

            $usage =
                AIUsage::create([
                    'user_id' =>
                        $request->user()?->id
                        ?? auth()->id(),

                    'tool' =>
                        'mock_interview',

                    'status' =>
                        $status,

                    'prompt_tokens' =>
                        $promptTokens,

                    'completion_tokens' =>
                        $completionTokens,

                    'total_tokens' =>
                        $totalTokens,

                    'error_message' =>
                        $errorMessage,
                ]);

            Log::info(
                'MOCK INTERVIEW AI USAGE SAVED',
                [
                    'usage_id' =>
                        $usage->id,

                    'user_id' =>
                        $usage->user_id,

                    'status' =>
                        $usage->status,

                    'total_tokens' =>
                        $usage->total_tokens,
                ]
            );

        } catch (\Throwable $error) {

            Log::error(
                'Mock Interview AI usage save failed',
                [
                    'message' =>
                        $error->getMessage(),

                    'user_id' =>
                        $request->user()?->id,
                ]
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save Failed Usage Without Groq Response
    |--------------------------------------------------------------------------
    */

    private function saveFailedUsageWithoutResponse(
        Request $request,
        string $errorMessage
    ): void {

        try {

            AIUsage::create([
                'user_id' =>
                    $request->user()?->id
                    ?? auth()->id(),

                'tool' =>
                    'mock_interview',

                'status' =>
                    'failed',

                'prompt_tokens' =>
                    0,

                'completion_tokens' =>
                    0,

                'total_tokens' =>
                    0,

                'error_message' =>
                    $errorMessage,
            ]);

        } catch (\Throwable $error) {

            Log::error(
                'Mock Interview failed usage save error',
                [
                    'message' =>
                        $error->getMessage(),
                ]
            );
        }
    }
}