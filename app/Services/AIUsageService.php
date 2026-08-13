<?php

namespace App\Services;

use App\Models\AIUsage;
use Illuminate\Support\Facades\Auth;

class AIUsageService
{
    public static function success(
        string $tool,
        int $promptTokens = 0,
        int $completionTokens = 0
    ): AIUsage {
        return AIUsage::create([
            'user_id' => Auth::id(),
            'tool' => $tool,
            'status' => 'success',
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'error_message' => null,
        ]);
    }

    public static function failed(
        string $tool,
        ?string $errorMessage = null
    ): AIUsage {
        return AIUsage::create([
            'user_id' => Auth::id(),
            'tool' => $tool,
            'status' => 'failed',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'error_message' => $errorMessage,
        ]);
    }
}