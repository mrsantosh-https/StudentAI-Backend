<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AIUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Generate Interview Questions
    |--------------------------------------------------------------------------
    */

    public function generateInterviewQuestions(Request $request)
    {
        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                'min:2',
                'max:150'
            ],
        ]);

        $role = trim($validated['role']);

        $prompt = "
You are an expert technical interviewer.

Generate exactly 5 interview questions for the role: {$role}.

Requirements:
- Suitable for fresher or junior candidates
- Include technical and practical questions
- Do not include answers
- Return only a numbered list from 1 to 5
";

        try {

            $aiResponse =
                $this->generateGroqResponse(
                    $prompt
                );

            /*
            |--------------------------------------------------------------------------
            | Save AI Usage
            |--------------------------------------------------------------------------
            */

            AIUsage::create([
                'user_id' =>
                    $request->user()?->id,

                'tool' =>
                    'ai_interview',

                'status' =>
                    'success',

                'prompt_tokens' =>
                    $aiResponse['prompt_tokens'],

                'completion_tokens' =>
                    $aiResponse['completion_tokens'],

                'total_tokens' =>
                    $aiResponse['total_tokens'],

                'error_message' =>
                    null,
            ]);

            $questions =
                $aiResponse['content'];

            return response()->json([
                'success' => true,

                'questions' =>
                    $questions,

                'result' =>
                    $questions,
            ]);

        } catch (\Throwable $error) {

            Log::error(
                'Interview questions error',
                [
                    'message' =>
                        $error->getMessage(),

                    'user_id' =>
                        $request->user()?->id,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Save Failed Usage
            |--------------------------------------------------------------------------
            */

            try {

                AIUsage::create([
                    'user_id' =>
                        $request->user()?->id,

                    'tool' =>
                        'ai_interview',

                    'status' =>
                        'failed',

                    'prompt_tokens' => 0,

                    'completion_tokens' => 0,

                    'total_tokens' => 0,

                    'error_message' =>
                        $error->getMessage(),
                ]);

            } catch (\Throwable $logError) {

                Log::error(
                    'AI Interview usage log failed',
                    [
                        'message' =>
                            $logError->getMessage(),
                    ]
                );
            }

            return response()->json([
                'success' => false,

                'message' =>
                    $error->getMessage(),
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Evaluate Interview Answer
    |--------------------------------------------------------------------------
    */

    public function evaluateInterviewAnswer(Request $request)
    {
        $validated = $request->validate([
            'question' => [
                'required',
                'string',
                'min:5'
            ],

            'answer' => [
                'required',
                'string',
                'min:2'
            ],
        ]);

        $question =
            trim(
                $validated['question']
            );

        $answer =
            trim(
                $validated['answer']
            );

        $prompt = "
Evaluate this interview answer.

Question:
{$question}

Candidate Answer:
{$answer}

Return in this format:

Score: __/10

What Was Good:
-

What Can Be Improved:
-

Better Answer:
-

Final Advice:
-
";

        try {

            $aiResponse =
                $this->generateGroqResponse(
                    $prompt
                );

            /*
            |--------------------------------------------------------------------------
            | Save AI Usage
            |--------------------------------------------------------------------------
            */

            AIUsage::create([
                'user_id' =>
                    $request->user()?->id,

                'tool' =>
                    'interview_feedback',

                'status' =>
                    'success',

                'prompt_tokens' =>
                    $aiResponse['prompt_tokens'],

                'completion_tokens' =>
                    $aiResponse['completion_tokens'],

                'total_tokens' =>
                    $aiResponse['total_tokens'],

                'error_message' =>
                    null,
            ]);

            $feedback =
                $aiResponse['content'];

            return response()->json([
                'success' => true,

                'feedback' =>
                    $feedback,

                'result' =>
                    $feedback,
            ]);

        } catch (\Throwable $error) {

            Log::error(
                'Interview feedback error',
                [
                    'message' =>
                        $error->getMessage(),

                    'user_id' =>
                        $request->user()?->id,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Save Failed Usage
            |--------------------------------------------------------------------------
            */

            try {

                AIUsage::create([
                    'user_id' =>
                        $request->user()?->id,

                    'tool' =>
                        'interview_feedback',

                    'status' =>
                        'failed',

                    'prompt_tokens' => 0,

                    'completion_tokens' => 0,

                    'total_tokens' => 0,

                    'error_message' =>
                        $error->getMessage(),
                ]);

            } catch (\Throwable $logError) {

                Log::error(
                    'Interview Feedback usage log failed',
                    [
                        'message' =>
                            $logError->getMessage(),
                    ]
                );
            }

            return response()->json([
                'success' => false,

                'message' =>
                    $error->getMessage(),
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Groq Response
    |--------------------------------------------------------------------------
    */

    private function generateGroqResponse(
        string $prompt
    ): array {

        $apiKey =
            config(
                'services.groq.key'
            );

        $model =
            config(
                'services.groq.model',
                'llama-3.1-8b-instant'
            );

        if (!$apiKey) {

            throw new \RuntimeException(
                'GROQ_API_KEY is missing in .env file.'
            );
        }

        $response =
            Http::withToken(
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
                                    'system',

                                'content' =>
                                    'You are a helpful AI interview coach.',
                            ],

                            [
                                'role' =>
                                    'user',

                                'content' =>
                                    $prompt,
                            ],
                        ],

                        'temperature' =>
                            0.6,

                        'max_tokens' =>
                            1200,
                    ]
                );

        /*
        |--------------------------------------------------------------------------
        | Groq Error
        |--------------------------------------------------------------------------
        */

        if ($response->failed()) {

            $message =
                $response->json(
                    'error.message'
                ) ??
                'Groq API request failed.';

            throw new \RuntimeException(
                $message
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Content
        |--------------------------------------------------------------------------
        */

        $content =
            $response->json(
                'choices.0.message.content'
            );

        if (
            !is_string($content) ||
            trim($content) === ''
        ) {

            throw new \RuntimeException(
                'Empty response received from AI.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Token Usage
        |--------------------------------------------------------------------------
        */

        $promptTokens =
            (int) (
                $response->json(
                    'usage.prompt_tokens'
                ) ?? 0
            );

        $completionTokens =
            (int) (
                $response->json(
                    'usage.completion_tokens'
                ) ?? 0
            );

        $totalTokens =
            (int) (
                $response->json(
                    'usage.total_tokens'
                ) ??
                (
                    $promptTokens +
                    $completionTokens
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Return AI Data
        |--------------------------------------------------------------------------
        */

        return [
            'content' =>
                trim($content),

            'prompt_tokens' =>
                $promptTokens,

            'completion_tokens' =>
                $completionTokens,

            'total_tokens' =>
                $totalTokens,
        ];
    }
}