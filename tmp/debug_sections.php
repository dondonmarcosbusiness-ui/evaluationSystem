<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- All Sections ---\n";
foreach (\App\Models\Section::all() as $s) {
    echo "ID: {$s->id} | Name: {$s->name} | Course ID: {$s->course_id}\n";
}
echo "\n--- Students ---\n";
foreach (\App\Models\User::where('role', 'student')->limit(20)->get() as $u) {
    echo "Student: {$u->name} | Section ID: " . ($u->section_id ?? 'NULL') . "\n";
}
