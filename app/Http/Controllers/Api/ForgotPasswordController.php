<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Send OTP
    |--------------------------------------------------------------------------
    */

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = strtolower(trim($request->email));

        /*
         * Purane OTP remove
         */
        PasswordResetOtp::where(
            'email',
            $email
        )->delete();

        /*
         * Generate 6-digit OTP
         */
        $otp = random_int(100000, 999999);

        /*
         * Save OTP
         */
        $otpRecord = PasswordResetOtp::create([
            'email' => $email,

            'otp' => Hash::make(
                (string) $otp
            ),

            'expires_at' => now()->addMinutes(10),

            'verified' => false,
        ]);

        try {
            Mail::raw(
                "🎓 Welcome to StudentAI

Hello,

We received a request to reset the password for your StudentAI account.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔐 Your One-Time Password (OTP)

{$otp}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

⏳ This OTP is valid for only 10 minutes.

⚠️ Security Tips:
• Never share this OTP with anyone.
• StudentAI team will never ask for your OTP.
• If you didn't request a password reset, please ignore this email.

Thank you for using StudentAI!

🚀 StudentAI
Your Smart Learning & Career Companion

© " . date('Y') . " StudentAI. All rights reserved.",

                function ($message) use ($email) {
                    $message
                        ->to($email)
                        ->subject(
                            '🔐 StudentAI Password Reset OTP'
                        );
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'OTP aapke email par send kar diya gaya hai.',
            ]);

        } catch (\Throwable $error) {

            /*
             * Email send fail hua to OTP remove
             */
            $otpRecord->delete();

            return response()->json([
                'success' => false,
                'message' =>
                    'OTP email send nahi ho saka.',
                'error' =>
                    $error->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Verify OTP
    |--------------------------------------------------------------------------
    */

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $email = strtolower(
            trim($request->email)
        );

        $otpRecord = PasswordResetOtp::where(
            'email',
            $email
        )
            ->latest('id')
            ->first();

        /*
         * OTP record missing
         */
        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' =>
                    'OTP request nahi mila. Naya OTP mangayein.',
            ], 404);
        }

        /*
         * OTP expiry check
         */
        if (
            !$otpRecord->expires_at ||
            $otpRecord->expires_at->isPast()
        ) {
            $otpRecord->delete();

            return response()->json([
                'success' => false,
                'message' =>
                    'OTP expire ho gaya hai. Naya OTP mangayein.',
            ], 422);
        }

        /*
         * OTP check
         */
        if (
            !Hash::check(
                (string) $request->otp,
                $otpRecord->otp
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid OTP.',
            ], 422);
        }

        /*
         * OTP verified
         *
         * IMPORTANT:
         * Ab password reset karne ke liye
         * fresh 10 minutes milenge.
         */
        $otpRecord->verified = true;

        $otpRecord->expires_at =
            now()->addMinutes(10);

        $otpRecord->save();

        return response()->json([
            'success' => true,
            'message' =>
                'OTP successfully verify ho gaya.',

            'verified' => true,

            // Debug ke liye फिलहाल rehne do
            'reset_expires_at' =>
                $otpRecord
                    ->expires_at
                    ->toDateTimeString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' =>
                'required|email|exists:users,email',

            'otp' =>
                'required|digits:6',

            'password' =>
                'required|string|min:8|confirmed',
        ]);

        $email = strtolower(
            trim($request->email)
        );

        $otpRecord = PasswordResetOtp::where(
            'email',
            $email
        )
            ->latest('id')
            ->first();

        /*
         * OTP record missing
         */
        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' =>
                    'OTP record nahi mila. Naya OTP mangayein.',
            ], 404);
        }

        /*
         * IMPORTANT:
         * OTP pehle verify hona chahiye.
         */
        if (!$otpRecord->verified) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Pehle OTP verify karein.',
            ], 422);
        }

        /*
         * OTP verify hone ke baad
         * fresh 10 minute reset session.
         */
        if (
            !$otpRecord->expires_at ||
            $otpRecord->expires_at->isPast()
        ) {
            $otpRecord->delete();

            return response()->json([
                'success' => false,
                'message' =>
                    'Password reset session expire ho gaya hai. Naya OTP mangayein.',
            ], 422);
        }

        /*
         * Security ke liye OTP dobara match
         */
        if (
            !Hash::check(
                (string) $request->otp,
                $otpRecord->otp
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid OTP.',
            ], 422);
        }

        /*
         * Find user
         */
        $user = User::where(
            'email',
            $email
        )->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'User nahi mila.',
            ], 404);
        }

        /*
         * Update password
         */
        $user->password = Hash::make(
            $request->password
        );

        $user->save();

        /*
         * Existing login sessions remove
         */
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        /*
         * OTP single-use hai.
         * Reset complete hone ke baad delete.
         */
        PasswordResetOtp::where(
            'email',
            $email
        )->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Password successfully reset ho gaya.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resend OTP
    |--------------------------------------------------------------------------
    */

    public function resendOtp(Request $request)
    {
        return $this->sendOtp($request);
    }
}