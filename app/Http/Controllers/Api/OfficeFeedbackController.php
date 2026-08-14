<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OfficeFeedback;
use App\Http\Requests\StoreOfficeFeedbackRequest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OfficeFeedbackController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = OfficeFeedback::with('office', 'student', 'answers.question')
                ->when($request->query('office_id'), function ($q, $officeId) {
                    $q->where('office_id', $officeId);
                })
                ->when($request->query('visitor_type'), function ($q, $type) {
                    $q->where('visitor_type', $type);
                })
                ->when($request->query('from'), function ($q, $from) {
                    $q->where('submitted_at', '>=', $from);
                })
                ->when($request->query('to'), function ($q, $to) {
                    $q->where('submitted_at', '<=', $to);
                })
                ->orderByDesc('submitted_at');

            if ($request->query('paginate') === 'false') {
                return response()->json($query->get());
            }

            return response()->json($query->paginate(15));
        } catch (\Exception $e) {
            Log::error('Office feedback index error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function submit(StoreOfficeFeedbackRequest $request)
    {
        $validated = $request->validated();
        $office = Office::findOrFail($validated['office_id']);

        if (!$office->is_active) {
            return response()->json(['message' => 'This office is not currently accepting feedback'], 422);
        }

        // Rate limit: one feedback per office per day for students
        $studentId = null;
        if ($validated['visitor_type'] === 'student' && !empty($validated['student_number'])) {
            $studentUser = \App\Models\User::where('id_number', $validated['student_number'])->first();
            if ($studentUser) {
                $studentId = $studentUser->id;
                $todayFeedback = OfficeFeedback::where('office_id', $validated['office_id'])
                    ->where('student_id', $studentId)
                    ->whereDate('submitted_at', now()->toDateString())
                    ->exists();

                if ($todayFeedback) {
                    return response()->json(['message' => 'You have already submitted feedback for this office today'], 422);
                }
            }
        }

        $userAgent = $request->userAgent();
        $deviceType = $this->detectDevice($userAgent);

        $feedback = OfficeFeedback::create([
            'office_id' => $validated['office_id'],
            'student_id' => $studentId,
            'visitor_type' => $validated['visitor_type'],
            'gender' => $validated['gender'] ?? null,
            'visitor_name' => $validated['visitor_name'] ?? null,
            'student_number' => $validated['student_number'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'purpose_of_visit' => $validated['purpose_of_visit'] ?? null,
            'comments' => $validated['comments'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'submitted_at' => now(),
        ]);

        foreach ($validated['answers'] as $answer) {
            $feedback->answers()->create([
                'office_question_id' => $answer['question_id'],
                'answer' => (bool) $answer['answer'],
            ]);
        }

        $feedback->load('answers.question');

        AuditLogService::log('office_feedback.submitted', $feedback, [], $feedback->toArray(), $request);

        return response()->json([
            'message' => 'Feedback submitted successfully',
            'data' => $feedback,
        ], 201);
    }

    public function show($id)
    {
        try {
            $feedback = OfficeFeedback::with('office', 'student', 'answers.question')->findOrFail($id);
            return response()->json($feedback);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Not found'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            OfficeFeedback::findOrFail($id)->delete();
            return response()->json(['message' => 'Feedback deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Office feedback destroy error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function stats(Request $request)
    {
        try {
            $query = OfficeFeedback::query();

            if ($request->query('office_id')) {
                $query->where('office_id', $request->office_id);
            }
            if ($request->query('from')) {
                $query->where('submitted_at', '>=', $request->from);
            }
            if ($request->query('to')) {
                $query->where('submitted_at', '<=', $request->to);
            }

            $totalFeedback = (clone $query)->count();
            $todayFeedback = (clone $query)->whereDate('submitted_at', now()->toDateString())->count();

            $answerQuery = DB::table('office_feedback_answers as fa')
                ->join('office_feedback as of', 'of.id', '=', 'fa.office_feedback_id')
                ->selectRaw('count(fa.id) as total, sum(case when fa.answer = 1 then 1 else 0 end) as yes');

            if ($request->query('office_id')) {
                $answerQuery->where('of.office_id', $request->office_id);
            }
            if ($request->query('from')) {
                $answerQuery->where('of.submitted_at', '>=', $request->from);
            }
            if ($request->query('to')) {
                $answerQuery->where('of.submitted_at', '<=', $request->to);
            }

            $answerStats = $answerQuery->first();
            $yes = (int) ($answerStats->yes ?? 0);
            $total = (int) ($answerStats->total ?? 0);
            $satisfactionRate = $total ? round(($yes / $total) * 100, 2) : 0;

            $satisfactionDistribution = [
                'yes' => $yes,
                'no' => $total - $yes,
            ];

            $mostVisitedOffice = Office::withCount('feedbacks')
                ->orderByDesc('feedbacks_count')
                ->first();

            return response()->json([
                'total_feedback' => $totalFeedback,
                'today_feedback' => $todayFeedback,
                'satisfaction_rate' => $satisfactionRate,
                'satisfaction_distribution' => $satisfactionDistribution,
                'most_visited_office' => $mostVisitedOffice,
            ]);
        } catch (\Exception $e) {
            Log::error('Office feedback stats error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    private function detectDevice($userAgent)
    {
        if (!$userAgent) return 'unknown';
        if (preg_match('/android/i', $userAgent)) return 'android';
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) return 'ios';
        if (preg_match('/windows/i', $userAgent)) return 'windows';
        if (preg_match('/macintosh|mac os x/i', $userAgent)) return 'mac';
        if (preg_match('/linux/i', $userAgent)) return 'linux';
        return 'other';
    }
}
