<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Str;
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
    $sessionId = (string) Str::uuid();
    $chat = MockInterview::create([
        'user_id' => auth()->id(),
        'interview_session_id' => $sessionId,
        'role' => $request->role,
        'experience' => $request->experience,
        'question_no' => 1,
        'question' => $question,
        'is_completed' => false,
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

    // Same question ko dobara submit hone se roke
    if (!empty($interview->answer)) {
        return response()->json([
            'success' => false,
            'message' => 'Is question ka answer pehle hi submit ho chuka hai.',
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Answer evaluate karo
    |--------------------------------------------------------------------------
    */

    $evaluationPrompt = "
    You are an expert technical interviewer.

    Evaluate the candidate's answer.

    Role: {$interview->role}
    Experience: {$interview->experience}
    Question: {$interview->question}
    Candidate Answer: {$request->answer}

    Return valid JSON only in this exact format:

    {
        \"score\": 8,
        \"feedback\": \"Clear and useful feedback\",
        \"improvement\": \"How the candidate can improve\"
    }

    Important rules:
    - Score must be between 1 and 10.
    - Do not return markdown.
    - Do not return code blocks.
    - Return JSON only.
    ";

    $evaluationResponse = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
        'Content-Type' => 'application/json',
    ])->post(
        'https://api.groq.com/openai/v1/chat/completions',
        [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $evaluationPrompt,
                ],
            ],
            'temperature' => 0.3,
        ]
    );

    if (!$evaluationResponse->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Groq API se answer evaluate nahi ho saka.',
            'error' => $evaluationResponse->json(),
        ], 500);
    }

    $content = $evaluationResponse->json(
        'choices.0.message.content'
    );

    $content = preg_replace(
        '/```json|```/i',
        '',
        (string) $content
    );

    $evaluation = json_decode(trim($content), true);

    if (!is_array($evaluation)) {
        return response()->json([
            'success' => false,
            'message' => 'AI evaluation response ka format invalid hai.',
            'raw_response' => $content,
        ], 500);
    }

    $score = max(
        1,
        min(5, (int) ($evaluation['score'] ?? 1))
    );

    $feedback = $evaluation['feedback']
        ?? 'Feedback available nahi hai.';

    $improvement = $evaluation['improvement'] ?? '';

    $fullFeedback = $feedback;

    if (!empty($improvement)) {
        $fullFeedback .= "\n\nImprovement: " . $improvement;
    }

    $interview->update([
        'answer' => trim($request->answer),
        'feedback' => $fullFeedback,
        'score' => $score,
    ]);

    $evaluatedInterview = $interview->fresh();

    /*
    |--------------------------------------------------------------------------
    | Question 10 complete hone par interview finish
    |--------------------------------------------------------------------------
    */

    if ((int) $interview->question_no >= 5) {
        $interview->update([
            'is_completed' => true,
        ]);

        $sessionRecords = MockInterview::where(
            'user_id',
            auth()->id()
        )
            ->where(
                'interview_session_id',
                $interview->interview_session_id
            )
            ->get();

        MockInterview::where(
            'user_id',
            auth()->id()
        )
            ->where(
                'interview_session_id',
                $interview->interview_session_id
            )
            ->update([
                'is_completed' => true,
            ]);

        $averageScore = round(
            (float) $sessionRecords
                ->whereNotNull('score')
                ->avg('score'),
            1
        );

        return response()->json([
            'success' => true,
            'message' => 'Mock interview completed successfully.',
            'completed' => true,
            'data' => $interview->fresh(),
            'average_score' => $averageScore,
            'total_questions' => 5,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Previous questions nikalo taaki duplicate question na aaye
    |--------------------------------------------------------------------------
    */

    $previousQuestions = MockInterview::where(
        'user_id',
        auth()->id()
    )
        ->where(
            'interview_session_id',
            $interview->interview_session_id
        )
        ->orderBy('question_no')
        ->pluck('question')
        ->filter()
        ->values()
        ->toArray();

    $previousQuestionsText = collect($previousQuestions)
        ->map(
            fn ($question, $index) =>
                ($index + 1) . '. ' . $question
        )
        ->implode("\n");

    $nextQuestionNo = (int) $interview->question_no + 1;

    /*
    |--------------------------------------------------------------------------
    | Next question generate karo
    |--------------------------------------------------------------------------
    */

    $nextQuestionPrompt = "
    You are an expert technical interviewer.

    Generate exactly ONE next interview question.

    Role: {$interview->role}
    Experience level: {$interview->experience}
    Current question number: {$nextQuestionNo} of 10

    Questions already asked:
    {$previousQuestionsText}

    Important rules:
    - Do not repeat any previous question.
    - Keep the question suitable for the selected role.
    - Keep the difficulty suitable for the experience level.
    - Return only the question.
    - Do not include numbering.
    - Do not include explanation.
    ";

    $nextQuestionResponse = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
        'Content-Type' => 'application/json',
    ])->post(
        'https://api.groq.com/openai/v1/chat/completions',
        [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $nextQuestionPrompt,
                ],
            ],
            'temperature' => 0.7,
        ]
    );

    if (!$nextQuestionResponse->successful()) {
        return response()->json([
            'success' => false,
            'message' => 'Next interview question generate nahi ho saka.',
            'error' => $nextQuestionResponse->json(),
        ], 500);
    }

    $nextQuestion = trim(
        (string) $nextQuestionResponse->json(
            'choices.0.message.content'
        )
    );

    if (empty($nextQuestion)) {
        return response()->json([
            'success' => false,
            'message' => 'AI ne next question return nahi kiya.',
        ], 500);
    }

    $nextInterview = MockInterview::create([
        'user_id' => auth()->id(),
        'interview_session_id' =>
            $interview->interview_session_id,
        'role' => $interview->role,
        'experience' => $interview->experience,
        'question_no' => $nextQuestionNo,
        'question' => $nextQuestion,
        'is_completed' => false,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Answer evaluated successfully.',
        'completed' => false,
        'previous_result' => $evaluatedInterview,
        'next_question' => $nextInterview,
        'current_question' => $nextQuestionNo,
        'total_questions' => 5,
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
