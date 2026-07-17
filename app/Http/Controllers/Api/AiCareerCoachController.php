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

        $apiKey = env('GROQ_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'reply' => 'Groq API key Backend .env me missing hai.',
            ], 500);
        }

        $systemPrompt = "You are StudentAI Career Coach. Reply in simple Hinglish. Help students with resume, jobs, interview, roadmap, and skills.";

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $request->message,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 800,
            ]);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'reply' => 'Groq API error: ' . $response->body(),
            ], 500);
        }

        $reply = $response->json('choices.0.message.content') ?? 'AI response empty hai.';

        AiChat::create([
            'user_id' => $request->user()->id,
            'question' => $request->message,
            'answer' => $reply,
            'model' => 'groq-llama',
        ]);

        return response()->json([
            'success' => true,
            'reply' => $reply,
           
        ]);
    }

    public function history(Request $request)
    {
        $chats = AiChat::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'chats' => $chats,
        ]);
    }

    public function deleteChat(Request $request, $id)
    {
        $chat = AiChat::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $chat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chat deleted successfully',
        ]);
    }

    public function feedback(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:like,dislike',
        ]);

        $chat = AiChat::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $chat->update([
            'liked' => $request->type === 'like',
            'disliked' => $request->type === 'dislike',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback saved',
        ]);
    }
}