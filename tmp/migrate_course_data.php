<?php

use App\Models\Course;
use App\Models\Subject;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::beginTransaction();
try {
    $courses = Course::all();
    foreach ($courses as $course) {
        // Subjects
        if ($course->subjects) {
            $subjectList = explode(',', $course->subjects);
            foreach ($subjectList as $name) {
                $name = trim($name);
                if ($name) {
                    Subject::firstOrCreate([
                        'name' => $name,
                        'course_id' => $course->id
                    ]);
                }
            }
        }

        // Sections
        if ($course->sections) {
            $sectionList = explode(',', $course->sections);
            foreach ($sectionList as $name) {
                $name = trim($name);
                if ($name) {
                    Section::firstOrCreate([
                        'name' => $name,
                        'course_id' => $course->id
                    ]);
                }
            }
        }
    }
    DB::commit();
    echo "Data migration completed successfully.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
