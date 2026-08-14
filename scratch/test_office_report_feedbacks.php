<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\OfficeReportController;
use Illuminate\Http\Request;
use App\Models\Office;

$office = Office::where('name', 'Guidance Office')->first();
if (!$office) { echo "Guidance Office not found\n"; exit(1); }

$request = Request::create('/api/office-reports/' . $office->id . '/feedbacks', 'GET', ['page' => 1]);
$controller = new OfficeReportController();
$response = $controller->feedbacks($office->id, $request);

$content = $response->getContent();
$decoded = json_decode($content, true);
if (!is_array($decoded)) { echo "Response not JSON: $content\n"; exit(1); }

if (isset($decoded['data'])) {
    echo "data count=" . count($decoded['data']) . "\n";
    echo "total=" . ($decoded['total'] ?? 'n/a') . "\n";
} else {
    echo "response keys: " . implode(',', array_keys($decoded)) . "\n";
}
