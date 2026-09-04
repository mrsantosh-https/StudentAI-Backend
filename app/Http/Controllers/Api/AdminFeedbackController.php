<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChat;
use Illuminate\Http\Request;

class AdminFeedbackController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Get Feedback Analytics + List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = AiChat::with('user')
            ->where(function ($query) {
                $query->where('liked', true)
                    ->orWhere('disliked', true);
            });

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Feedback Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('feedback')) {
            if ($request->feedback === 'like') {
                $query->where('liked', true);
            }

            if ($request->feedback === 'dislike') {
                $query->where('disliked', true);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Date Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('date')) {
            $query->whereDate(
                'updated_at',
                $request->date
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $feedback = $query
            ->latest('updated_at')
            ->paginate(15);

        /*
        |--------------------------------------------------------------------------
        | Analytics
        |--------------------------------------------------------------------------
        */

        $totalLikes = AiChat::where('liked', true)
            ->count();

        $totalDislikes = AiChat::where('disliked', true)
            ->count();

        $totalFeedback = $totalLikes + $totalDislikes;

        $likePercentage = $totalFeedback > 0
            ? round(
                ($totalLikes / $totalFeedback) * 100,
                2
            )
            : 0;

        $dislikePercentage = $totalFeedback > 0
            ? round(
                ($totalDislikes / $totalFeedback) * 100,
                2
            )
            : 0;

        return response()->json([
            'success' => true,

            'analytics' => [
                'total_feedback' => $totalFeedback,

                'total_likes' => $totalLikes,

                'total_dislikes' => $totalDislikes,

                'like_percentage' => $likePercentage,

                'dislike_percentage' => $dislikePercentage,
            ],

            'feedback' => $feedback,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Single Feedback
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $chat = AiChat::with('user')
            ->where(function ($query) {
                $query->where('liked', true)
                    ->orWhere('disliked', true);
            })
            ->find($id);

        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'feedback' => $chat,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Feedback
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $chat = AiChat::find($id);

        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback not found.',
            ], 404);
        }

        /*
        |----------------------------------------------------------------------
        | Remove feedback only
        |----------------------------------------------------------------------
        */

        $chat->update([
            'liked' => false,
            'disliked' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback deleted successfully.',
        ]);
    }
}