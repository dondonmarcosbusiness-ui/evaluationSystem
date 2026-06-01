<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = \App\Models\Section::find(12);
if ($s) {
    echo "Section ID: {$s->id} | Name: {$s->name} | Course ID: {$s->course_id}\n";
    $c = \App\Models\Course::find($s->course_id);
    echo "Course Name: " . ($c ? $c->name : "N/A") . "\n";
} else {
    echo "Section ID 12 NOT FOUND\n";
}

$stu = \App\Models\User::where('name', 'LIKE', '%Mapandi%')->first();
if ($stu) {
    echo "\nStudent: {$stu->name} | Section ID: {$stu->section_id} | Course: {$stu->course}\n";
}
