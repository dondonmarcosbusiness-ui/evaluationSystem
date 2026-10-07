<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\QuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The faculty questionnaire is the instrument prescribed by CHED CMO No. 19,
 * s. 2025 (ANNEX A - Student Evaluation of Teachers). Section 4.2 forbids SUCs
 * from modifying or adding indicators, so the wording, the three domains, the
 * item count and every source reference are asserted verbatim here.
 */
class CmoQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    /** Domain name => [weight, expected statements] */
    private const DOMAINS = [
        'Management of Teaching and Learning' => [
            0.40,
            [
                'Comes to class on time.',
                'Explains learning outcomes, expectations, grading system, and various requirements of the subject/course.',
                'Maximizes the allocated time/learning hours effectively.',
                'Facilitates students to think critically and creatively by providing appropriate learning activities.',
                'Guides students to learn on their own, reflect on new ideas and experiences, and make decisions in accomplishing given tasks.',
                'Communicates constructive feedback to students for their academic growth.',
            ],
        ],
        'Content Knowledge, Pedagogy and Technology' => [
            0.33,
            [
                'Demonstrates extensive and broad knowledge of the subject/course.',
                'Simplifies complex ideas in the lesson for ease of understanding.',
                'Relates the subject matter to contemporary issues and developments in the discipline and/or daily life activities.',
                'Promotes active learning and student engagement by using appropriate teaching and learning resources including ICT tools and platforms.',
                'Uses appropriate assessments (projects, exams, quizzes, assignments, etc.) aligned with the learning outcomes.',
            ],
        ],
        'Commitment and Transparency' => [
            0.27,
            [
                'Recognizes and values the unique diversity and individual differences among students.',
                'Assists students with their learning challenges during consultation hours.',
                'Provides immediate feedback on student outputs and performance.',
                'Provides transparent and clear criteria in rating student\'s performance.',
            ],
        ],
    ];

    public function test_faculty_questionnaire_is_the_cmo_19_annex_a_instrument(): void
    {
        $this->seed(QuestionnaireSeeder::class);

        $this->assertSame(3, Category::count(), 'The CMO prescribes exactly three domains');
        $this->assertSame(15, Question::count(), 'The CMO prescribes exactly 15 benchmark statements');

        $firstItem = 1;
        foreach (self::DOMAINS as $name => [$weight, $statements]) {
            $category = Category::where('category_name', $name)->firstOrFail();

            $this->assertSame('faculty', $category->evaluatee_type);
            $this->assertEqualsWithDelta($weight, (float) $category->weight, 0.001, "{$name} weight");
            $this->assertStringContainsString('CMO No. 19, s. 2025', $category->reference);
            $this->assertStringContainsString('Annex A', $category->reference);

            $questions = $category->questions;
            $this->assertCount(count($statements), $questions);

            foreach ($statements as $offset => $text) {
                $item = $firstItem + $offset;
                $question = $questions->first(fn (Question $q) => str_ends_with((string) $q->reference, "Item {$item}"));

                $this->assertNotNull($question, "No question referenced as Annex A item {$item}");
                $this->assertSame($text, $question->question_text, "Item {$item} wording");
                $this->assertStringContainsString('CMO No. 19, s. 2025', $question->reference);
                $this->assertStringContainsString('Annex A', $question->reference);
            }

            $firstItem += count($statements);
        }

        // Weights are proportional to item count so the weighted score
        // reproduces Rating = (Total Score / 75) x 100: 15 items x 5 = 75.
        $this->assertEqualsWithDelta(1.0, Category::sum('weight'), 0.001, 'Domain weights must total 100%');
        $this->assertEqualsWithDelta(75.0, Question::count() * 5, 0.001, 'Max total score must be 75');
    }

    public function test_seeder_replaces_the_previous_questionnaire_and_its_data(): void
    {
        $facultyUser = User::create([
            'firstname' => 'Test',
            'lastname' => 'Student',
            'email' => 'legacy@test.com',
            'password' => 'password',
            'role' => 'student',
            'is_active' => true,
        ]);

        $legacyCategory = Category::create([
            'category_name' => 'Instructional Delivery',
            'weight' => 0.20,
            'academic_year' => '2025-2026',
            'semester' => '1st Semester',
            'evaluatee_type' => 'faculty',
        ]);
        $legacyQuestion = Question::create([
            'category_id' => $legacyCategory->id,
            'question_text' => 'The faculty presents lessons in a clear and organized manner.',
        ]);

        $evaluation = Evaluation::create([
            'student_id' => $facultyUser->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $facultyUser->id,
            'semester' => '1st Semester',
            'academic_year' => '2025-2026',
            'subject_code' => 'IT-SIA01',
        ]);
        Answer::create([
            'evaluation_id' => $evaluation->id,
            'question_id' => $legacyQuestion->id,
            'rating' => 4,
        ]);

        $facultyUser->evaluations_completed_at = now();
        $facultyUser->evaluations_completed_semester = '1st Semester';
        $facultyUser->evaluations_completed_academic_year = '2025-2026';
        $facultyUser->save();

        $this->seed(QuestionnaireSeeder::class);

        $this->assertDatabaseMissing('evaluation_categories', ['category_name' => 'Instructional Delivery']);
        $this->assertSame(0, Answer::count());
        $this->assertSame(15, Question::count());

        // Evaluations survive the answer wipe only as unusable shells, and the
        // duplicate-submission guard would block a re-take, so they go too.
        $this->assertSame(0, Evaluation::count());
        $this->assertNull($facultyUser->fresh()->evaluations_completed_at);
    }
}
