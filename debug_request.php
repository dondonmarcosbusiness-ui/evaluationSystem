<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$request = \Illuminate\Http\Request::create('/api/students', 'GET', [
    'page' => '1',
    'query' => '',
    'course' => '',
    'section_id' => ''
]);

echo "Has query: " . ($request->has('query') ? 'Yes' : 'No') . PHP_EOL;
echo "Query is empty string: " . ($request->query('query') === '' ? 'Yes' : 'No') . PHP_EOL;
echo "Query !== empty string: " . ($request->query('query') !== '' ? 'Yes' : 'No') . PHP_EOL;
