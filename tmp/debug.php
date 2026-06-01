<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('role', 'student')->first();
if (!$user) die("No student found");

$request = \Illuminate\Http\Request::create('/api/evaluations/faculty', 'GET');
$request->setUserResolver(function() use ($user) { return $user; });

try {
    $c = app('App\Http\Controllers\Api\EvaluationController');
    $response = $c->getFacultyToEvaluate($request);
    echo $response->content();
} catch (\Exception $e) {
    echo "ERROR IS HERE: ".$e->getMessage()."\n".$e->getTraceAsString();
}
