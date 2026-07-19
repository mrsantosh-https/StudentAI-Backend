<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MockInterview;
use Illuminate\Support\Facades\Http;

class MockInterviewController extends Controller
{

public function start(Request $request)
{
    $request->validate([
        'role' => 'required|string|max:100',
        'experience' => 'required|string|max:100',
    ]);

    $prompt = "
    You are an expert technical interviewer.

    Generate ONLY ONE interview question.

    Role: {$request->role}
    Experience: {$request->experience}

    Return only the question.
    ";

    $response = Http::withHeaders([
        'Authorization' => 'Bearer '.env('GROQ_API_KEY'),
        'Content-Type' => 'application/json',
    ])->post('https://api.groq.com/openai/v1/chat/completions', [

        'model' => 'llama-3.1-8b-instant',

        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ]);

    if (!$response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Groq API Error'
        ],500);
    }

    $question = $response->json('choices.0.message.content');

    $chat = MockInterview::create([
        'user_id' => auth()->id(),
        'role' => $request->role,
        'experience' => $request->experience,
        'question_no' => 1,
        'question' => $question,
    ]);

    return response()->json([
        'success' => true,
        'data' => $chat,
    ]);
}

public function answer(Request $request)
{
    $request->validate([
        'interview_id' => 'required|exists:mock_interviews,id',
        'answer' => 'required|string|min:10',
    ]);

    $interview = MockInterview::where('id', $request->interview_id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    $prompt = "
    You are an expert technical interviewer.

    Evaluate the candidate's answer.

    Role: {$interview->role}
    Experience: {$interview->experience}
    Question: {$interview->question}
    Candidate Answer: {$request->answer}

    Return valid JSON only in this format:
    {
        \"score\": 8,
        \"feedback\": \"Clear feedback here\",
        \"improvement\": \"How the candidate can improve\"
    }

    Score must be between 1 and 10.
    ";

    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
        'Content-Type' => 'application/json',
    ])->post('https://api.groq.com/openai/v1/chat/completions', [
        'model' => 'llama-3.1-8b-instant',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.3,
    ]);

    if (!$response->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Groq API se answer evaluate nahi ho saka.',
            'error' => $response->json(),
        ], 500);
    }

    $content = $response->json('choices.0.message.content');

    // Markdown code block remove karega, agar Groq ```json return kare
    $content = preg_replace('/```json|```/i', '', $content);
    $result = json_decode(trim($content), true);

    if (!is_array($result)) {
        return response()->json([
            'success' => false,
            'message' => 'AI response ka format invalid hai.',
            'raw_response' => $content,
        ], 500);
    }

    $score = max(1, min(10, (int) ($result['score'] ?? 1)));
    $feedback = $result['feedback'] ?? 'Feedback available nahi hai.';
    $improvement = $result['improvement'] ?? '';

    $fullFeedback = $feedback;

    if ($improvement) {
        $fullFeedback .= "\n\nImprovement: " . $improvement;
    }

    $interview->update([
        'answer' => $request->answer,
        'feedback' => $fullFeedback,
        'score' => $score,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Answer evaluated successfully.',
        'data' => $interview->fresh(),
    ]);
}

public function history()
{
    $history = MockInterview::where('user_id', auth()->id())
        ->latest()
        ->get();

    return response()->json([
        'success' => true,
        'data' => $history
    ]);
}
public function destroy($id)
{
    $interview = MockInterview::where('id', $id)
        ->where('user_id', auth()->id())
        ->first();

    if (!$interview) {
        return response()->json([
            'success' => false,
            'message' => 'Mock interview record not found.'
        ], 404);
    }

    $interview->delete();

    return response()->json([
        'success' => true,
        'message' => 'Mock interview history deleted successfully.'
    ]);
}
}
