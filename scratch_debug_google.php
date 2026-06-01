<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Manually test Socialite output
use Laravel\Socialite\Facades\Socialite;

try {
  /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
  $driver = Socialite::driver('google');

  $url = $driver->stateless()->redirect()->getTargetUrl();
  echo "Generated URL: " . $url . "\n";
} catch (\Throwable $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
