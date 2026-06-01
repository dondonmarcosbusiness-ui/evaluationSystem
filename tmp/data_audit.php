<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subject;
use App\Models\Course;
use App\Models\Section;
use App\Models\FacultyAssignment;

echo "--- DATA AUDIT ---\n";

$stu = User::where('name', 'LIKE', '%Mapandi%')->first();
if ($stu) {
    echo "Student: {$stu->name}\n";
    echo " - Stored Course Name: {$stu->course}\n";
    $stuCourseObj = Course::where('name', $stu->course)->first();
    echo " - Derived Course ID: " . ($stuCourseObj ? $stuCourseObj->id : "NOT FOUND") . "\n";
    echo " - Linked Section ID: {$stu->section_id}\n";
    $stuSectObj = Section::find($stu->section_id);
    echo " - Linked Section Name: " . ($stuSectObj ? $stuSectObj->name : "N/A") . "\n";
}

$sub = Subject::where('name', 'LIKE', '%Capstone%')->first();
if ($sub) {
    echo "\nSubject: {$sub->name} (Subject ID: {$sub->id})\n";
    echo " - Subject Course ID: {$sub->course_id}\n";
    $subCourseObj = Course::find($sub->course_id);
    echo " - Subject Course Name: " . ($subCourseObj ? $subCourseObj->name : "N/A") . "\n";
}

echo "\n--- FACULTY ASSIGNMENTS FOR THIS SUBJECT ---\n";
$ass = FacultyAssignment::with(['faculty.user', 'section'])->where('subject_id', $sub->id)->get();
foreach ($ass as $a) {
    echo "Faculty: {$a->faculty->user->name} | Section: {$a->section->name} (Section ID: {$a->section_id}) | Course ID: {$a->section->course_id}\n";
}
