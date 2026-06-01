<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Faculty;
use App\Models\FacultyAssignment;
use App\Models\Section;

// 1. Find the Faculty
$faculty = Faculty::whereHas('user', function($q) {
    $q->where('name', 'LIKE', '%Allan Cruz%');
})->first();

if (!$faculty) {
    die("Faculty 'Allan Cruz' not found.\n");
}

echo "Faculty: {$faculty->user->name} (Faculty ID: {$faculty->id})\n";

// 2. Find assignments for this faculty
$assignments = FacultyAssignment::with(['subject', 'section'])->where('faculty_id', $faculty->id)->get();
echo "Assignments found: " . $assignments->count() . "\n";
foreach ($assignments as $a) {
    echo " - Subject: {$a->subject->name} | Section: {$a->section->name} (ID: {$a->section_id}) | Year: {$a->academic_year} | Sem: {$a->semester}\n";
}

// 3. Find the student
$student = User::where('name', 'LIKE', '%Mapandi%')->first(); // Omar Mapandi from debug output
if ($student) {
    echo "\nStudent: {$student->name} | Section ID: " . ($student->section_id ?? 'NULL') . " | Course: {$student->course}\n";
    $studentSection = Section::find($student->section_id);
    if ($studentSection) {
        echo "Student Section Detail: ID {$studentSection->id} | Name {$studentSection->name} | Course ID {$studentSection->course_id}\n";
    }
}
