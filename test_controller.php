<?php
use Illuminate\Http\Request;
use App\Http\Controllers\Api\StudentController;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = new Request([
    'page' => 1,
    'query' => '',
    'course' => '',
    'section_id' => ''
]);

$controller = new StudentController();
$response = $controller->index($request);

echo "Status Code: " . $response->getStatusCode() . PHP_EOL;
echo "Data: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . PHP_EOL;
