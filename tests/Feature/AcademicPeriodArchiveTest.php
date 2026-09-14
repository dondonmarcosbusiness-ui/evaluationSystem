<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Category;
use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\Question;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AcademicPeriodArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'firstname' => 'Test',
            'lastname' => ucfirst($role),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function seedSettings(array $overrides = []): void
    {
        $defaults = [
            'active_semester' => '1st Semester',
            'active_academic_year' => '2025-2026',
            'semester_options' => ['1st Semester', '2nd Semester'],
            'academic_year_options' => ['2025-2026'],
            'archived_semester_options' => [],
            'archived_academic_year_options' => [],
        ];
        foreach (array_merge($defaults, $overrides) as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }
        Cache::flush();
    }

    private function makeFacultyWithEvaluation(string $semester, string $year, int $rating): Faculty
    {
        $facultyUser = $this->makeUser('faculty', "faculty-{$semester}-{$rating}@test.com");
        $facultyUser->update(['role' => 'faculty']);
        $faculty = Faculty::create([
            'user_id' => $facultyUser->id,
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);
        $student = $this->makeUser('student', "student-{$semester}-{$rating}@test.com");

        $category = Category::create([
            'category_name' => 'Teaching Skill',
            'weight' => 1,
            'evaluatee_type' => 'faculty',
        ]);
        $question = Question::create([
            'category_id' => $category->id,
            'question_text' => 'Rates clearly?',
        ]);
        $evaluation = Evaluation::create([
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => $semester,
            'academic_year' => $year,
        ]);
        Answer::create([
            'evaluation_id' => $evaluation->id,
            'question_id' => $question->id,
            'rating' => $rating,
        ]);

        return $faculty;
    }

    public function test_settings_update_rejects_archived_active_period()
    {
        $this->seedSettings();
        $admin = $this->makeUser('admin', 'admin@test.com');

        $this->actingAs($admin, 'sanctum')->postJson('/api/settings', [
            'settings' => [
                'active_semester' => '1st Semester',
                'active_academic_year' => '2025-2026',
                'semester_options' => ['1st Semester'],
                'academic_year_options' => ['2025-2026'],
                'archived_semester_options' => ['1st Semester'],
                'archived_academic_year_options' => [],
            ],
        ])->assertStatus(422);
    }

    public function test_settings_update_keeps_active_period_usable()
    {
        $this->seedSettings();
        $admin = $this->makeUser('admin', 'admin@test.com');

        $this->actingAs($admin, 'sanctum')->postJson('/api/settings', [
            'settings' => [
                'active_semester' => 'Summer',
                'active_academic_year' => '2026-2027',
                'semester_options' => ['1st Semester'],
                'academic_year_options' => ['2025-2026'],
                'archived_semester_options' => [],
                'archived_academic_year_options' => [],
            ],
        ])->assertStatus(200);

        Cache::flush();
        $stored = Setting::cachedAll();
        $this->assertContains('Summer', $stored->get('semester_options'));
        $this->assertContains('2026-2027', $stored->get('academic_year_options'));
        $this->assertNotContains('Summer', $stored->get('archived_semester_options') ?? []);
    }

    public function test_academic_periods_returns_union_with_usage()
    {
        $this->seedSettings();
        $admin = $this->makeUser('admin', 'admin@test.com');
        // Legacy evaluation uses values absent from the configured option lists.
        $this->makeFacultyWithEvaluation('Summer', '2023-2024', 4);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/reports/periods');
        $res->assertStatus(200);
        $this->assertContains('Summer', $res->json('semesters'));
        $this->assertContains('2023-2024', $res->json('academic_years'));
        $this->assertEquals(1, $res->json('semester_usage.Summer'));
        $this->assertEquals(1, $res->json('academic_year_usage.2023-2024'));
    }

    public function test_get_results_defaults_to_active_but_allows_legacy_period()
    {
        $this->seedSettings();
        $admin = $this->makeUser('admin', 'admin@test.com');
        $faculty = $this->makeFacultyWithEvaluation('1st Semester', '2025-2026', 5);

        // Second response for the same faculty in a legacy period.
        $student = $this->makeUser('student', 'legacy-student@test.com');
        $question = Question::first();
        $legacy = Evaluation::create([
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => 'Summer',
            'academic_year' => '2023-2024',
        ]);
        Answer::create(['evaluation_id' => $legacy->id, 'question_id' => $question->id, 'rating' => 1]);

        $base = "/api/evaluations/results/{$faculty->id}";

        $active = $this->actingAs($admin, 'sanctum')->getJson($base);
        $active->assertStatus(200);
        $this->assertEquals(5.0, (float) $active->json('final_score'));

        $archived = $this->actingAs($admin, 'sanctum')
            ->getJson($base . '?semester=Summer&academic_year=2023-2024');
        $archived->assertStatus(200);
        $this->assertEquals(1.0, (float) $archived->json('final_score'));

        $all = $this->actingAs($admin, 'sanctum')
            ->getJson($base . '?semester=all&academic_year=all');
        $all->assertStatus(200);
        $this->assertEquals(3.0, (float) $all->json('final_score'));
    }
}
