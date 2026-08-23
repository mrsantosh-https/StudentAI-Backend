<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubscriptionLimitService
{
    /**
     * ============================================================
     * GET CURRENT ACTIVE SUBSCRIPTION
     * ============================================================
     */
    public function getSubscription(User $user): ?UserSubscription
    {
        return UserSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * ============================================================
     * CHECK WHETHER USER HAS ACTIVE SUBSCRIPTION
     * ============================================================
     */
    public function hasActiveSubscription(User $user): bool
    {
        return $this->getSubscription($user) !== null;
    }

    /**
     * ============================================================
     * GET PLAN
     * ============================================================
     */
    public function getPlan(User $user)
    {
        $subscription = $this->getSubscription($user);

        if (!$subscription) {
            return null;
        }

        return $subscription->plan;
    }

    /**
     * ============================================================
     * GET PLAN LIMIT
     * ============================================================
     *
     * Supported limits:
     *
     * resume_limit
     * ai_usage_limit
     * interview_limit
     * job_tracker_limit
     */
    public function getLimit(
        User $user,
        string $limit
    ): ?int {
        $plan = $this->getPlan($user);

        if (!$plan) {
            return null;
        }

        if (!in_array($limit, [
            'resume_limit',
            'ai_usage_limit',
            'interview_limit',
            'job_tracker_limit',
        ], true)) {
            throw new RuntimeException(
                "Invalid subscription limit: {$limit}"
            );
        }

        $value = $plan->{$limit};

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * ============================================================
     * GET USED COUNT
     * ============================================================
     *
     * This method determines how much of a particular feature
     * the user has already consumed.
     */
    public function getUsedCount(
        User $user,
        string $feature
    ): int {
        switch ($feature) {

            /*
            |--------------------------------------------------------------------------
            | Resume
            |--------------------------------------------------------------------------
            */
            case 'resume':
                return $user->resumes()->count();

            /*
            |--------------------------------------------------------------------------
            | AI
            |--------------------------------------------------------------------------
            */
            case 'ai':
                return $user->aiUsages()
                    ->where('status', 'success')
                    ->count();

            /*
            |--------------------------------------------------------------------------
            | Interview
            |--------------------------------------------------------------------------
            */
            case 'interview':
                return $this->getInterviewUsage($user);

            /*
            |--------------------------------------------------------------------------
            | Job Tracker
            |--------------------------------------------------------------------------
            */
            case 'job_tracker':
                return $user->jobApplications()->count();

            default:
                throw new RuntimeException(
                    "Invalid subscription feature: {$feature}"
                );
        }
    }

    /**
     * ============================================================
     * INTERVIEW USAGE
     * ============================================================
     *
     * Change this query if your interview history table has
     * a different model/table name.
     */
    protected function getInterviewUsage(User $user): int
    {
        /*
         * If your project has an InterviewHistory model,
         * use it here.
         *
         * Example:
         *
         * return $user->interviewHistories()->count();
         *
         * For now, AI interview usage can be tracked from
         * ai_usages using tool name.
         */

        return $user->aiUsages()
            ->where('status', 'success')
            ->whereIn('tool', [
                'interview',
                'interview_questions',
                'ai_interview',
            ])
            ->count();
    }

    /**
     * ============================================================
     * CHECK LIMIT
     * ============================================================
     *
     * Returns:
     *
     * true  = user can use feature
     * false = limit reached
     */
    public function canUse(
        User $user,
        string $feature
    ): bool {
        $subscription = $this->getSubscription($user);

        /*
        |--------------------------------------------------------------------------
        | No active subscription
        |--------------------------------------------------------------------------
        */
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $limitField = $this->featureToLimitField($feature);

        $limit = $this->getLimit(
            $user,
            $limitField
        );

        /*
        |--------------------------------------------------------------------------
        | Unlimited
        |--------------------------------------------------------------------------
        |
        | If your database uses NULL for unlimited,
        | then NULL means unlimited.
        |
        */
        if ($limit === null) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Negative limit protection
        |--------------------------------------------------------------------------
        */
        if ($limit < 0) {
            return false;
        }

        $used = $this->getUsedCount(
            $user,
            $feature
        );

        return $used < $limit;
    }

    /**
     * ============================================================
     * CHECK LIMIT + THROW EXCEPTION
     * ============================================================
     *
     * Use this directly inside controllers.
     */
    public function check(
        User $user,
        string $feature
    ): void {
        $subscription = $this->getSubscription($user);

        /*
        |--------------------------------------------------------------------------
        | Subscription missing
        |--------------------------------------------------------------------------
        */
        if (!$subscription || !$subscription->plan) {
            throw new RuntimeException(
                'Active subscription required.'
            );
        }

        $limitField = $this->featureToLimitField(
            $feature
        );

        $limit = $this->getLimit(
            $user,
            $limitField
        );

        /*
        |--------------------------------------------------------------------------
        | Unlimited
        |--------------------------------------------------------------------------
        */
        if ($limit === null) {
            return;
        }

        $used = $this->getUsedCount(
            $user,
            $feature
        );

        /*
        |--------------------------------------------------------------------------
        | LIMIT REACHED
        |--------------------------------------------------------------------------
        */
        if ($used >= $limit) {

            Log::warning(
                'Subscription limit reached.',
                [
                    'user_id' => $user->id,
                    'feature' => $feature,
                    'limit' => $limit,
                    'used' => $used,
                    'plan_id' => $subscription->plan->id,
                ]
            );

            throw new RuntimeException(
                $this->getLimitMessage(
                    $feature,
                    $limit
                )
            );
        }
    }

    /**
     * ============================================================
     * GET REMAINING COUNT
     * ============================================================
     */
    public function getRemaining(
        User $user,
        string $feature
    ): ?int {
        $subscription = $this->getSubscription($user);

        if (!$subscription || !$subscription->plan) {
            return 0;
        }

        $limitField = $this->featureToLimitField(
            $feature
        );

        $limit = $this->getLimit(
            $user,
            $limitField
        );

        /*
        |--------------------------------------------------------------------------
        | NULL = unlimited
        |--------------------------------------------------------------------------
        */
        if ($limit === null) {
            return null;
        }

        $used = $this->getUsedCount(
            $user,
            $feature
        );

        return max(
            0,
            $limit - $used
        );
    }

    /**
     * ============================================================
     * GET USAGE INFORMATION
     * ============================================================
     */
    public function getUsage(
        User $user,
        string $feature
    ): array {
        $subscription = $this->getSubscription($user);

        if (!$subscription || !$subscription->plan) {
            return [
                'has_subscription' => false,
                'feature' => $feature,
                'used' => 0,
                'limit' => 0,
                'remaining' => 0,
                'unlimited' => false,
                'can_use' => false,
            ];
        }

        $limitField = $this->featureToLimitField(
            $feature
        );

        $limit = $this->getLimit(
            $user,
            $limitField
        );

        $used = $this->getUsedCount(
            $user,
            $feature
        );

        $unlimited = $limit === null;

        return [
            'has_subscription' => true,

            'feature' => $feature,

            'used' => $used,

            'limit' => $limit,

            'remaining' => $unlimited
                ? null
                : max(0, $limit - $used),

            'unlimited' => $unlimited,

            'can_use' => $unlimited
                ? true
                : $used < $limit,

            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
            ],
        ];
    }

    /**
     * ============================================================
     * FEATURE -> DATABASE LIMIT FIELD
     * ============================================================
     */
    protected function featureToLimitField(
        string $feature
    ): string {
        return match ($feature) {

            'resume' =>
                'resume_limit',

            'ai' =>
                'ai_usage_limit',

            'interview' =>
                'interview_limit',

            'job_tracker' =>
                'job_tracker_limit',

            default => throw new RuntimeException(
                "Invalid subscription feature: {$feature}"
            ),
        };
    }

    /**
     * ============================================================
     * LIMIT ERROR MESSAGE
     * ============================================================
     */
    protected function getLimitMessage(
        string $feature,
        int $limit
    ): string {
        return match ($feature) {

            'resume' =>
                "Resume limit reached. Your current plan allows {$limit} resumes.",

            'ai' =>
                "AI usage limit reached. Your current plan allows {$limit} AI usages.",

            'interview' =>
                "Interview limit reached. Your current plan allows {$limit} interviews.",

            'job_tracker' =>
                "Job tracker limit reached. Your current plan allows {$limit} job applications.",

            default =>
                "Your subscription limit has been reached.",
        };
    }
}