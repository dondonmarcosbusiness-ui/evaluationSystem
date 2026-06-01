<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Setting;
use App\Models\FacultyAssignment;
use App\Models\User;

$settings = Setting::all()->pluck('value', 'key');
echo "Active Academic Year: [" . $settings->get('active_academic_year') . "]\n";
echo "Active Semester: [" . $settings->get('active_semester') . "]\n";
echo "Active Academic Year HEX: " . bin2hex($settings->get('active_academic_year')) . "\n";
echo "Active Semester HEX: " . bin2hex($settings->get('active_semester')) . "\n\n";

echo "Assignments:\n";
foreach (FacultyAssignment::with(['faculty.user', 'section'])->get() as $a) {
    echo "ID: {$a->id} | Faculty: {$a->faculty->user->name} | Section: {$a->section->name} (ID: {$a->section_id}) | Yr: [{$a->academic_year}] | Sem: [{$a->semester}]\n";
}

echo "\nStudents in 3A (If we can find 3A):\n";
$section3a = \App\Models\Section::where('name', '3A')->first();
if ($section3a) {
    echo "Section 3A found with ID: {$section3a->id}\n";
    $students = User::where('role', 'student')->where('section_id', $section3a->id)->get();
    foreach ($students as $s) {
        echo "Name: {$s->name} | Email: {$s->email} | Section ID: {$s->section_id}\n";
    }
} else {
    echo "Section 3A NOT found in database.\n";
}
