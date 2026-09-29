<?php
// run_cron.php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// هذا يشغل كل الـ commands المجدولة في app/Console/Kernel.php
$kernel->call('schedule:run');

echo "Scheduled commands executed.\n";
