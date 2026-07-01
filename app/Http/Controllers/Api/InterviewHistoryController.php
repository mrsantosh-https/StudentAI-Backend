<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InterviewHistory;

class InterviewHistoryController extends Controller
{
    // Save Interview
    public function store(Request $request)
    {
        $request->validate([
            'role' => 'required',
            'question' => 'required',
            'answer' => 'required',
            'feedback' => 'required',
            'score' => 'nullable|integer',
        ]);

        $history = InterviewHistory::create([
            'user_id' => auth()->id(),
            'role' => $request->role,
            'question' => $request->question,
            'answer' => $request->answer,
            'feedback' => $request->feedback,
            'score' => $request->score,
        ]);

        return response()->json([
            'message' => 'Interview saved successfully',
            'data' => $history,
        ], 201);
    }

    // Get User Interview History
    public function index()
    {
        return InterviewHistory::where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    // Delete Interview
    public function destroy($id)
    {
        $history = InterviewHistory::where('user_id', auth()->id())
            ->findOrFail($id);

        $history->delete();

        return response()->json([
            'message' => 'Interview deleted successfully',
        ]);
    }
}