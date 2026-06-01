<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = \App\Models\User::where('section_id', 12)->count();
echo "Students in Section 12: $count\n";

$section = \App\Models\Section::find(12);
if ($section) {
    echo "Section 12 Name: {$section->name} | Course ID: {$section->course_id}\n";
}

$section4 = \App\Models\Section::find(4);
if ($section4) {
    echo "Section 4 Name: {$section4->name} | Course ID: {$section4->course_id}\n";
    echo "Students in Section 4: " . \App\Models\User::where('section_id', 4)->count() . "\n";
}
