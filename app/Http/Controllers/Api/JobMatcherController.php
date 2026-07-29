<?php

namespace App\Http\Controllers\Api;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JobMatcherController extends Controller
{
    public function match(Request $request)
    {
        $validated = $request->validate([
            'resume_id' => [
                'required',
                'integer',
                'exists:resumes,id',
            ],

            'job_description' => [
                'required',
                'string',
                'min:20',
                'max:20000',
            ],
        ]);

        $user = $request->user();

        $resume = Resume::where('id', $validated['resume_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $apiKey = config('services.groq.key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Groq API key is missing.',
            ], 500);
        }

        $resumeText = <<<TEXT
Name: {$resume->full_name}

Professional Summary:
{$resume->summary}

Education:
{$resume->education}

Skills:
{$resume->skills}

Projects:
{$resume->projects}

Experience:
{$resume->experience}
TEXT;

        $jobDescription = $validated['job_description'];

        $prompt = <<<PROMPT
You are an expert ATS resume and job-description matcher.

Compare the candidate resume with the provided job description.

Resume:

{$resumeText}

Job Description:

{$jobDescription}

Return the response exactly in this readable format:

Match Score: __/100

Matching Skills:
- Skill one
- Skill two

Missing Skills:
- Skill one
- Skill two

Resume Improvement Tips:
- Actionable suggestion one
- Actionable suggestion two

Final Verdict:
Write a concise final assessment.

Rules:
- Match Score must be between 0 and 100.
- Use only information available in the resume and job description.
- Do not invent skills or experience.
- Clearly mention skills missing from the resume.
- Keep suggestions practical and ATS-friendly.
- Do not use markdown tables.
PROMPT;

        try {
            $response = Http::withToken($apiKey)
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
                                    'You are an expert ATS job matcher.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],

                        'temperature' => 0.25,
                        'max_tokens' => 1200,
                    ]
                );

            if (!$response->successful()) {
                Log::error('Groq job matcher failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'user_id' => $user->id,
                    'resume_id' => $resume->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' =>
                        $response->json('error.message')
                        ?? 'AI job matching failed.',
                ], $response->status() === 429 ? 429 : 502);
            }

            $result = $response->json(
                'choices.0.message.content'
            );

            if (!is_string($result) || trim($result) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'AI returned an empty result.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' =>
                    'Resume matched with job description successfully.',
                'result' => trim($result),
            ]);
        } catch (\Throwable $error) {
            Log::error('Job matcher exception', [
                'message' => $error->getMessage(),
                'user_id' => $user->id,
                'resume_id' => $resume->id,
            ]);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to match the job description. Please try again.',
            ], 500);
        }
    }
}