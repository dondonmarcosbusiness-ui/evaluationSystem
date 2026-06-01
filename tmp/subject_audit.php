<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subject;
use App\Models\Course;

echo "--- SUBJECT AUDIT ---\n";
$subjects = Subject::where('name', 'LIKE', '%Capstone%')->get();
foreach ($subjects as $s) {
    $c = Course::find($s->course_id);
    echo "ID: {$s->id} | Name: {$s->name} | Code: {$s->code} | Course: " . ($c ? $c->name : "N/A") . " (ID: {$s->course_id})\n";
}
echo "\n--- FACULTY ASSIGNMENTS ---\n";
foreach (\App\Models\FacultyAssignment::with(['faculty.user', 'subject', 'section'])->get() as $a) {
    echo "F: {$a->faculty->user->name} | Sub: {$a->subject->name} (ID: {$a->subject_id}) | Sect: {$a->section->name} (ID: {$a->section_id})\n";
}
