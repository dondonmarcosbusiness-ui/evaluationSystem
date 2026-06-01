<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subject;

echo "--- IT401 SUBJECTS ---\n";
foreach (Subject::where('code', 'IT401')->orWhere('name', 'LIKE', '%Capstone%')->get() as $s) {
    echo "ID: {$s->id} | Name: {$s->name} | Code: {$s->code} | Course ID: {$s->course_id}\n";
}
echo "\n--- ALL COURSES ---\n";
foreach (\App\Models\Course::all() as $c) {
    echo "ID: {$c->id} | Name: {$c->name}\n";
}
