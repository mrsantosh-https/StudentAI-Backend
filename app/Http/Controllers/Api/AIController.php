<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function generateInterviewQuestions(Request $request)
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'min:2', 'max:150'],
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
            $questions = $this->generateGroqResponse($prompt);

            return response()->json([
                'success' => true,
                'questions' => $questions,
                'result' => $questions,
            ]);
        } catch (\Throwable $error) {
            Log::error('Interview questions error', [
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    public function evaluateInterviewAnswer(Request $request)
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:5'],
            'answer' => ['required', 'string', 'min:2'],
        ]);

        $question = trim($validated['question']);
        $answer = trim($validated['answer']);

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
            $feedback = $this->generateGroqResponse($prompt);

            return response()->json([
                'success' => true,
                'feedback' => $feedback,
                'result' => $feedback,
            ]);
        } catch (\Throwable $error) {
            Log::error('Interview feedback error', [
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    private function generateGroqResponse(string $prompt): string
    {
        $apiKey = config('services.groq.key');
        $model = config(
            'services.groq.model',
            'llama-3.1-8b-instant'
        );

        if (!$apiKey) {
            throw new \RuntimeException(
                'GROQ_API_KEY is missing in .env file.'
            );
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->post(
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful AI interview coach.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.6,
                    'max_tokens' => 1200,
                ]
            );

        if ($response->failed()) {
            $message =
                $response->json('error.message') ??
                'Groq API request failed.';

            throw new \RuntimeException($message);
        }

        $content = $response->json(
            'choices.0.message.content'
        );

        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException(
                'Empty response received from AI.'
            );
        }

        return trim($content);
    }
}