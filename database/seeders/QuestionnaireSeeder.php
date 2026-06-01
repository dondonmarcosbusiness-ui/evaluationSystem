<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

class QuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate questionnaire tables; clear answers first so question_id FKs are not orphaned
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('evaluation_answers')->truncate();
        DB::table('evaluation_questions')->truncate();
        DB::table('evaluation_categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Faculty Categories
        $facultyCategories = [
            [
                'category_name' => 'Instructional Delivery',
                'weight' => 0.20,
                'questions' => [
                    'The faculty presents lessons in a clear and organized manner.',
                    'The faculty uses appropriate teaching methods for the subject.',
                    'The faculty provides examples that help in understanding topics.',
                    'The faculty uses instructional materials effectively.',
                ]
            ],
            [
                'category_name' => 'Subject Mastery',
                'weight' => 0.20,
                'questions' => [
                    'The faculty demonstrates mastery and expertise of the subject matter.',
                    'The faculty answers student questions accurately and confidently.',
                    'The faculty relates subject matter to real-world applications.',
                ]
            ],
            [
                'category_name' => 'Student Engagement',
                'weight' => 0.25,
                'questions' => [
                    'The faculty encourages student participation during class.',
                    'The faculty creates a positive and engaging learning environment.',
                    'The faculty uses activities that promote active learning.',
                    'The faculty addresses individual student needs appropriately.',
                ]
            ],
            [
                'category_name' => 'Assessment Practices',
                'weight' => 0.20,
                'questions' => [
                    'The faculty provides clear and fair assessment criteria.',
                    'The faculty returns graded work with constructive feedback.',
                    'The faculty uses varied assessment methods (quizzes, projects, recitation).',
                ]
            ],
            [
                'category_name' => 'Professionalism',
                'weight' => 0.15,
                'questions' => [
                    'The faculty is punctual and attends classes regularly.',
                    'The faculty treats students with respect and courtesy.',
                    'The faculty maintains professional conduct at all times.',
                ]
            ],
        ];

        foreach ($facultyCategories as $catData) {
            $category = Category::create([
                'category_name' => $catData['category_name'],
                'weight'        => $catData['weight'],
                'academic_year' => '2025-2026',
                'semester'      => '1st Semester',
                'evaluatee_type'=> 'faculty',
            ]);

            foreach ($catData['questions'] as $q) {
                Question::create([
                    'category_id'   => $category->id,
                    'question_text' => $q,
                ]);
            }
        }

        // Staff Categories
        $staffCategories = [
            [
                'category_name' => 'Service Quality',
                'weight' => 0.40,
                'questions' => [
                    'The staff member is polite, courteous, and approachable.',
                    'The staff member provides clear instructions and helpful information.',
                    'The staff member demonstrates a strong desire to assist students.',
                ]
            ],
            [
                'category_name' => 'Efficiency & Promptness',
                'weight' => 0.35,
                'questions' => [
                    'The staff member processes student requests and inquiries efficiently.',
                    'The staff member keeps services moving with minimal wait times.',
                    'The staff member is organized and fast in delivery of tasks.',
                ]
            ],
            [
                'category_name' => 'Professionalism & Decorum',
                'weight' => 0.25,
                'questions' => [
                    'The staff member maintains professional conduct and attire.',
                    'The staff member treats everyone with equal respect and fairness.',
                    'The staff member handles complex requests or complaints in a helpful manner.',
                ]
            ],
        ];

        foreach ($staffCategories as $catData) {
            $category = Category::create([
                'category_name' => $catData['category_name'],
                'weight'        => $catData['weight'],
                'academic_year' => '2025-2026',
                'semester'      => '1st Semester',
                'evaluatee_type'=> 'staff',
            ]);

            foreach ($catData['questions'] as $q) {
                Question::create([
                    'category_id'   => $category->id,
                    'question_text' => $q,
                ]);
            }
        }
    }
}
