<?php

// namespace App\Http\Controllers;

// use App\Models\SubscriptionPlan;
// use App\Models\User;
// use App\Models\UserSubscription;
// use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Str;

// class AdminUserSubscriptionController extends Controller
// {
//     /**
//      * GET /api/admin/user-subscriptions
//      *
//      * Get paginated users with their latest subscription.
//      */
//     public function index(Request $request): JsonResponse
//     {
//         $perPage = min(
//             max((int) $request->input('per_page', 10), 1),
//             100
//         );

//         $query = User::query()
//             ->with([
//                 'subscriptions' => function ($query) {
//                     $query
//                         ->with('plan')
//                         ->latest('id');
//                 },
//             ])
//             ->orderByDesc('id');

//         /*
//         |--------------------------------------------------------------------------
//         | Search
//         |--------------------------------------------------------------------------
//         */

//         if ($request->filled('search')) {
//             $search = trim($request->input('search'));

//             $query->where(function ($q) use ($search) {
//                 $q->where('name', 'like', "%{$search}%")
//                     ->orWhere('email', 'like', "%{$search}%");

//                 if (is_numeric($search)) {
//                     $q->orWhere('id', (int) $search);
//                 }
//             });
//         }

//         /*
//         |--------------------------------------------------------------------------
//         | Subscription Status Filter
//         |--------------------------------------------------------------------------
//         */

//         $status = $request->input('subscription_status');

//         if ($status === 'none') {
//             $query->whereDoesntHave('subscriptions');
//         }

//         if ($status === 'active') {
//             $query->whereHas('subscriptions', function ($q) {
//                 $q->where('status', 'active')
//                     ->where(function ($q) {
//                         $q->whereNull('ends_at')
//                             ->orWhere('ends_at', '>', now());
//                     });
//             });
//         }

//         if ($status === 'cancelled') {
//             $query->whereHas('subscriptions', function ($q) {
//                 $q->where('status', 'cancelled');
//             });
//         }

//         if ($status === 'inactive') {
//             $query->whereHas('subscriptions', function ($q) {
//                 $q->where(function ($q) {
//                     $q->where('status', '!=', 'active')
//                         ->orWhere(function ($q) {
//                             $q->whereNotNull('ends_at')
//                                 ->where('ends_at', '<=', now());
//                         });
//                 });
//             });
//         }

//         /*
//         |--------------------------------------------------------------------------
//         | Pagination
//         |--------------------------------------------------------------------------
//         */

//         $users = $query->paginate($perPage);

//         /*
//         |--------------------------------------------------------------------------
//         | Transform Response
//         |--------------------------------------------------------------------------
//         */

//         $users->getCollection()->transform(function (User $user) {

//             $subscription = $user->subscriptions->first();

//             return [
//                 'id' => $user->id,
//                 'name' => $user->name,
//                 'email' => $user->email,

//                 'subscription' => $subscription
//                     ? [
//                         'id' => $subscription->id,

//                         'user_id' => $subscription->user_id,

//                         'subscription_plan_id' =>
//                             $subscription->subscription_plan_id,

//                         'status' => $subscription->status,

//                         'starts_at' => $subscription->starts_at,

//                         'ends_at' => $subscription->ends_at,

//                         'cancelled_at' =>
//                             $subscription->cancelled_at,

//                         'is_active' =>
//                             $subscription->isActive(),

//                         'plan' => $subscription->plan,
//                     ]
//                     : null,
//             ];
//         });

//         return response()->json([
//             'success' => true,
//             'users' => $users,
//         ]);
//     }

//     /**
//      * GET /api/admin/user-subscriptions/{userSubscription}
//      *
//      * Get one subscription.
//      */
//     public function show(
//         UserSubscription $userSubscription
//     ): JsonResponse {
//         $userSubscription->load([
//             'user',
//             'plan',
//         ]);

//         return response()->json([
//             'success' => true,
//             'subscription' => $userSubscription,
//         ]);
//     }

//     /**
//      * POST /api/admin/users/{user}/subscription
//      *
//      * Assign subscription to user.
//      *
//      * This is the main method.
//      */
//     public function store(
//         Request $request,
//         User $user
//     ): JsonResponse {
//         return $this->assignSubscription(
//             $request,
//             $user
//         );
//     }

//     /**
//      * POST /api/admin/users/{user}/subscription/assign
//      *
//      * Compatibility method.
//      *
//      * Your frontend/old route may call assign().
//      */
//     public function assign(
//         Request $request,
//         User $user
//     ): JsonResponse {
//         return $this->assignSubscription(
//             $request,
//             $user
//         );
//     }

//     /**
//      * Common subscription assignment logic.
//      */
//     private function assignSubscription(
//         Request $request,
//         User $user
//     ): JsonResponse {

