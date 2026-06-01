<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'neustcarr1005@gmail.com')->first();
$settings = App\Models\Setting::first();
echo "Active Settings: Sem=" . ($settings->active_semester ?? 'null') . " Year=" . ($settings->active_academic_year ?? 'null') . "\n";

if ($user) {
    $evals = App\Models\Evaluation::where('student_id', $user->id)->get(['faculty_id', 'semester', 'academic_year']);
    echo "Evaluations: " . $evals->toJson() . "\n";
}
