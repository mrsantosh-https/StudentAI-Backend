<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChat;
use App\Services\AIUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiCareerCoachController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Send AI Chat Message
    |--------------------------------------------------------------------------
    */

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $apiKey = env('GROQ_API_KEY');

        $model = env(
            'GROQ_MODEL',
            'groq/compound-mini'
        );

        if (!$apiKey) {
            AIUsageService::failed(
                'ai_career_coach',
                'GROQ_API_KEY is missing.'
            );

            return response()->json([
                'success' => false,
                'reply' => 'Groq API key backend .env file me missing hai.',
            ], 500);
        }

        $systemPrompt =
            'You are StudentAI Career Coach. '
            . 'Reply in simple Hinglish. '
            . 'Help students with resume, jobs, interview preparation, '
            . 'career roadmap, programming skills, and career guidance. '
            . 'Give practical and clear answers.';

        try {

            /*
            |--------------------------------------------------------------------------
            | Call Groq API
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(60)
                ->withToken($apiKey)
                ->acceptJson()
                ->post(
                    'https://api.groq.com/openai/v1/chat/completions',
                    [
                        'model' => $model,

                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                            [
                                'role' => 'user',
                                'content' => trim(
                                    $request->message
                                ),
                            ],
                        ],

                        'temperature' => 0.7,

                        'max_tokens' => 800,
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | Handle Groq API Error
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {

                $errorMessage =
                    $response->json('error.message')
                    ?? $response->body()
                    ?? 'Groq API request failed.';

                AIUsageService::failed(
                    'ai_career_coach',
                    $errorMessage
                );

                return response()->json([
                    'success' => false,
                    'reply' => 'Groq API error: ' . $errorMessage,
                ], $response->status());
            }

            /*
            |--------------------------------------------------------------------------
            | Get AI Reply
            |--------------------------------------------------------------------------
            */

            $reply = $response->json(
                'choices.0.message.content'
            );

            if (
                !is_string($reply)
                || trim($reply) === ''
            ) {

                AIUsageService::failed(
                    'ai_career_coach',
                    'AI response empty.'
                );

                return response()->json([
                    'success' => false,
                    'reply' => 'AI response empty hai.',
                ], 500);
            }

            $reply = trim($reply);

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

            /*
            |--------------------------------------------------------------------------
            | Save Chat
            |--------------------------------------------------------------------------
            */

            $chat = AiChat::create([
                'user_id' => $user->id,

                'question' => trim(
                    $request->message
                ),

                'answer' => $reply,

                'model' => $model,

                'liked' => false,

                'disliked' => false,
            ]);

            /*
            |--------------------------------------------------------------------------
            | AI Usage Tracking
            |--------------------------------------------------------------------------
            */

            AIUsageService::success(
                'ai_career_coach',
                $promptTokens,
                $completionTokens
            );

            /*
            |--------------------------------------------------------------------------
            | Return Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'reply' => $reply,

                'chat' => $chat,

                'usage' => [
                    'prompt_tokens' =>
                        $promptTokens,

                    'completion_tokens' =>
                        $completionTokens,

                    'total_tokens' =>
                        $promptTokens
                        + $completionTokens,
                ],
            ]);

        } catch (\Throwable $error) {

            AIUsageService::failed(
                'ai_career_coach',
                $error->getMessage()
            );

            return response()->json([
                'success' => false,

                'reply' =>
                    'Server error: '
                    . $error->getMessage(),
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Get Chat History
    |--------------------------------------------------------------------------
    */

    public function history(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $chats = AiChat::where(
            'user_id',
            $user->id
        )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'chats' => $chats,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Single Chat
    |--------------------------------------------------------------------------
    */

    public function deleteChat(
        Request $request,
        $id
    ) {

        $user = $request->user();

        $chat = AiChat::where(
            'user_id',
            $user->id
        )
            ->where('id', $id)
            ->firstOrFail();

        $chat->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Chat deleted successfully.',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Clear All Chats
    |--------------------------------------------------------------------------
    */

    public function clearAll(Request $request)
    {
        $user = $request->user();

        $deletedCount = AiChat::where(
            'user_id',
            $user->id
        )->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'All chat history cleared successfully.',

            'deleted_count' =>
                $deletedCount,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Like / Dislike Feedback
    |--------------------------------------------------------------------------
    */

    public function feedback(
        Request $request,
        $id
    ) {

        $request->validate([
            'type' =>
                'required|in:like,dislike',
        ]);

        $user = $request->user();

        $chat = AiChat::where(
            'user_id',
            $user->id
        )
            ->where('id', $id)
            ->firstOrFail();

        if ($request->type === 'like') {

            $chat->update([
                'liked' => true,
                'disliked' => false,
            ]);

        } else {

            $chat->update([
                'liked' => false,
                'disliked' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Feedback saved.',
            'chat' => $chat,
        ]);
    }
}