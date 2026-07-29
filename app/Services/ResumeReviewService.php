<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ResumeReviewService
{
    public function review(array $resumeData): array
    {
        $apiKey = config('services.groq.key');
        $model = config('services.groq.model', 'llama-3.1-8b-instant');

        if (!$apiKey) {
            throw new RuntimeException('Groq API key configure nahi hai.');
        }

        $prompt = $this->buildPrompt($resumeData);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 1000)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => [
                    'type' => 'json_object',
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<'SYSTEM'
You are an expert ATS recruiter and professional resume reviewer.

Review the resume honestly and return only valid JSON.

Required JSON structure:

{
  "score": 0,
  "strengths": [],
  "weaknesses": [],
  "missing_keywords": [],
  "suggestions": [],
  "section_reviews": {
    "summary": "",
    "skills": "",
    "experience": "",
    "education": "",
    "projects": ""
  },
  "verdict": ""
}

Rules:
- score must be an integer between 0 and 100.
- strengths, weaknesses, missing_keywords and suggestions must be arrays of short strings.
- Do not return markdown.
- Do not wrap JSON in code blocks.
- Do not invent experience, education, skills or achievements.
- If a section is missing, mention it clearly.
- Give practical suggestions suitable for the candidate's experience level.
SYSTEM,
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('error.message')
                ?? 'Groq API se resume review generate nahi hua.'
            );
        }

        $content = $response->json('choices.0.message.content');

        if (!$content) {
            throw new RuntimeException('AI response empty hai.');
        }

        $review = json_decode($content, true);

        if (!is_array($review)) {
            throw new RuntimeException('AI ne valid JSON response nahi diya.');
        }

        return $this->normalizeReview($review);
    }

    private function buildPrompt(array $resumeData): string
    {
        return "Review the following resume data:\n\n"
            . json_encode(
                $resumeData,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
    }

    private function normalizeReview(array $review): array
    {
        $score = (int) ($review['score'] ?? 0);

        return [
            'score' => max(0, min(100, $score)),

            'strengths' => $this->normalizeArray(
                $review['strengths'] ?? []
            ),

            'weaknesses' => $this->normalizeArray(
                $review['weaknesses'] ?? []
            ),

            'missing_keywords' => $this->normalizeArray(
                $review['missing_keywords'] ?? []
            ),

            'suggestions' => $this->normalizeArray(
                $review['suggestions'] ?? []
            ),

            'section_reviews' => [
                'summary' => (string) (
                    $review['section_reviews']['summary'] ?? ''
                ),
                'skills' => (string) (
                    $review['section_reviews']['skills'] ?? ''
                ),
                'experience' => (string) (
                    $review['section_reviews']['experience'] ?? ''
                ),
                'education' => (string) (
                    $review['section_reviews']['education'] ?? ''
                ),
                'projects' => (string) (
                    $review['section_reviews']['projects'] ?? ''
                ),
            ],

            'verdict' => (string) ($review['verdict'] ?? ''),
        ];
    }

    private function normalizeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    fn ($item) => trim((string) $item),
                    $value
                )
            )
        );
    }
}