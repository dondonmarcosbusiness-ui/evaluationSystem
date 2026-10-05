<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Faculty;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $user, array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->givePermissionTo($name);
        }

        return $user;
    }

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

    /** @return array{0: User, 1: Faculty} */
    private function makeFaculty(string $email = 'faculty@test.com'): array
    {
        $user = $this->grant($this->makeUser('faculty', $email), [
            'report.view',
            'report.view.own',
        ]);
        $faculty = Faculty::create([
            'user_id' => $user->id,
            'department' => 'CIT',
            'course' => 'BSIT',
            'position' => 'Instructor',
        ]);

        return [$user, $faculty];
    }

    private function makeEvaluation(User $facultyUser, Faculty $faculty, array $attrs): Evaluation
    {
        $evaluation = Evaluation::create(array_merge([
            'student_id' => $this->makeUser('student', uniqid('student-') . '@test.com')->id,
            'faculty_id' => $faculty->id,
            'evaluatee_type' => 'faculty',
            'evaluatee_id' => $faculty->id,
            'semester' => '1st Semester',
            'academic_year' => '2025-2026',
            'comments' => 'A comment',
        ], $attrs));

        if (isset($attrs['created_at'])) {
            $evaluation->forceFill(['created_at' => $attrs['created_at']])->save();
        }

        return $evaluation;
    }

    public function test_latest_per_subject_returns_only_the_most_recent_comment_per_subject()
    {
        [$facultyUser, $faculty] = $this->makeFaculty();

        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-SIA01', 'year_section' => '4A',
            'comments' => 'oldest', 'created_at' => '2026-09-20 08:00:00',
        ]);
        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-SIA01', 'year_section' => '4B',
            'comments' => 'middle', 'created_at' => '2026-09-26 05:53:00',
        ]);
        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-SIA01', 'year_section' => '3A',
            'comments' => 'newest', 'created_at' => '2026-09-28 08:39:00',
        ]);
        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-PE01', 'year_section' => '4A',
            'comments' => 'other subject', 'created_at' => '2026-09-21 08:00:00',
        ]);

        $res = $this->actingAs($facultyUser, 'sanctum')
            ->getJson('/api/reports/my-feedback?semester=all&academic_year=all&latest_per_subject=1')
            ->assertOk();

        $feedbacks = $res->json('feedbacks');
        $this->assertCount(2, $feedbacks);
        $this->assertEqualsCanonicalizing(
            ['IT-SIA01', 'IT-PE01'],
            array_column($feedbacks, 'subject_code')
        );

        $sia = collect($feedbacks)->firstWhere('subject_code', 'IT-SIA01');
        $this->assertSame('newest', $sia['text']);
    }

    public function test_my_feedback_without_latest_flag_returns_every_comment()
    {
        [$facultyUser, $faculty] = $this->makeFaculty();

        foreach (['2026-09-20 08:00:00', '2026-09-26 05:53:00', '2026-09-28 08:39:00'] as $i => $date) {
            $this->makeEvaluation($facultyUser, $faculty, [
                'subject_code' => 'IT-SIA01',
                'comments' => "comment {$i}",
                'created_at' => $date,
            ]);
        }

        $res = $this->actingAs($facultyUser, 'sanctum')
            ->getJson('/api/reports/my-feedback?semester=all&academic_year=all')
            ->assertOk();

        $this->assertCount(3, $res->json('feedbacks'));
    }

    public function test_subject_drill_down_returns_all_comments_across_sections_and_periods()
    {
        [$facultyUser, $faculty] = $this->makeFaculty();

        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-SIA01', 'year_section' => '4A',
            'semester' => '1st Semester', 'academic_year' => '2025-2026',
            'comments' => 'from 4A current period', 'created_at' => '2026-09-28 08:39:00',
        ]);
        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-SIA01', 'year_section' => '3B',
            'semester' => '2nd Semester', 'academic_year' => '2024-2025',
            'comments' => 'from 3B old period', 'created_at' => '2025-03-10 08:00:00',
        ]);
        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-SIA01', 'year_section' => '2A',
            'semester' => '1st Semester', 'academic_year' => '2025-2026',
            'comments' => 'from 2A current period', 'created_at' => '2026-09-20 08:00:00',
        ]);
        // Different subject: must not leak into the drill-down.
        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-PE01', 'year_section' => '4A',
            'comments' => 'other subject', 'created_at' => '2026-09-21 08:00:00',
        ]);

        // Explicit period filters are intentionally bypassed for the drill-down.
        $res = $this->actingAs($facultyUser, 'sanctum')
            ->getJson('/api/reports/my-feedback?subject_code=IT-SIA01&semester=1st%20Semester&academic_year=2025-2026')
            ->assertOk();

        $feedbacks = $res->json('feedbacks');
        $this->assertCount(3, $feedbacks);
        $this->assertEqualsCanonicalizing(
            ['from 4A current period', 'from 3B old period', 'from 2A current period'],
            array_column($feedbacks, 'text')
        );
    }

    public function test_my_feedback_is_anonymous_and_scoped_to_own_evaluations()
    {
        [$facultyUser, $faculty] = $this->makeFaculty();
        [$otherUser, $otherFaculty] = $this->makeFaculty('other@test.com');

        $this->makeEvaluation($facultyUser, $faculty, [
            'subject_code' => 'IT-SIA01', 'comments' => 'mine',
        ]);
        $this->makeEvaluation($otherUser, $otherFaculty, [
            'subject_code' => 'IT-SIA01', 'comments' => 'not mine',
        ]);

        $res = $this->actingAs($facultyUser, 'sanctum')
            ->getJson('/api/reports/my-feedback?semester=all&academic_year=all&latest_per_subject=1')
            ->assertOk();

        $feedbacks = $res->json('feedbacks');
        $this->assertCount(1, $feedbacks);
        $this->assertSame('mine', $feedbacks[0]['text']);

        foreach ($feedbacks as $feedback) {
            $this->assertArrayNotHasKey('student_id', $feedback);
            $this->assertArrayNotHasKey('year_section', $feedback);
            $this->assertArrayNotHasKey('name', $feedback);
        }
    }
}
