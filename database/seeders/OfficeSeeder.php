<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Office;
use App\Models\OfficeCategory;
use App\Models\OfficeQuestion;
use App\Models\QrCode;
use Illuminate\Support\Str;

class OfficeSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            ['name' => 'Guidance Office', 'description' => 'Provides counseling and guidance services to students.', 'office_head' => null, 'location' => 'Ground Floor, Admin Building'],
            ['name' => 'Library', 'description' => 'Manages library resources and facilities for research and study.', 'office_head' => null, 'location' => 'Second Floor, Library Building'],
            ['name' => 'Registrar', 'description' => 'Handles student records, enrollment, and academic documentation.', 'office_head' => null, 'location' => 'Ground Floor, Admin Building'],
            ['name' => 'Clinic', 'description' => 'Provides basic health services and first aid to students and staff.', 'office_head' => null, 'location' => 'Ground Floor, Health Center'],
            ['name' => 'Student Affairs Office', 'description' => 'Manages student activities, organizations, and welfare programs.', 'office_head' => null, 'location' => 'Second Floor, Admin Building'],
            ['name' => 'Cashier', 'description' => 'Handles financial transactions, tuition payments, and fees.', 'office_head' => null, 'location' => 'Ground Floor, Admin Building'],
        ];

        foreach ($offices as $officeData) {
            $office = Office::create($officeData);
            QrCode::create([
                'office_id' => $office->id,
                'qr_token' => Str::random(32),
                'is_active' => true,
            ]);
        }

        $categories = [
            ['category_name' => 'Service Quality', 'weight' => 0.30],
            ['category_name' => 'Responsiveness', 'weight' => 0.30],
            ['category_name' => 'Office Environment', 'weight' => 0.20],
            ['category_name' => 'Overall', 'weight' => 0.20],
        ];

        foreach ($categories as $catData) {
            $category = OfficeCategory::create($catData);
        }

        $questionsByCategory = [
            'Service Quality' => [
                'Staff shows courtesy and respect to visitors.',
                'Staff is professional in handling transactions.',
                'Staff is respectful and approachable.',
            ],
            'Responsiveness' => [
                'Staff is willing to help and assist visitors.',
                'Speed of service is satisfactory.',
                'Staff effectively resolves problems and concerns.',
            ],
            'Office Environment' => [
                'The office is clean and well-maintained.',
                'The office is comfortable and organized.',
                'The office layout is easy to navigate.',
            ],
            'Overall' => [
                'Overall satisfaction with the office service.',
                'I would recommend this office to others.',
            ],
        ];

        foreach ($questionsByCategory as $catName => $questions) {
            $category = OfficeCategory::where('category_name', $catName)->first();
            if ($category) {
                foreach ($questions as $questionText) {
                    OfficeQuestion::create([
                        'category_id' => $category->id,
                        'question_text' => $questionText,
                    ]);
                }
            }
        }
    }
}
