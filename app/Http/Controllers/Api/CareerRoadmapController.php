<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $response = Http::withToken(config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert career coach.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        $roadmap = data_get(
            $response->json(),
            'choices.0.message.content'
        );

        return response()->json([
            'success' => true,
            'roadmap' => $roadmap,
        ]);
    }
}