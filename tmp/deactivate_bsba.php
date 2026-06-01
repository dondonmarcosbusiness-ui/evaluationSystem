<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$studentUserIds = \App\Models\Student::where('course', 'BSBA')->pluck('user_id');
if ($studentUserIds->isNotEmpty()) {
    $updated = \App\Models\User::whereIn('id', $studentUserIds)->update(['is_active' => false]);
    echo "Successfully deactivated $updated orphaned BSBA students!";
} else {
    echo "No BSBA students found to deactivate.";
}
