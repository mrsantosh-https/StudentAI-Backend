<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AIUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CareerRoadmapController extends Controller
{
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'goal' => 'required|string|max:255',
            'currentSkills' => 'required|string',
            'experience' => 'required|string',
        ]);

        $prompt = "
Create a detailed career roadmap.

Goal: {$validated['goal']}
Current Skills: {$validated['currentSkills']}
Experience: {$validated['experience']}

Return:
- Learning Roadmap
- Technologies
- Projects
- Certifications
- Interview Preparation
- Timeline
";

        try {
            $response = Http::withToken(
                config('services.groq.key')
            )
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(60)
                ->retry(2, 1000)
                ->post(
                    'https://api.groq.com/openai/v1/chat/completions',
                    [
                        'model' => config(
                            'services.groq.model',
                            'llama-3.1-8b-instant'
                        ),

                        'messages' => [
                            [
                                'role' => 'system',
                                'content' =>
                                    'You are an expert career coach.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],

                        'temperature' => 0.6,
                        'max_tokens' => 1600,
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Token Usage
            |--------------------------------------------------------------------------
            */

            $promptTokens = (int) (
                $response->json(
                    'usage.prompt_tokens'
                ) ?? 0
            );

            $completionTokens = (int) (
                $response->json(
                    'usage.completion_tokens'
                ) ?? 0
            );

            $totalTokens = (int) (
                $response->json(
                    'usage.total_tokens'
                )
                ?? (
                    $promptTokens +
                    $completionTokens
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Groq Failed
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {
                $errorMessage =
                    $response->json(
                        'error.message'
                    )
                    ?? 'Career roadmap generation failed.';

                AIUsage::create([
                    'user_id' =>
                        $request->user()?->id,

                    'tool' =>
                        'career_roadmap',

                    'status' =>
                        'failed',

                    'prompt_tokens' =>
                        $promptTokens,

                    'completion_tokens' =>
                        $completionTokens,

                    'total_tokens' =>
                        $totalTokens,

                    'error_message' =>
                        $errorMessage,
                ]);

                Log::error(
                    'Career roadmap Groq failed',
                    [
                        'status' =>
                            $response->status(),

                        'body' =>
                            $response->body(),

                        'user_id' =>
                            $request->user()?->id,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], $response->status() === 429 ? 429 : 502);
            }

            /*
            |--------------------------------------------------------------------------
            | Roadmap
            |--------------------------------------------------------------------------
            */

            $roadmap = data_get(
                $response->json(),
                'choices.0.message.content'
            );

            if (
                !is_string($roadmap) ||
                trim($roadmap) === ''
            ) {
                AIUsage::create([
                    'user_id' =>
                        $request->user()?->id,

                    'tool' =>
                        'career_roadmap',

                    'status' =>
                        'failed',

                    'prompt_tokens' =>
                        $promptTokens,

                    'completion_tokens' =>
                        $completionTokens,

                    'total_tokens' =>
                        $totalTokens,

                    'error_message' =>
                        'AI returned an empty career roadmap.',
                ]);

                return response()->json([
                    'success' => false,
                    'message' =>
                        'AI returned an empty career roadmap.',
                ], 502);
            }

            /*
            |--------------------------------------------------------------------------
            | Save Successful Usage
            |--------------------------------------------------------------------------
            */

            AIUsage::create([
                'user_id' =>
                    $request->user()?->id,

                'tool' =>
                    'career_roadmap',

                'status' =>
                    'success',

                'prompt_tokens' =>
                    $promptTokens,

                'completion_tokens' =>
                    $completionTokens,

                'total_tokens' =>
                    $totalTokens,

                'error_message' =>
                    null,
            ]);

            return response()->json([
                'success' => true,
                'roadmap' => trim($roadmap),
            ]);

        } catch (\Throwable $error) {
            Log::error(
                'Career roadmap exception',
                [
                    'message' =>
                        $error->getMessage(),

                    'user_id' =>
                        $request->user()?->id,
                ]
            );

            try {
                AIUsage::create([
                    'user_id' =>
                        $request->user()?->id,

                    'tool' =>
                        'career_roadmap',

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
                    'Career Roadmap AI usage log failed',
                    [
                        'message' =>
                            $logError->getMessage(),
                    ]
                );
            }

            return response()->json([
                'success' => false,
                'message' =>
                    'Career roadmap generate nahi ho saka.',
            ], 500);
        }
    }
}