<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Office;
use App\Models\OfficeFeedback;

foreach (Office::all() as $office) {
    printf("%s|%s|%d\n", $office->id, $office->name, OfficeFeedback::where('office_id', $office->id)->count());
}
