<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Throwable;

class PaymentController extends Controller
{
    /**
     * ============================================================
     * CREATE RAZORPAY ORDER
     * ============================================================
     */
    public function createOrder(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => [
                'required',
                'integer',
                'exists:subscription_plans,id',
            ],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // --------------------------------------------------------
        // FIND ACTIVE PLAN
        // --------------------------------------------------------

        $plan = SubscriptionPlan::query()
            ->where('id', $validated['plan_id'])
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Subscription plan is not available.',
            ], 404);
        }

        // --------------------------------------------------------
        // PRICE
        // --------------------------------------------------------

        $price = (float) $plan->price;

        if ($price <= 0) {
            return response()->json([
                'success' => true,
                'free_plan' => true,
                'message' =>
                    'This plan does not require payment.',
                'plan' => $plan,
            ]);
        }

        // --------------------------------------------------------
        // RAZORPAY CONFIG
        // --------------------------------------------------------

        $keyId =
            config('services.razorpay.key_id');

        $keySecret =
            config('services.razorpay.key_secret');

        if (
            empty($keyId) ||
            empty($keySecret)
        ) {
            Log::error(
                'Razorpay credentials are missing.',
                [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Razorpay configuration is missing on the server.',
            ], 500);
        }

        // --------------------------------------------------------
        // AMOUNT IN PAISE
        // --------------------------------------------------------

        $amount = (int) round(
            $price * 100
        );

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid subscription amount.',
            ], 422);
        }

        // --------------------------------------------------------
        // CREATE ORDER
        // --------------------------------------------------------

        try {
            $api = new Api(
                $keyId,
                $keySecret
            );

            $receipt =
                'studentai_' .
                $user->id .
                '_' .
                $plan->id .
                '_' .
                time();

            $order = $api->order->create([
                'receipt' => $receipt,

                'amount' => $amount,

                'currency' => 'INR',

                'notes' => [
                    'user_id' =>
                        (string) $user->id,

                    'plan_id' =>
                        (string) $plan->id,

                    'product' =>
                        'StudentAI Subscription',

                    'plan_name' =>
                        (string) $plan->name,
                ],
            ]);

            if (
                !isset($order['id']) ||
                empty($order['id'])
            ) {
                Log::error(
                    'Razorpay returned invalid order.',
                    [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'order' => $order,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Razorpay returned an invalid order.',
                ], 500);
            }

            // ----------------------------------------------------
            // SAVE PAYMENT ATTEMPT
            // ----------------------------------------------------

            $payment = Payment::create([
                'user_id' =>
                    $user->id,

                'subscription_plan_id' =>
                    $plan->id,

                'razorpay_order_id' =>
                    $order['id'],

                'amount' =>
                    $amount,

                'currency' =>
                    'INR',

                'status' =>
                    'created',
            ]);

            return response()->json([
                'success' => true,

                'message' =>
                    'Payment order created successfully.',

                'key_id' =>
                    $keyId,

                'order' => [
                    'id' =>
                        $order['id'],

                    'amount' =>
                        $amount,

                    'currency' =>
                        'INR',
                ],

                'payment_id' =>
                    $payment->id,

                'plan' =>
                    $plan,
            ], 200);

        } catch (Throwable $e) {

            Log::error(
                'Razorpay order creation failed.',
                [
                    'user_id' =>
                        $user->id,

                    'plan_id' =>
                        $plan->id,

                    'amount' =>
                        $amount,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to create payment order.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * ============================================================
     * VERIFY PAYMENT
     * ============================================================
     */
    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'razorpay_payment_id' => [
                'required',
                'string',
            ],

            'razorpay_order_id' => [
                'required',
                'string',
            ],

            'razorpay_signature' => [
                'required',
                'string',
            ],

            'plan_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        // --------------------------------------------------------
        // FIND OUR PAYMENT
        // --------------------------------------------------------

        $payment = Payment::query()
            ->where(
                'razorpay_order_id',
                $validated['razorpay_order_id']
            )
            ->where(
                'user_id',
                $user->id
            )
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Payment order not found.',
            ], 404);
        }

        // --------------------------------------------------------
        // IDEMPOTENCY
        // --------------------------------------------------------

        if (
            $payment->status === 'paid'
        ) {
            return response()->json([
                'success' => true,
                'message' =>
                    'Payment already verified.',
            ]);
        }

        // --------------------------------------------------------
        // CONFIG
        // --------------------------------------------------------

        $keyId =
            config('services.razorpay.key_id');

        $keySecret =
            config('services.razorpay.key_secret');

        if (
            empty($keyId) ||
            empty($keySecret)
        ) {
            Log::error(
                'Razorpay credentials missing during verification.',
                [
                    'user_id' =>
                        $user->id,

                    'payment_id' =>
                        $payment->id,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Razorpay configuration is missing.',
            ], 500);
        }

        // --------------------------------------------------------
        // VERIFY SIGNATURE
        // --------------------------------------------------------

        try {
            $api = new Api(
                $keyId,
                $keySecret
            );

            $api->utility
                ->verifyPaymentSignature([
                    'razorpay_order_id' =>
                        $payment->razorpay_order_id,

                    'razorpay_payment_id' =>
                        $validated[
                            'razorpay_payment_id'
                        ],

                    'razorpay_signature' =>
                        $validated[
                            'razorpay_signature'
                        ],
                ]);

        } catch (
            SignatureVerificationError $e
        ) {

            $payment->update([
                'status' =>
                    'failed',

                'failure_reason' =>
                    'Invalid Razorpay signature.',
            ]);

            Log::warning(
                'Invalid Razorpay payment signature.',
                [
                    'user_id' =>
                        $user->id,

                    'payment_id' =>
                        $payment->id,

                    'order_id' =>
                        $payment->razorpay_order_id,
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Payment verification failed.',
            ], 400);

        } catch (Throwable $e) {

            Log::error(
                'Razorpay payment verification error.',
                [
                    'user_id' =>
                        $user->id,

                    'payment_id' =>
                        $payment->id,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to verify payment.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }

        // --------------------------------------------------------
        // ACTIVATE SUBSCRIPTION
        // --------------------------------------------------------

        try {

            DB::transaction(
                function () use (
                    $payment,
                    $validated,
                    $user
                ) {

                    // --------------------------------------------
                    // GET PLAN
                    // --------------------------------------------

                    $plan =
                        SubscriptionPlan::query()
                            ->where(
                                'id',
                                $payment
                                    ->subscription_plan_id
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->first();

                    if (!$plan) {
                        throw new \RuntimeException(
                            'Subscription plan no longer exists or is inactive.'
                        );
                    }

                    // --------------------------------------------
                    // CANCEL OLD SUBSCRIPTION
                    // --------------------------------------------

                    UserSubscription::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'status',
                            'active'
                        )
                        ->update([
                            'status' =>
                                'cancelled',

                            'cancelled_at' =>
                                now(),
                        ]);

                    // --------------------------------------------
                    // START DATE
                    // --------------------------------------------

                    $startsAt = now();

                    // --------------------------------------------
                    // BILLING PERIOD
                    // --------------------------------------------

                    $billingPeriod =
                        strtolower(
                            trim(
                                (string) (
                                    $plan->billing_period ??
                                    $plan->billing_cycle ??
                                    $plan->interval ??
                                    'monthly'
                                )
                            )
                        );

                    // --------------------------------------------
                    // END DATE
                    // --------------------------------------------

                    if (
                        in_array(
                            $billingPeriod,
                            [
                                'yearly',
                                'year',
                                'annual',
                                '1_year',
                            ],
                            true
                        )
                    ) {
                        $endsAt =
                            $startsAt
                                ->copy()
                                ->addYear();
                    } else {
                        $endsAt =
                            $startsAt
                                ->copy()
                                ->addMonth();
                    }

                    // --------------------------------------------
                    // CREATE SUBSCRIPTION
                    // --------------------------------------------

                    UserSubscription::create([
                        'user_id' =>
                            $user->id,

                        'subscription_plan_id' =>
                            $plan->id,

                        'status' =>
                            'active',

                        'starts_at' =>
                            $startsAt,

                        'ends_at' =>
                            $endsAt,
                    ]);

                    // --------------------------------------------
                    // UPDATE PAYMENT
                    // --------------------------------------------

                    $payment->update([
                        'razorpay_payment_id' =>
                            $validated[
                                'razorpay_payment_id'
                            ],

                        'razorpay_signature' =>
                            $validated[
                                'razorpay_signature'
                            ],

                        'status' =>
                            'paid',

                        'paid_at' =>
                            now(),
                    ]);
                }
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Payment verified and subscription activated.',
            ]);

        } catch (Throwable $e) {

            Log::error(
                'Subscription activation failed.',
                [
                    'user_id' =>
                        $user->id,

                    'payment_id' =>
                        $payment->id,

                    'error' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Payment was verified but subscription activation failed.',

                'error' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : null,
            ], 500);
        }
    }

    /**
     * ============================================================
     * PAYMENT HISTORY
     * ============================================================
     */
    public function history(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unauthenticated.',
            ], 401);
        }

        $payments = Payment::query()
            ->with('plan')
            ->where(
                'user_id',
                $user->id
            )
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'payments' => $payments,
        ]);
    }

    /**
 * ============================================================
 * MY SUBSCRIPTION
 * ============================================================
 */
