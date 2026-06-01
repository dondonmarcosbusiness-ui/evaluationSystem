<?php

use App\Models\User;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::beginTransaction();
try {
    $students = User::where('role', 'student')->get();
    foreach ($students as $student) {
        if ($student->section) {
            $section = Section::where('name', trim($student->section))->first();
            if ($section) {
                $student->section_id = $section->id;
                $student->save();
            }
        }
    }
    DB::commit();
    echo "User section linking completed successfully.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Linking failed: " . $e->getMessage() . "\n";
}
