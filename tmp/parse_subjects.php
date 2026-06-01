<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Subject;

$subjects = Subject::whereNull('code')->get();
$count = 0;

foreach ($subjects as $s) {
    if (preg_match('/^([^\-\x{2013}]+)\s*[-\x{2013}]\s*(.+)$/u', $s->name, $matches)) {
        $code = trim($matches[1]);
        $name = trim($matches[2]);
        
        $s->code = $code;
        $s->name = $name;
        $s->save();
        $count++;
    }
}

echo "Successfully parsed and updated $count subjects!\n";