public function mySubscription(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    /*
    |--------------------------------------------------------------------------
    | Find current active subscription
    |--------------------------------------------------------------------------
    */

    $subscription = UserSubscription::query()
        ->with('plan')
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->latest('starts_at')
        ->first();

    /*
    |--------------------------------------------------------------------------
    | No active subscription
    |--------------------------------------------------------------------------
    */

    if (!$subscription) {
        return response()->json([
            'success' => true,
            'has_subscription' => false,
            'message' => 'No active subscription found.',
            'subscription' => null,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Check expiration
    |--------------------------------------------------------------------------
    */

    if (
        $subscription->ends_at &&
        now()->greaterThanOrEqualTo($subscription->ends_at)
    ) {
        $subscription->update([
            'status' => 'expired',
        ]);

        return response()->json([
            'success' => true,
            'has_subscription' => false,
            'message' => 'Your subscription has expired.',
            'subscription' => null,
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate remaining days
    |--------------------------------------------------------------------------
    */

    $remainingDays = null;

    if ($subscription->ends_at) {
        $remainingDays = max(
            0,
            now()->diffInDays(
                $subscription->ends_at,
                false
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Return subscription
    |--------------------------------------------------------------------------
    */

    return response()->json([
        'success' => true,
        'has_subscription' => true,

        'subscription' => [
            'id' => $subscription->id,

            'status' => $subscription->status,

            'starts_at' => $subscription->starts_at,

            'ends_at' => $subscription->ends_at,

            'remaining_days' => $remainingDays,

            'plan' => $subscription->plan
                ? [
                    'id' => $subscription->plan->id,

                    'name' => $subscription->plan->name,

                    'description' =>
                        $subscription->plan->description,

                    'price' =>
                        $subscription->plan->price,

                    'billing_period' =>
                        $subscription->plan->billing_period,

                    'resume_limit' =>
                        $subscription->plan->resume_limit,

                    'ai_usage_limit' =>
                        $subscription->plan->ai_usage_limit,

                    'interview_limit' =>
                        $subscription->plan->interview_limit,

                    'job_tracker_limit' =>
                        $subscription->plan->job_tracker_limit,

                    'cover_letter_limit' =>
                        $subscription->plan->cover_letter_limit,

                    'career_coach_limit' =>
                        $subscription->plan->career_coach_limit,
                ]
                : null,
        ],
    ], 200);
}
}