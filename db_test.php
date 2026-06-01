<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$settings = App\Models\Setting::first();
echo "Active Settings: Sem=" . ($settings->active_semester ?? 'null') . " Year=" . ($settings->active_academic_year ?? 'null') . "\n";

$evals = App\Models\Evaluation::all(['student_id', 'faculty_id', 'semester', 'academic_year']);
echo "Evaluations: " . $evals->toJson() . "\n";
