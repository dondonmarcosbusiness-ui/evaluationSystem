<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OfficeFeedback;
use App\Models\OfficeQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfficeReportController extends Controller
{
    public function dashboardStats()
    {
        try {
            $totalOffices = Office::count();
            $activeOffices = Office::where('is_active', true)->count();
            $totalFeedback = OfficeFeedback::count();
            $todayFeedback = OfficeFeedback::whereDate('submitted_at', now()->toDateString())->count();

            $satisfaction = DB::table('office_feedback_answers as fa')
                ->join('office_feedback as of', 'of.id', '=', 'fa.office_feedback_id')
                ->selectRaw('count(fa.id) as total, sum(case when fa.answer = 1 then 1 else 0 end) as yes')
                ->first();
            $yes = (int) ($satisfaction->yes ?? 0);
            $total = (int) ($satisfaction->total ?? 0);
            $satisfactionRate = $total ? round(($yes / $total) * 100, 2) : 0;

            $ranking = DB::table('offices as o')
                ->leftJoin('office_feedback as of', 'of.office_id', '=', 'o.id')
                ->leftJoin('office_feedback_answers as fa', 'fa.office_feedback_id', '=', 'of.id')
                ->select(
                    'o.id',
                    'o.name',
                    DB::raw('count(distinct of.id) as feedback_count'),
                    DB::raw('count(fa.id) as answer_count'),
                    DB::raw('sum(case when fa.answer = 1 then 1 else 0 end) as yes_count')
                )
                ->groupBy('o.id', 'o.name')
                ->get()
                ->map(function ($row) {
                    $row->satisfaction_rate = $row->answer_count ? round(($row->yes_count / $row->answer_count) * 100, 2) : 0;
                    return $row;
                })
                ->filter(fn($row) => $row->feedback_count > 0)
                ->sortByDesc('satisfaction_rate');

            $highestRated = $ranking->first() ?? null;
            $lowestRated = $ranking->last() ?? null;

            $mostVisited = Office::withCount('feedbacks')
                ->orderByDesc('feedbacks_count')
                ->first();

            $recentFeedback = OfficeFeedback::with('office', 'answers.question')
                ->orderByDesc('submitted_at')
                ->limit(5)
                ->get();

            $monthlyData = DB::table('office_feedback as of')
                ->join('office_feedback_answers as fa', 'fa.office_feedback_id', '=', 'of.id')
                ->selectRaw("DATE_FORMAT(of.submitted_at, '%Y-%m') as month, count(distinct of.id) as count, round(sum(case when fa.answer = 1 then 1 else 0 end) * 100.0 / count(fa.id), 2) as satisfaction_rate")
                ->where('of.submitted_at', '>=', now()->subMonths(11)->startOfMonth())
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $monthlyStats = collect();
            for ($i = 11; $i >= 0; $i--) {
                $key = now()->startOfMonth()->subMonths($i)->format('Y-m');
                $row = $monthlyData->get($key);
                $monthlyStats->push((object) [
                    'month' => $key,
                    'count' => (int) ($row->count ?? 0),
                    'satisfaction_rate' => (float) ($row->satisfaction_rate ?? 0),
                ]);
            }

            $satisfactionDistribution = [
                'yes' => $yes,
                'no' => $total - $yes,
            ];

            $visitorTypeDistribution = OfficeFeedback::selectRaw('visitor_type, count(*) as count')
                ->groupBy('visitor_type')
                ->pluck('count', 'visitor_type')
                ->map(fn($count) => (int) $count);

            return response()->json([
                'total_offices' => $totalOffices,
                'active_offices' => $activeOffices,
                'total_feedback' => $totalFeedback,
                'today_feedback' => $todayFeedback,
                'satisfaction_rate' => $satisfactionRate,
                'satisfaction_distribution' => $satisfactionDistribution,
                'highest_rated' => $highestRated,
                'lowest_rated' => $lowestRated,
                'most_visited' => $mostVisited,
                'recent_feedback' => $recentFeedback,
                'monthly_stats' => $monthlyStats,
                'visitor_type_distribution' => $visitorTypeDistribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Office dashboard stats error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function officeSummary(Request $request)
    {
        try {
            $query = DB::table('offices as o')
                ->leftJoin('office_feedback as of', 'of.office_id', '=', 'o.id')
                ->leftJoin('office_feedback_answers as fa', 'fa.office_feedback_id', '=', 'of.id')
                ->select(
                    'o.id',
                    'o.name',
                    DB::raw('count(distinct of.id) as feedbacks_count'),
                    DB::raw('count(fa.id) as answer_count'),
                    DB::raw('sum(case when fa.answer = 1 then 1 else 0 end) as yes_count'),
                    DB::raw('sum(case when fa.answer = 0 then 1 else 0 end) as no_count')
                )
                ->groupBy('o.id', 'o.name');

            if ($request->query('office_id')) {
                $query->where('o.id', $request->office_id);
            }

            $offices = $query->get()->map(function ($row) {
                $row->feedbacks_count = (int) $row->feedbacks_count;
                $row->yes_count = (int) $row->yes_count;
                $row->no_count = (int) $row->no_count;
                $row->satisfaction_rate = $row->answer_count ? round(($row->yes_count / $row->answer_count) * 100, 2) : 0;
                return $row;
            });

            return response()->json($offices);
        } catch (\Exception $e) {
            Log::error('Office summary error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function officeDetailedReport($id, Request $request)
    {
        try {
            $office = Office::withCount('feedbacks')->findOrFail($id);

            $base = OfficeFeedback::where('office_id', $id)
                ->when($request->query('from'), fn($q, $v) => $q->where('submitted_at', '>=', $v))
                ->when($request->query('to'), fn($q, $v) => $q->where('submitted_at', '<=', $v))
                ->when($request->query('visitor_type'), fn($q, $v) => $q->where('visitor_type', $v));

            $feedbacks = (clone $base)
                ->with('answers.question')
                ->orderByDesc('submitted_at')
                ->paginate(20);

            $ids = (clone $base)->pluck('id');

            $satisfactionDistribution = ['yes' => 0, 'no' => 0];
            $categorySatisfaction = [];
            $monthlyTrend = [];

            if ($ids->isNotEmpty()) {
                $answerStats = DB::table('office_feedback_answers')
                    ->whereIn('office_feedback_id', $ids)
                    ->selectRaw('sum(case when answer = 1 then 1 else 0 end) as yes, sum(case when answer = 0 then 1 else 0 end) as no')
                    ->first();

                $satisfactionDistribution = [
                    'yes' => (int) ($answerStats->yes ?? 0),
                    'no' => (int) ($answerStats->no ?? 0),
                ];

                $categorySatisfaction = DB::table('office_categories as c')
                    ->join('office_questions as q', 'q.category_id', '=', 'c.id')
                    ->join('office_feedback_answers as fa', 'fa.office_question_id', '=', 'q.id')
                    ->whereIn('fa.office_feedback_id', $ids)
                    ->select(
                        'c.id',
                        'c.category_name',
                        DB::raw('count(fa.id) as total'),
                        DB::raw('sum(case when fa.answer = 1 then 1 else 0 end) as yes_count'),
                        DB::raw('sum(case when fa.answer = 0 then 1 else 0 end) as no_count')
                    )
                    ->groupBy('c.id', 'c.category_name')
                    ->get()
                    ->map(fn($row) => [
                        'id' => $row->id,
                        'category_name' => $row->category_name,
                        'yes' => (int) $row->yes_count,
                        'no' => (int) $row->no_count,
                        'total' => (int) $row->total,
                        'satisfaction_rate' => $row->total ? round(($row->yes_count / $row->total) * 100, 2) : 0,
                    ]);

                $monthlyTrend = DB::table('office_feedback as of')
                    ->join('office_feedback_answers as fa', 'fa.office_feedback_id', '=', 'of.id')
                    ->whereIn('of.id', $ids)
                    ->selectRaw("DATE_FORMAT(of.submitted_at, '%Y-%m') as month, count(distinct of.id) as count, round(sum(case when fa.answer = 1 then 1 else 0 end) * 100.0 / count(fa.id), 2) as satisfaction_rate")
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();
            }

            $suggestions = (clone $base)
                ->whereNotNull('comments')
                ->where('comments', '!=', '')
                ->orderByDesc('submitted_at')
                ->limit(20)
                ->get()
                ->map(fn($fb) => [
                    'id' => $fb->id,
                    'comment' => $fb->comments,
                    'purpose_of_visit' => $fb->purpose_of_visit,
                    'gender' => $fb->gender,
                    'visitor_type' => $fb->visitor_type,
                    'date' => $fb->submitted_at?->toISOString(),
                ]);

            return response()->json([
                'office' => $office,
                'feedbacks' => $feedbacks,
                'satisfaction_distribution' => $satisfactionDistribution,
                'category_satisfaction' => $categorySatisfaction,
                'monthly_trend' => $monthlyTrend,
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            Log::error('Office detailed report error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function feedbacks($id, Request $request)
    {
        try {
            $query = OfficeFeedback::where('office_id', $id)
                ->with('answers.question')
                ->when($request->query('from') ?? $request->query('date_from'), fn($q, $v) => $q->where('submitted_at', '>=', $v))
                ->when($request->query('to') ?? $request->query('date_to'), fn($q, $v) => $q->where('submitted_at', '<=', $v))
                ->when($request->query('visitor_type'), fn($q, $v) => $q->where('visitor_type', $v))
                ->orderByDesc('submitted_at');

            return response()->json($query->paginate(15));
        } catch (\Exception $e) {
            Log::error('Office report feedbacks error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $query = OfficeFeedback::with('office', 'student', 'answers.question')
                ->when($request->query('office_id'), fn($q, $v) => $q->where('office_id', $v))
                ->when($request->query('from'), fn($q, $v) => $q->where('submitted_at', '>=', $v))
                ->when($request->query('to'), fn($q, $v) => $q->where('submitted_at', '<=', $v))
                ->when($request->query('visitor_type'), fn($q, $v) => $q->where('visitor_type', $v))
                ->orderByDesc('submitted_at');

            $questions = OfficeQuestion::orderBy('category_id')->orderBy('created_at')->get();

            $filename = 'office_feedback_' . now()->format('Y-m-d_His') . '.csv';

            return response()->streamDownload(function () use ($query, $questions) {
                $handle = fopen('php://output', 'w');
                $headers = ['Office', 'Visitor Type', 'Purpose of Visit', 'IP Address', 'Satisfaction (Yes/Total)', 'Comments', 'Device', 'Submitted At'];
                foreach ($questions as $q) {
                    $headers[] = mb_strimwidth($q->question_text, 0, 60, '…');
                }
                fputcsv($handle, $headers);

                $query->chunkById(500, function ($feedbacks) use ($handle, $questions) {
                    foreach ($feedbacks as $fb) {
                        $answers = $fb->answers->keyBy('office_question_id');
                        $yes = $fb->answers->where('answer', true)->count();
                        $row = [
                            $fb->office->name ?? '',
                            $fb->visitor_type,
                            $fb->purpose_of_visit ?? '',
                            $fb->ip_address ?? '',
                            $yes . '/' . $fb->answers->count(),
                            $fb->comments ?? '',
                            $fb->device_type ?? '',
                            $fb->submitted_at?->format('Y-m-d H:i'),
                        ];
                        foreach ($questions as $q) {
                            $a = $answers->get($q->id);
                            $row[] = $a ? ($a->answer ? 'Yes' : 'No') : '';
                        }
                        fputcsv($handle, $row);
                    }
                });

                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv']);
        } catch (\Exception $e) {
            Log::error('Office export error: ' . $e->getMessage());
            return response()->json(['message' => 'System error'], 500);
        }
    }
}
