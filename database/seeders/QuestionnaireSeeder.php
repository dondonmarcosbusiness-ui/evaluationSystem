<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Question;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Faculty questionnaire = the instrument prescribed by CHED CMO No. 19,
 * s. 2025 (Revised Guidelines and Instruments for Evaluating Faculty Teaching
 * Effectiveness in SUCs), ANNEX A - Student Evaluation of Teachers (SET).
 *
 * Section 4.2 of the CMO states that SUCs "shall not modify or add indicators
 * to the prescribed instruments", so the wording below is reproduced verbatim:
 *
 *   Domain A  Management of Teaching and Learning   items  1-6  (weight 0.40)
 *   Domain B  Content Knowledge, Pedagogy and
 *             Technology                            items  7-11  (weight 0.33)
 *   Domain C  Commitment and Transparency           items 12-15  (weight 0.27)
 *
 * Weights are proportional to the item count so that the system's weighted
 * score reproduces the CMO computation  Rating = (Total Score / 75) x 100
 * (15 items x max 5 = 75). 40 + 33 + 27 = 100%.
 *
 * Every category and question carries a `reference` column pointing back at
 * the exact place in the memo it comes from.
 *
 * Re-seeding discards the previous, non-CMO questionnaire together with the
 * answers and evaluations attached to it: the old answers point at question
 * rows that no longer exist, and students could not resubmit because the
 * duplicate-submission guard would still see their old evaluation.
 */
class QuestionnaireSeeder extends Seeder
{
    /** @var string */
    private string $memo = 'CHED CMO No. 19, s. 2025';

    public function run(): void
    {
        $this->wipeFacultyQuestionnaire();

        $semester = $this->activeSetting('active_semester', '1st Semester');
        $academicYear = $this->activeSetting('active_academic_year', '2025-2026');

        foreach ($this->domains() as $domain) {
            $category = Category::create([
                'category_name' => $domain['category_name'],
                'reference' => sprintf('%s, Annex A - %s (Items %d-%d)', $this->memo, $domain['label'], $domain['items'][0], $domain['items'][1]),
                'weight' => $domain['weight'],
                'academic_year' => $academicYear,
                'semester' => $semester,
                'evaluatee_type' => 'faculty',
            ]);

            foreach ($domain['questions'] as $offset => $questionText) {
                $item = $domain['items'][0] + $offset;

                Question::create([
                    'category_id' => $category->id,
                    'question_text' => $questionText,
                    'reference' => sprintf('%s, Annex A, %s, Item %d', $this->memo, $domain['label'], $item),
                ]);
            }
        }
    }

    /**
     * Clear the previous questionnaire and everything derived from it.
     * Truncating in this order keeps foreign keys happy on both MySQL and
     * SQLite (the tests run on :memory:).
     */
    private function wipeFacultyQuestionnaire(): void
    {
        DB::table('evaluation_answers')->delete();
        DB::table('evaluation_questions')->delete();
        DB::table('evaluation_categories')->delete();

        DB::table('evaluations')
            ->where(function ($query) {
                $query->where('evaluatee_type', 'faculty')->orWhereNull('evaluatee_type');
            })
            ->delete();

        DB::table('users')
            ->whereNotNull('evaluations_completed_at')
            ->update([
                'evaluations_completed_at' => null,
                'evaluations_completed_semester' => null,
                'evaluations_completed_academic_year' => null,
            ]);
    }

    private function activeSetting(string $key, string $fallback): string
    {
        $value = Setting::cachedAll()->get($key);

        return is_string($value) && trim($value) !== '' ? $value : $fallback;
    }

    /**
     * ANNEX A benchmark statements, verbatim.
     *
     * @return array<int, array<string, mixed>>
     */
    private function domains(): array
    {
        return [
            [
                'label' => 'Domain A',
                'category_name' => 'Management of Teaching and Learning',
                'weight' => 0.40,
                'items' => [1, 6],
                'questions' => [
                    'Comes to class on time.',
                    'Explains learning outcomes, expectations, grading system, and various requirements of the subject/course.',
                    'Maximizes the allocated time/learning hours effectively.',
                    'Facilitates students to think critically and creatively by providing appropriate learning activities.',
                    'Guides students to learn on their own, reflect on new ideas and experiences, and make decisions in accomplishing given tasks.',
                    'Communicates constructive feedback to students for their academic growth.',
                ],
            ],
            [
                'label' => 'Domain B',
                'category_name' => 'Content Knowledge, Pedagogy and Technology',
                'weight' => 0.33,
                'items' => [7, 11],
                'questions' => [
                    'Demonstrates extensive and broad knowledge of the subject/course.',
                    'Simplifies complex ideas in the lesson for ease of understanding.',
                    'Relates the subject matter to contemporary issues and developments in the discipline and/or daily life activities.',
                    'Promotes active learning and student engagement by using appropriate teaching and learning resources including ICT tools and platforms.',
                    'Uses appropriate assessments (projects, exams, quizzes, assignments, etc.) aligned with the learning outcomes.',
                ],
            ],
            [
                'label' => 'Domain C',
                'category_name' => 'Commitment and Transparency',
                'weight' => 0.27,
                'items' => [12, 15],
                'questions' => [
                    'Recognizes and values the unique diversity and individual differences among students.',
                    'Assists students with their learning challenges during consultation hours.',
                    'Provides immediate feedback on student outputs and performance.',
                    'Provides transparent and clear criteria in rating student\'s performance.',
                ],
            ],
        ];
    }
}
