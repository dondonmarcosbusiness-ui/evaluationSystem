<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiService;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

class AiController extends Controller
{
  protected $aiService;

  public function __construct(AiService $aiService)
  {
    $this->aiService = $aiService;
  }

  /**
   * Endpoint for real-time comment analysis during evaluation submission.
   */
  public function analyzeComment(Request $request)
  {
    $user = $request->user();

    // Enforce 60-second cooldown for students only
    if ($user && $user->role === 'student') {
      $cacheKey = 'ai_cooldown_' . $user->id;
      $expiresAt = Cache::get($cacheKey);

      if ($expiresAt) {
        $remaining = $expiresAt - now()->timestamp;
        if ($remaining > 0) {
          return response()->json([
            'message' => 'Please wait before generating another feedback.',
            'remaining_seconds' => $remaining
          ], 429);
        }
      }
    }

    $request->validate([
      'comment' => 'required|string|min:5'
    ]);

    $analysis = $this->aiService->analyzeComment($request->comment);

    if (!$analysis) {
      return response()->json([
        'message' => 'AI Analysis is temporarily unavailable due to high demand. You can still submit your evaluation.',
        'error_type' => 'service_unavailable'
      ], 503);
    }

    // Set cooldown after successful analysis for students
    if ($user && $user->role === 'student') {
      Cache::put('ai_cooldown_' . $user->id, now()->addSeconds(60)->timestamp, 60);
    }

    return response()->json($analysis);
  }
}