//         $validated = $request->validate([
//             'plan_id' => [
//                 'required',
//                 'integer',
//                 'exists:subscription_plans,id',
//             ],
//         ]);

//         /*
//         |--------------------------------------------------------------------------
//         | Find Active Plan
//         |--------------------------------------------------------------------------
//         */

//         $plan = SubscriptionPlan::query()
//             ->where('id', $validated['plan_id'])
//             ->where('is_active', true)
//             ->first();

//         if (!$plan) {
//             return response()->json([
//                 'success' => false,
//                 'message' =>
//                     'Selected subscription plan is not active.',
//             ], 422);
//         }

//         /*
//         |--------------------------------------------------------------------------
//         | Assign Subscription
//         |--------------------------------------------------------------------------
//         */

//         try {

//             $subscription = DB::transaction(function () use (
//                 $user,
//                 $plan
//             ) {

//                 /*
//                 |--------------------------------------------------------------------------
//                 | Cancel Existing Active Subscription
//                 |--------------------------------------------------------------------------
//                 */

//                 UserSubscription::query()
//                     ->where('user_id', $user->id)
//                     ->where('status', 'active')
//                     ->update([
//                         'status' => 'cancelled',
//                         'cancelled_at' => now(),
//                         'ends_at' => now(),
//                     ]);

//                 /*
//                 |--------------------------------------------------------------------------
//                 | Start New Subscription
//                 |--------------------------------------------------------------------------
//                 */

//                 $startsAt = now();

//                 $endsAt = $this->calculateEndDate(
//                     $startsAt,
//                     $plan->billing_period
//                 );

//                 return UserSubscription::create([
//                     'user_id' =>
//                         $user->id,

//                     'subscription_plan_id' =>
//                         $plan->id,

//                     'status' =>
//                         'active',

//                     'starts_at' =>
//                         $startsAt,

//                     'ends_at' =>
//                         $endsAt,

//                     'cancelled_at' =>
//                         null,
//                 ]);
//             });

//             $subscription->load([
//                 'user',
//                 'plan',
//             ]);

//             return response()->json([
//                 'success' => true,

//                 'message' =>
//                     'Subscription assigned successfully.',

//                 'subscription' =>
//                     $subscription,
//             ], 201);

//         } catch (\Throwable $e) {

//             report($e);

//             return response()->json([
//                 'success' => false,

//                 'message' =>
//                     'Subscription save nahi ho saki.',

//                 'error' =>
//                     config('app.debug')
//                         ? $e->getMessage()
//                         : null,
//             ], 500);
//         }
//     }

//     /**
//      * PATCH /api/admin/user-subscriptions/{userSubscription}/cancel
//      *
//      * Cancel subscription.
//      */
//     public function cancel(
//         UserSubscription $userSubscription
//     ): JsonResponse {

//         if ($userSubscription->status === 'cancelled') {
//             return response()->json([
//                 'success' => false,

//                 'message' =>
//                     'Subscription already cancelled.',
//             ], 422);
//         }

//         $userSubscription->update([
//             'status' => 'cancelled',

//             'cancelled_at' => now(),

//             'ends_at' => now(),
//         ]);

//         $userSubscription->load([
//             'user',
//             'plan',
//         ]);

//         return response()->json([
//             'success' => true,

//             'message' =>
//                 'Subscription cancelled successfully.',

//             'subscription' =>
//                 $userSubscription,
//         ]);
//     }

//     /**
//      * DELETE /api/admin/user-subscriptions/{userSubscription}
//      *
//      * Delete subscription.
//      */
//     public function destroy(
//         UserSubscription $userSubscription
//     ): JsonResponse {

//         $userSubscription->delete();

//         return response()->json([
//             'success' => true,

//             'message' =>
//                 'Subscription deleted successfully.',
//         ]);
//     }

//     /**
//      * Calculate subscription end date.
//      */
//     private function calculateEndDate(
//         $startsAt,
//         ?string $billingPeriod
//     ) {
//         $period = Str::lower(
//             trim($billingPeriod ?? 'monthly')
//         );

//         return match ($period) {

//             'daily',
//             'day' =>
//                 $startsAt->copy()->addDay(),

//             'weekly',
//             'week' =>
//                 $startsAt->copy()->addWeek(),

//             'monthly',
//             'month' =>
//                 $startsAt->copy()->addMonth(),

//             'quarterly',
//             'quarter' =>
//                 $startsAt->copy()->addMonths(3),

//             'yearly',
//             'year' =>
//                 $startsAt->copy()->addYear(),

//             'lifetime' =>
//                 null,

//             default =>
//                 $startsAt->copy()->addMonth(),
//         };
//     }
// }