<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FacultyAssignment;

$a = FacultyAssignment::where('faculty_id', 1)->where('section_id', 12)->first();
if ($a) {
    $a->section_id = 4;
    $a->save();
    echo "Assignment for Dr. Allan Cruz moved from Section 12 to Section 4.\n";
} else {
    echo "Assignment not found. It might have been fixed or the IDs are different.\n";
}
