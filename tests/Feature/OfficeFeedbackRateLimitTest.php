<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\OfficeCategory;
use App\Models\OfficeQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeFeedbackRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function makeOffice(string $name = 'Registrar'): Office
    {
        return Office::create([
            'name' => $name,
            'description' => 'Test office',
            'is_active' => true,
        ]);
    }

    private function makeQuestion(): OfficeQuestion
    {
        $category = OfficeCategory::create([
            'category_name' => 'Service',
            'weight' => 1,
        ]);

        return OfficeQuestion::create([
            'category_id' => $category->id,
            'question_text' => 'Were you satisfied with the service?',
        ]);
    }

    private function payload(Office $office, OfficeQuestion $question, array $overrides = []): array
    {
        return array_merge([
            'office_id' => $office->id,
            'visitor_type' => 'student',
            'gender' => 'male',
            'device_id' => 'device-123',
            'answers' => [
                ['question_id' => $question->id, 'answer' => true],
            ],
        ], $overrides);
    }

    public function test_logged_in_student_cannot_submit_twice_for_same_office_in_one_day()
    {
        $user = User::create([
            'email' => 'student@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'password' => 'password',
            'is_active' => true,
        ]);
        $office = $this->makeOffice();
        $question = $this->makeQuestion();
        $payload = $this->payload($office, $question);

        $this->actingAs($user, 'sanctum')->postJson('/api/office-feedback', $payload)->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/office-feedback', $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'You have already submitted feedback for this office today']);
    }

    public function test_logged_in_student_can_submit_for_different_office_same_day()
    {
        $user = User::create([
            'email' => 'student@test.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'password' => 'password',
            'is_active' => true,
        ]);
        $firstOffice = $this->makeOffice('Registrar');
        $secondOffice = $this->makeOffice('Library');
        $question = $this->makeQuestion();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/office-feedback', $this->payload($firstOffice, $question))
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/office-feedback', $this->payload($secondOffice, $question))
            ->assertStatus(201);
    }

    public function test_anonymous_visitor_cannot_submit_twice_from_same_device_in_one_day()
    {
        $office = $this->makeOffice();
        $question = $this->makeQuestion();

        $this->postJson('/api/office-feedback', $this->payload($office, $question))->assertStatus(201);

        $this->postJson('/api/office-feedback', $this->payload($office, $question))
            ->assertStatus(422)
            ->assertJson(['message' => 'You have already submitted feedback for this office today']);
    }

    public function test_anonymous_visitor_can_submit_again_on_a_new_day()
    {
        $office = $this->makeOffice();
        $question = $this->makeQuestion();

        $this->postJson('/api/office-feedback', $this->payload($office, $question))->assertStatus(201);

        \Carbon\Carbon::setTestNow(now()->addDay());

        try {
            $this->postJson('/api/office-feedback', $this->payload($office, $question))->assertStatus(201);
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    public function test_student_number_matching_account_is_rate_limited_without_login()
    {
        User::create([
            'email' => 'student@test.com',
            'id_number' => '2020-0001',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'password' => 'password',
            'is_active' => true,
        ]);
        $office = $this->makeOffice();
        $question = $this->makeQuestion();
        $payload = $this->payload($office, $question, ['student_number' => '2020-0001']);

        $this->postJson('/api/office-feedback', $payload)->assertStatus(201);

        $this->postJson('/api/office-feedback', $payload)
            ->assertStatus(422)
            ->assertJson(['message' => 'You have already submitted feedback for this office today']);
    }
}
