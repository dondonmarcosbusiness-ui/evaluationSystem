<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
  protected $apiKey;
  protected $model = 'gemini-2.5-flash';

  public function __construct()
  {
    $this->apiKey = config('services.gemini.key');
    $this->model = config('services.gemini.model', 'gemini-2.5-flash');
  }

  /**
   * Analyze a single student comment for moderation, sentiment, and categorization.
   */
  public function analyzeComment(string $comment)
  {
    if (empty(trim($comment))) {
      return null;
    }

    $prompt = "
            Analyze the following student feedback for a faculty member:
            \"$comment\"
            
            Provide a JSON response with exactly these fields:
            - sentiment: 'positive', 'negative', or 'neutral'
            - category: 'teaching', 'communication', 'grading', 'behavior', or 'other'
            - moderation_status: 'valid', 'too_vague', or 'inappropriate'
            - moderation_reason: A brief explanation if inappropriate or too vague.
            - extracted_concern: The main issue mentioned (short string).
            - suggestion: An improved, constructive version of the comment that is professional, specific, and respectful.
        ";

    $result = $this->callGemini($prompt);

    if ($result['success']) {
      return $result['data'];
    }

    return null;
  }

  /**
   * Generate a summary and actionable suggestions from multiple comments.
   */
  public function generateSummary(array $comments, float $averageRating = 0, int $responseCount = 0, float $previousRating = null)
  {
    if (empty($comments)) {
      return null;
    }

    $commentsText = implode("\n- ", $comments);
    $trendText = $previousRating !== null ? ($averageRating > $previousRating ? "an increase from $previousRating" : ($averageRating < $previousRating ? "a decrease from $previousRating" : "no change from $previousRating")) : "no previous data available";

    $prompt = "
            You are an AI analytics assistant for a Faculty Evaluation System.
            
            INPUT DATA:
            - Average rating: $averageRating
            - Total responses: $responseCount
            - Previous rating: " . ($previousRating ?? 'N/A') . "
            - Trend: $trendText
            - Student comments:
            $commentsText
            
            INSTRUCTIONS:
            1. Provide a concise but insightful overview of the faculty's performance.
            2. Extract TOP 3 strengths based on recurring positive feedback.
            3. Identify TOP 3 areas for improvement (never say 'no issues').
            4. Perform sentiment analysis: Estimate percentage of Positive, Neutral, and Negative feedback.
            5. Detect recurring themes or patterns in comments for 'Key Insights'.
            6. Generate actionable and specific recommendations (avoid generic advice).
            
            Provide a JSON response with exactly these fields:
            - overview: A brief summary including rating, count, and trend.
            - strengths: Array of Top 3 strengths.
            - issues: Array of Top 3 areas for improvement.
            - sentiment: Object with 'positive', 'neutral', 'negative' (numbers as percentages).
            - key_insights: A summary of recurring patterns.
            - recommendations: Array of specific, actionable suggestions.
            - metric_insights: Array of objects with { metric: string, insight: string } for metrics like 'Overall Rating', 'Student Engagement', and 'Response Volume'.
        ";

    $result = $this->callGemini($prompt);

    if ($result['success']) {
      return $result['data'];
    }

    return null;
  }

  /**
   * Helper to call Google Gemini API.
   */
  protected function callGemini(string $prompt)
  {
    if (!$this->apiKey) {
      Log::warning('Gemini API key not found in configuration.');
      return [
        'success' => false,
        'error' => 'Gemini API key not configured.',
        'code' => 'missing_key'
      ];
    }

    try {
      $response = Http::retry(2, 1000, throw: false)
        ->timeout(30)
        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
        'contents' => [
          [
            'parts' => [
              ['text' => $prompt]
            ]
          ]
        ],
        'generationConfig' => [
          'responseMimeType' => 'application/json',
        ]
      ]);

      if ($response->successful()) {
        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $decoded = $text ? json_decode($text, true) : null;

        if (!is_array($decoded)) {
          Log::error('Gemini API returned an invalid JSON response.', [
            'model' => $this->model,
            'status' => $response->status(),
          ]);
          return [
            'success' => false,
            'error' => 'Gemini API returned invalid JSON.',
            'code' => 'invalid_response'
          ];
        }

        return [
          'success' => true,
          'data' => $decoded
        ];
      }

      $errorMessage = $response->body();
      Log::error('Gemini API error.', [
        'model' => $this->model,
        'status' => $response->status(),
        'body' => $errorMessage,
      ]);

      return [
        'success' => false,
        'error' => 'Gemini API returned an error.',
        'code' => 'provider_error'
      ];
    } catch (\Exception $e) {
      Log::error('Gemini API exception: ' . $e->getMessage());
      return [
        'success' => false,
        'error' => 'An unexpected error occurred while connecting to the AI service.',
        'code' => 'exception'
      ];
    }
  }
}
