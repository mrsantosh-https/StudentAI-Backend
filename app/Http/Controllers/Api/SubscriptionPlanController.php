<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends Controller
{
    /**
     * Get all active subscription plans.
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'plans' => $plans,
        ]);
    }
}