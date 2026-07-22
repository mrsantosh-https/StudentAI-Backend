<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiCareerCoachController extends Controller
{
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

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'reply' => 'Groq API key backend .env file me missing hai.',
            ], 500);
        }

        $systemPrompt = 'You are StudentAI Career Coach. Reply in simple Hinglish. Help students with resume, jobs, interview, roadmap, and skills.';

        try {
            $response = Http::timeout(30)
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => trim($request->message),
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 800,
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'reply' => 'Groq API error: ' . $response->body(),
                ], $response->status());
            }

            $reply = $response->json('choices.0.message.content');

            if (!$reply) {
                $reply = 'AI response empty hai.';
            }

            $chat = AiChat::create([
                'user_id' => $user->id,
                'question' => trim($request->message),
                'answer' => trim($reply),
                'model' => 'llama-3.1-8b-instant',
                'liked' => false,
                'disliked' => false,
            ]);

            return response()->json([
                'success' => true,
                'reply' => $reply,
                'chat' => $chat,
            ]);
        } catch (\Throwable $error) {
            return response()->json([
                'success' => false,
                'reply' => 'Server error: ' . $error->getMessage(),
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $chats = AiChat::where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'chats' => $chats,
        ]);
    }

    public function deleteChat(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $chat = AiChat::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $chat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chat deleted successfully.',
        ]);
    }

    public function clearAll(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $deletedCount = AiChat::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'All chat history cleared successfully.',
            'deleted_count' => $deletedCount,
        ]);
    }

    public function feedback(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:like,dislike',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $chat = AiChat::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $chat->update([
            'liked' => $request->type === 'like',
            'disliked' => $request->type === 'dislike',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback saved.',
            'chat' => $chat,
        ]);
    }
}