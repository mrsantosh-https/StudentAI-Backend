<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSubscriptionPlanController extends Controller
{
    /**
     * Get all subscription plans.
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderBy('price', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'plans' => $plans,
        ]);
    }

    /**
     * Create subscription plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'billing_period' => [
                'required',
                'string',
                'max:50',
            ],

            'resume_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'ai_usage_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'interview_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'job_tracker_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate Slug
        |--------------------------------------------------------------------------
        */

        $slug = !empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $originalSlug = $slug;
        $counter = 1;

        while (
            SubscriptionPlan::where('slug', $slug)->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $validated['slug'] = $slug;

        /*
        |--------------------------------------------------------------------------
        | Default Active Status
        |--------------------------------------------------------------------------
        */

        $validated['is_active'] =
            $validated['is_active'] ?? true;

        /*
        |--------------------------------------------------------------------------
        | Create Plan
        |--------------------------------------------------------------------------
        */

        $plan = SubscriptionPlan::create(
            $validated
        );

        $plan->loadCount('subscriptions');

        return response()->json([
            'success' => true,
            'message' => 'Subscription plan created successfully.',
            'plan' => $plan,
        ], 201);
    }

    /**
     * Get single subscription plan.
     */
    public function show(
        SubscriptionPlan $subscriptionPlan
    ): JsonResponse {
        $subscriptionPlan->loadCount(
            'subscriptions'
        );

        return response()->json([
            'success' => true,
            'plan' => $subscriptionPlan,
        ]);
    }

    /**
     * Update subscription plan.
     */
    public function update(
        Request $request,
        SubscriptionPlan $subscriptionPlan
    ): JsonResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(
                    'subscription_plans',
                    'slug'
                )->ignore(
                    $subscriptionPlan->id
                ),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'billing_period' => [
                'required',
                'string',
                'max:50',
            ],

            'resume_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'ai_usage_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'interview_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'job_tracker_limit' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Slug
        |--------------------------------------------------------------------------
        */

        if (empty($validated['slug'])) {
            $validated['slug'] =
                Str::slug(
                    $validated['name']
                );
        } else {
            $validated['slug'] =
                Str::slug(
                    $validated['slug']
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Make Slug Unique
        |--------------------------------------------------------------------------
        */

        $originalSlug =
            $validated['slug'];

        $slug =
            $originalSlug;

        $counter = 1;

        while (
            SubscriptionPlan::where(
                'slug',
                $slug
            )
                ->where(
                    'id',
                    '!=',
                    $subscriptionPlan->id
                )
                ->exists()
        ) {
            $slug =
                $originalSlug .
                '-' .
                $counter;

            $counter++;
        }

        $validated['slug'] =
            $slug;

        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        $subscriptionPlan->update(
            $validated
        );

        $subscriptionPlan->refresh();

        $subscriptionPlan->loadCount(
            'subscriptions'
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Subscription plan updated successfully.',
            'plan' => $subscriptionPlan,
        ]);
    }

    /**
     * Activate / Deactivate plan.
     */
    public function toggleStatus(
        SubscriptionPlan $subscriptionPlan
    ): JsonResponse {
        $subscriptionPlan->update([
            'is_active' =>
                !$subscriptionPlan->is_active,
        ]);

        $subscriptionPlan->refresh();

        $subscriptionPlan->loadCount(
            'subscriptions'
        );

        return response()->json([
            'success' => true,

            'message' =>
                $subscriptionPlan->is_active
                    ? 'Subscription plan activated successfully.'
                    : 'Subscription plan deactivated successfully.',

            'plan' => $subscriptionPlan,
        ]);
    }

    /**
     * Delete subscription plan.
     */
    public function destroy(
        SubscriptionPlan $subscriptionPlan
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Check Existing Subscriptions
        |--------------------------------------------------------------------------
        */

        $hasSubscriptions =
            $subscriptionPlan
                ->subscriptions()
                ->exists();

        if ($hasSubscriptions) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This plan cannot be deleted because users are subscribed to it. Deactivate the plan instead.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        $subscriptionPlan->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Subscription plan deleted successfully.',
        ]);
    }
}