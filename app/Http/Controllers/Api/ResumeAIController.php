<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResumeAIController extends Controller
{
    /**
     * Analyze saved resume with Groq AI.
     */
    public function analyze(Request $request, int $id)
    {
        $user = $request->user();

        $resume = Resume::where('user_id', $user->id)
            ->findOrFail($id);

        if (!config('services.groq.key')) {
            return response()->json([
                'success' => false,
                'message' => 'Groq API key is missing.',
            ], 500);
        }

        $resumeText = $this->buildResumeText($resume);

        $prompt = <<<PROMPT
You are an expert ATS resume analyzer.

Analyze the resume below and return ONLY one valid JSON object.

Required JSON format:

{
  "ats_score": 85,
  "strengths": [
    "First resume strength",
    "Second resume strength"
  ],
  "weaknesses": [
    "First resume weakness",
    "Second resume weakness"
  ],
  "suggestions": [
    "First improvement suggestion",
    "Second improvement suggestion"
  ]
}

Rules:
- ats_score must be an integer between 0 and 100.
- strengths must be a JSON array of concise strings.
- weaknesses must be a JSON array of concise strings.
- suggestions must be a JSON array of actionable strings.
- Do not include markdown.
- Do not include explanations outside the JSON object.

Resume:

{$resumeText}
PROMPT;

        try {
            $response = $this->sendGroqRequest(
                systemPrompt: 'You are an ATS resume expert. Return strictly valid JSON.',
                userPrompt: $prompt,
                temperature: 0.2,
                maxTokens: 1200,
                jsonMode: true
            );

            if (!$response->successful()) {
                Log::error('Groq resume analysis failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'resume_id' => $resume->id,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $this->extractGroqError($response),
                ], $this->safeErrorStatus($response->status()));
            }

            $reply = $response->json('choices.0.message.content');

            if (!is_string($reply) || trim($reply) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'AI returned an empty analysis.',
                ], 502);
            }

            $analysis = $this->parseJsonResponse($reply);

            if (!$analysis) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI returned an invalid analysis format.',
                ], 502);
            }

            $validatedAnalysis = $this->validateAnalysis($analysis);

            $resume->update([
                'ats_score' => $validatedAnalysis['ats_score'],
                'strengths' => $validatedAnalysis['strengths'],
                'weaknesses' => $validatedAnalysis['weaknesses'],
                'suggestions' => $validatedAnalysis['suggestions'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Resume analyzed successfully.',
                'analysis' => $validatedAnalysis,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI analysis format was incomplete.',
                'errors' => $e->errors(),
            ], 502);
        } catch (\Throwable $e) {
            Log::error('Resume AI analysis exception', [
                'message' => $e->getMessage(),
                'resume_id' => $resume->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Resume analysis failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Generate ATS-friendly professional summary.
     */
    public function resumeSummary(Request $request)
    {
        $validated = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'education' => ['nullable', 'string', 'max:5000'],
            'skills' => ['required', 'string', 'max:5000'],
            'projects' => ['nullable', 'string', 'max:10000'],
            'experience' => ['nullable', 'string', 'max:10000'],
        ]);

        if (!config('services.groq.key')) {
            return response()->json([
                'success' => false,
                'message' => 'Groq API key is missing.',
            ], 500);
        }

        $fullName = $validated['fullName'];
        $education = $validated['education'] ?? 'Not provided';
        $skills = $validated['skills'];
        $projects = $validated['projects'] ?? 'Not provided';
        $experience = $validated['experience'] ?? 'Not provided';

        $prompt = <<<PROMPT
Write a professional ATS-friendly resume summary.

Candidate details:

Name: {$fullName}
Education: {$education}
Skills: {$skills}
Projects: {$projects}
Experience: {$experience}

Requirements:
- Write one professional paragraph.
- Use approximately 60 to 100 words.
- Mention the strongest technical skills.
- Mention relevant projects or experience when provided.
- Do not invent employers, certifications, achievements, or experience.
- Do not add headings.
- Do not use bullet points.
- Return only the final summary paragraph.
PROMPT;

        try {
            $response = $this->sendGroqRequest(
                systemPrompt: 'You are a professional ATS resume writer.',
                userPrompt: $prompt,
                temperature: 0.5,
                maxTokens: 350
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => $this->extractGroqError($response),
                ], $this->safeErrorStatus($response->status()));
            }

            $summary = $response->json('choices.0.message.content');

            if (!is_string($summary) || trim($summary) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'AI returned an empty summary.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'Resume summary generated successfully.',
                'summary' => trim($summary),
            ]);
        } catch (\Throwable $e) {
            Log::error('Resume summary exception', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Summary generation failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Improve saved resume using Groq AI.
     *
     * This method returns a preview only.
     * It does not update the database automatically.
     */
    public function improveResume(Request $request, int $id)
    {
        $user = $request->user();

        $resume = Resume::where('user_id', $user->id)
            ->findOrFail($id);

        if (!config('services.groq.key')) {
            return response()->json([
                'success' => false,
                'message' => 'Groq API key is missing.',
            ], 500);
        }

        $resumeText = $this->buildResumeText($resume);

        $prompt = <<<PROMPT
You are an expert ATS resume writer.

Improve the resume below to make it professional, clear, recruiter-friendly, and ATS-friendly.

You must improve only these sections:
- summary
- skills
- projects
- experience

Strict rules:
- Do not invent fake companies.
- Do not invent fake job experience.
- Do not invent fake certifications.
- Do not invent fake education.
- Do not invent fake achievements.
- Do not invent numbers, percentages, or metrics.
- Keep the original meaning and facts.
- Improve grammar, wording, structure, and clarity.
- Use strong action verbs where appropriate.
- Keep skills relevant and organized.
- Keep projects factual.
- If a section is empty, return an empty string for that section.

Return ONLY one valid JSON object in this exact format:

{
  "summary": "Improved professional summary",
  "skills": "Improved and organized skills",
  "projects": "Improved project descriptions",
  "experience": "Improved experience descriptions"
}

Do not include markdown.
Do not include triple backticks.
Do not include explanations outside JSON.

Resume:

{$resumeText}
PROMPT;

        try {
            $response = $this->sendGroqRequest(
                systemPrompt: 'You are a professional ATS resume writer. Return strictly valid JSON.',
                userPrompt: $prompt,
                temperature: 0.35,
                maxTokens: 1800,
                jsonMode: true
            );

            if (!$response->successful()) {
                Log::error('Groq resume improvement failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'resume_id' => $resume->id,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $this->extractGroqError($response),
                ], $this->safeErrorStatus($response->status()));
            }

            $reply = $response->json('choices.0.message.content');

            if (!is_string($reply) || trim($reply) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'AI returned an empty improved resume.',
                ], 502);
            }

            $improvedResume = $this->parseJsonResponse($reply);

            if (!$improvedResume) {
                Log::warning('Invalid improved resume JSON', [
                    'resume_id' => $resume->id,
                    'raw_response' => $reply,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'AI returned an invalid resume format. Please try again.',
                ], 502);
            }

            $validatedImprovedResume = validator($improvedResume, [
                'summary' => ['present', 'nullable', 'string', 'max:10000'],
                'skills' => ['present', 'nullable', 'string', 'max:10000'],
                'projects' => ['present', 'nullable', 'string', 'max:20000'],
                'experience' => ['present', 'nullable', 'string', 'max:20000'],
            ])->validate();

            $validatedImprovedResume = [
                'summary' => trim($validatedImprovedResume['summary'] ?? ''),
                'skills' => trim($validatedImprovedResume['skills'] ?? ''),
                'projects' => trim($validatedImprovedResume['projects'] ?? ''),
                'experience' => trim($validatedImprovedResume['experience'] ?? ''),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Resume improved successfully.',
                'improved_resume' => $validatedImprovedResume,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI improved resume format was incomplete.',
                'errors' => $e->errors(),
            ], 502);
        } catch (\Throwable $e) {
            Log::error('Resume improvement exception', [
                'message' => $e->getMessage(),
                'resume_id' => $resume->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Resume improvement failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Send request to Groq API.
     */
    private function sendGroqRequest(
        string $systemPrompt,
        string $userPrompt,
        float $temperature,
        int $maxTokens,
        bool $jsonMode = false
    ) {
        $payload = [
            'model' => config(
                'services.groq.model',
                'llama-3.1-8b-instant'
            ),

            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],

            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if ($jsonMode) {
            $payload['response_format'] = [
                'type' => 'json_object',
            ];
        }

        return Http::withToken(config('services.groq.key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->retry(2, 1000)
            ->post(
                'https://api.groq.com/openai/v1/chat/completions',
                $payload
            );
    }

    /**
     * Build resume plain text.
     */
    private function buildResumeText(Resume $resume): string
    {
        return <<<TEXT
Name: {$resume->full_name}
Email: {$resume->email}
Phone: {$resume->phone}
LinkedIn: {$resume->linkedin}
GitHub: {$resume->github}
Portfolio: {$resume->portfolio}

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
    }

    /**
     * Parse JSON and remove accidental markdown fences.
     */
    private function parseJsonResponse(string $reply): ?array
    {
        $cleanedReply = trim($reply);

        $cleanedReply = preg_replace(
            '/^```(?:json)?\s*|\s*```$/i',
            '',
            $cleanedReply
        );

        $decoded = json_decode($cleanedReply, true);

        if (
            json_last_error() === JSON_ERROR_NONE &&
            is_array($decoded)
        ) {
            return $decoded;
        }

        $start = strpos($cleanedReply, '{');
        $end = strrpos($cleanedReply, '}');

        if (
            $start === false ||
            $end === false ||
            $end < $start
        ) {
            return null;
        }

        $jsonOnly = substr(
            $cleanedReply,
            $start,
            $end - $start + 1
        );

        $decoded = json_decode($jsonOnly, true);

        return json_last_error() === JSON_ERROR_NONE &&
            is_array($decoded)
            ? $decoded
            : null;
    }

    /**
     * Validate ATS analysis.
     */
    private function validateAnalysis(array $analysis): array
    {
        $validated = validator($analysis, [
            'ats_score' => [
                'required',
                'integer',
                'between:0,100',
            ],

            'strengths' => [
                'required',
                'array',
                'min:1',
            ],

            'strengths.*' => [
                'required',
                'string',
                'max:500',
            ],

            'weaknesses' => [
                'required',
                'array',
                'min:1',
            ],

            'weaknesses.*' => [
                'required',
                'string',
                'max:500',
            ],

            'suggestions' => [
                'required',
                'array',
                'min:1',
            ],

            'suggestions.*' => [
                'required',
                'string',
                'max:500',
            ],
        ])->validate();

        return [
            'ats_score' => (int) $validated['ats_score'],

            'strengths' => array_values(
                array_map('trim', $validated['strengths'])
            ),

            'weaknesses' => array_values(
                array_map('trim', $validated['weaknesses'])
            ),

            'suggestions' => array_values(
                array_map('trim', $validated['suggestions'])
            ),
        ];
    }

    /**
     * Extract readable Groq error.
     */
    private function extractGroqError($response): string
    {
        return $response->json('error.message')
            ?? 'Groq AI request failed. Please try again.';
    }

    /**
     * Return safe HTTP error status.
     */
    private function safeErrorStatus(int $status): int
    {
        return in_array(
            $status,
            [
                400,
                401,
                403,
                404,
                422,
                429,
                500,
                502,
                503,
            ],
            true
        )
            ? $status
            : 502;
    }
}