<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class EvaluationAnswerRepairService
{
    /**
     * Remap orphan evaluation_answers to current questionnaire questions (by stable display order).
     */
    public function repairOrphans(string $evaluateeType = 'faculty', ?string $semester = null, ?string $academicYear = null): int
    {
        $questionIds = $this->orderedQuestionIds($evaluateeType, $semester, $academicYear);
        if ($questionIds === []) {
            return 0;
        }

        $orphanRows = DB::table('evaluation_answers')
            ->leftJoin('evaluation_questions', 'evaluation_answers.question_id', '=', 'evaluation_questions.id')
            ->join('evaluations', 'evaluation_answers.evaluation_id', '=', 'evaluations.id')
            ->whereNull('evaluation_questions.id')
            ->where('evaluations.evaluatee_type', $evaluateeType)
            ->when($semester, fn ($q) => $q->where('evaluations.semester', $semester))
            ->when($academicYear, fn ($q) => $q->where('evaluations.academic_year', $academicYear))
            ->select('evaluation_answers.id', 'evaluation_answers.evaluation_id', 'evaluation_answers.created_at')
            ->orderBy('evaluation_answers.evaluation_id')
            ->orderBy('evaluation_answers.created_at')
            ->orderBy('evaluation_answers.id')
            ->get();

        if ($orphanRows->isEmpty()) {
            return 0;
        }

        $repaired = 0;
        foreach ($orphanRows->groupBy('evaluation_id') as $group) {
            if ($group->count() !== count($questionIds)) {
                continue;
            }

            foreach ($group->values() as $index => $row) {
                DB::table('evaluation_answers')
                    ->where('id', $row->id)
                    ->update(['question_id' => $questionIds[$index]]);
                $repaired++;
            }
        }

        return $repaired;
    }

    /**
     * @return list<string>
     */
    public function orderedQuestionIds(string $evaluateeType, ?string $semester, ?string $academicYear): array
    {
        $query = Category::with(['questions' => fn ($q) => $q->orderBy('created_at')->orderBy('id')])
            ->where('evaluatee_type', $evaluateeType)
            ->orderBy('created_at')
            ->orderBy('id');

        if ($semester) {
            $query->where('semester', $semester);
        }
        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }

        return $query->get()
            ->flatMap(fn ($cat) => $cat->questions->pluck('id'))
            ->values()
            ->all();
    }
}
