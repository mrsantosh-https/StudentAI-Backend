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

        // Purane OTP delete kar do
        PasswordResetOtp::where('email', $email)->delete();

        $otp = random_int(100000, 999999);

        PasswordResetOtp::create([
            'email' => $email,
            'otp' => Hash::make((string) $otp),
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
                        $message->to($email)
                                ->subject('🔐 StudentAI Password Reset OTP');
                    }
                    );
            return response()->json([
                'success' => true,
                'message' => 'OTP aapke email par send kar diya gaya hai.',
            ]);
        } catch (\Throwable $error) {
            PasswordResetOtp::where('email', $email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP email send nahi ho saka.',
                'error' => $error->getMessage(),
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

    $email = strtolower(trim($request->email));

    $otpRecord = PasswordResetOtp::where('email', $email)
        ->orderByDesc('id')
        ->first();

    if (!$otpRecord) {
        return response()->json([
            'success' => false,
            'message' => 'OTP request nahi mila. Naya OTP mangayein.',
        ], 404);
    }

    if (now()->greaterThan($otpRecord->expires_at)) {
        $otpRecord->delete();

        return response()->json([
            'success' => false,
            'message' => 'OTP expire ho gaya hai.',
        ], 422);
    }

    if (!Hash::check((string) $request->otp, $otpRecord->otp)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid OTP.',
        ], 422);
    }

    $updated = PasswordResetOtp::where('id', $otpRecord->id)
        ->update([
            'verified' => 1,
        ]);

    $otpRecord = PasswordResetOtp::find($otpRecord->id);

    return response()->json([
        'success' => true,
        'message' => 'OTP successfully verify ho gaya.',
        'updated_rows' => $updated,
        'verified' => (bool) $otpRecord->verified,
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
        'email' => 'required|email|exists:users,email',
        'otp' => 'required|digits:6',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $email = strtolower(trim($request->email));

    $otpRecord = PasswordResetOtp::where('email', $email)
        ->orderByDesc('id')
        ->first();

    if (!$otpRecord) {
        return response()->json([
            'success' => false,
            'message' => 'OTP record nahi mila. Naya OTP mangayein.',
        ], 404);
    }

    if (now()->greaterThan($otpRecord->expires_at)) {
        $otpRecord->delete();

        return response()->json([
            'success' => false,
            'message' => 'OTP expire ho gaya hai. Naya OTP mangayein.',
        ], 422);
    }

    // OTP ko reset ke samay dobara verify karo
    if (!Hash::check((string) $request->otp, $otpRecord->otp)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid OTP.',
        ], 422);
    }

    $user = User::where('email', $email)->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User nahi mila.',
        ], 404);
    }

    $user->password = Hash::make($request->password);
    $user->save();

    // Sabhi existing login tokens remove honge
    $user->tokens()->delete();

    // Password reset ke baad OTP delete
    PasswordResetOtp::where('email', $email)->delete();

    return response()->json([
        'success' => true,
        'message' => 'Password successfully reset ho gaya.',
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