<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\FacultyController;

try {
    $request = new Request([
        'page' => 1,
        'query' => '',
        'department' => ''
    ]);

    $controller = new FacultyController();
    $response = $controller->index($request);

    echo "Status Code: " . $response->getStatusCode() . PHP_EOL;
    echo "Data: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . PHP_EOL;
} catch (\Throwable $e) {
    echo "CAUGHT ERROR: " . $e->getMessage() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}
