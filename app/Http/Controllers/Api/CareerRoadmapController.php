<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CareerRoadmapController extends Controller
{
    public function generate(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'goal' => 'required|string|max:255',
            'currentSkills' => 'required|string|max:5000',
            'experience' => 'required|string|max:5000',
        ]);


        /*
        |--------------------------------------------------------------------------
        | User
        |--------------------------------------------------------------------------
        */

        $user = $request->user();


        /*
        |--------------------------------------------------------------------------
        | Groq Configuration
        |--------------------------------------------------------------------------
        */

        $apiKey = config('services.groq.key');

        $model = config(
            'services.groq.model',
            'llama-3.1-8b-instant'
        );


        /*
        |--------------------------------------------------------------------------
        | Check API Key
        |--------------------------------------------------------------------------
        */

        if (empty($apiKey)) {

            Log::error('StudentAI: Groq API key missing');

            return response()->json([
                'success' => false,
                'message' => 'Groq API key is missing.',
            ], 500);
        }


        /*
        |--------------------------------------------------------------------------
        | AI Prompt
        |--------------------------------------------------------------------------
        */

        $prompt = <<<PROMPT
You are StudentAI, an expert AI career coach.

Create a detailed, realistic, practical career roadmap for a student or fresher.

Student Information:

Career Goal:
{$validated['goal']}

Current Skills:
{$validated['currentSkills']}

Experience:
{$validated['experience']}

Return the roadmap in clear and simple English.

Use exactly these sections:

# 1. Career Goal

# 2. Current Skill Analysis

# 3. Learning Roadmap

# 4. Technologies to Learn

# 5. Projects to Build

# 6. Certifications

# 7. Interview Preparation

# 8. Job Preparation

# 9. Timeline

# 10. Daily/Weekly Action Plan

Requirements:

- Make the roadmap realistic for a student or fresher.
- Give practical steps.
- Clearly mention what to learn first, second and third.
- Suggest beginner, intermediate and advanced projects.
- Include technologies relevant to the student's career goal.
- Include interview preparation.
- Include job preparation.
- Give a realistic timeline.
- Avoid generic motivational content.
- Do not invent work experience.
- Keep the response structured and easy to read.
PROMPT;


        try {

            /*
            |--------------------------------------------------------------------------
            | Log Request
            |--------------------------------------------------------------------------
            */

            Log::info(
                'StudentAI: Career roadmap Groq request started',
                [
                    'user_id' => $user?->id,
                    'model' => $model,
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | Call Groq API
            |--------------------------------------------------------------------------
            */

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(60)
                ->retry(2, 1000)
                ->post(
                    'https://api.groq.com/openai/v1/chat/completions',
                    [
                        'model' => $model,

                        'messages' => [
                            [
                                'role' => 'system',

                                'content' =>
                                    'You are StudentAI, an expert AI career coach. '
                                    . 'Provide practical and structured career guidance.',
                            ],

                            [
                                'role' => 'user',

                                'content' => $prompt,
                            ],
                        ],

                        'temperature' => 0.5,

                        'max_tokens' => 2500,
                    ]
                );


            /*
            |--------------------------------------------------------------------------
            | Token Usage
            |--------------------------------------------------------------------------
            */

            $promptTokens = (int) (
                $response->json('usage.prompt_tokens') ?? 0
            );

            $completionTokens = (int) (
                $response->json('usage.completion_tokens') ?? 0
            );

            $totalTokens = (int) (
                $response->json('usage.total_tokens')
                ?? ($promptTokens + $completionTokens)
            );


            /*
            |--------------------------------------------------------------------------
            | Handle Groq API Error
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {

                $errorMessage =
                    $response->json('error.message')
                    ?? 'Groq AI request failed.';


                Log::error(
                    'StudentAI: Career roadmap Groq request failed',
                    [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'user_id' => $user?->id,
                    ]
                );


                try {

                    AIUsageService::failed(
                        'career_roadmap',
                        $errorMessage
                    );

                } catch (\Throwable $usageError) {

                    Log::error(
                        'StudentAI: AI usage save failed',
                        [
                            'message' =>
                                $usageError->getMessage(),
                        ]
                    );
                }


                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ],
                $response->status() === 429
                    ? 429
                    : 502
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Get AI Response
            |--------------------------------------------------------------------------
            */

            $roadmap = $response->json(
                'choices.0.message.content'
            );


            /*
            |--------------------------------------------------------------------------
            | Empty Response Check
            |--------------------------------------------------------------------------
            */

            if (
                !is_string($roadmap)
                || trim($roadmap) === ''
            ) {

                try {

                    AIUsageService::failed(
                        'career_roadmap',
                        'Groq returned an empty response.'
                    );

                } catch (\Throwable $usageError) {

                    Log::error(
                        'StudentAI: AI usage save failed',
                        [
                            'message' =>
                                $usageError->getMessage(),
                        ]
                    );
                }


                return response()->json([
                    'success' => false,

                    'message' =>
                        'AI returned an empty career roadmap.',
                ], 502);
            }


            /*
            |--------------------------------------------------------------------------
            | Save Successful AI Usage
            |--------------------------------------------------------------------------
            */

            try {

                AIUsageService::success(
                    'career_roadmap',
                    $promptTokens,
                    $completionTokens
                );

            } catch (\Throwable $usageError) {

                Log::error(
                    'StudentAI: AI usage save failed',
                    [
                        'message' =>
                            $usageError->getMessage(),
                    ]
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Success Log
            |--------------------------------------------------------------------------
            */

            Log::info(
                'StudentAI: Career roadmap generated successfully',
                [
                    'user_id' => $user?->id,

                    'model' => $model,

                    'prompt_tokens' =>
                        $promptTokens,

                    'completion_tokens' =>
                        $completionTokens,

                    'total_tokens' =>
                        $totalTokens,
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | Return Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'roadmap' =>
                    trim($roadmap),

                'model' =>
                    $model,
            ]);

        } catch (\Throwable $error) {


            /*
            |--------------------------------------------------------------------------
            | Exception Log
            |--------------------------------------------------------------------------
            */

            Log::error(
                'StudentAI: Career roadmap exception',
                [
                    'message' =>
                        $error->getMessage(),

                    'file' =>
                        $error->getFile(),

                    'line' =>
                        $error->getLine(),

                    'user_id' =>
                        $user?->id,
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | Save Failed Usage
            |--------------------------------------------------------------------------
            */

            try {

                AIUsageService::failed(
                    'career_roadmap',
                    $error->getMessage()
                );

            } catch (\Throwable $usageError) {

                Log::error(
                    'StudentAI: AI usage logging failed',
                    [
                        'message' =>
                            $usageError->getMessage(),
                    ]
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Return Error
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => false,

                'message' =>
                    'Career roadmap generate nahi ho saka. Please try again.',

                'error' =>
                    config('app.debug')
                        ? $error->getMessage()
                        : null,
            ], 500);
        }
    }
}