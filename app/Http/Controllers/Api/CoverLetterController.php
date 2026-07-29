<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoverLetterController extends Controller
{
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'min:20', 'max:10000'],
        ]);

        try {
            $prompt = "
Generate a professional ATS-friendly cover letter.

Company: {$validated['company']}
Job Role: {$validated['role']}
Candidate Skills and Experience:
{$validated['details']}

Requirements:
- Use a professional tone
- Keep it concise
- Highlight relevant skills and projects
- Do not use placeholders
- Return only the cover letter
";

            $response = Http::withToken(config('services.groq.key'))
                ->timeout(60)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config(
                        'services.groq.model',
                        'llama-3.1-8b-instant'
                    ),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert professional cover letter writer.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1200,
                ]);

            if ($response->failed()) {
                Log::error('Groq cover letter error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'AI service failed to generate cover letter.',
                ], 500);
            }

            $coverLetter = data_get(
                $response->json(),
                'choices.0.message.content'
            );

            if (!$coverLetter) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI returned an empty response.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cover letter generated successfully.',
                'cover_letter' => trim($coverLetter),
            ]);
        } catch (\Throwable $error) {
            Log::error('Cover letter generation failed', [
                'message' => $error->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate cover letter.',
            ], 500);
        }
    }
}