<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$now = now();
$start = $now->copy()->subHours(2);
$end = $now->copy()->addHours(2);
$dateStr = $now->toDateString();

$shiftStart = \Carbon\Carbon::parse($dateStr . ' ' . $start->format('H:i:s'));
$shiftEnd   = \Carbon\Carbon::parse($dateStr . ' ' . $end->format('H:i:s'));
$allowedStart = $shiftStart->copy()->subMinutes(30);
$allowedEnd   = $shiftEnd->copy()->addMinutes(30);

echo 'now: ' . $now->toString() . PHP_EOL;
echo 'shiftStart: ' . $shiftStart->toString() . PHP_EOL;
echo 'shiftEnd: ' . $shiftEnd->toString() . PHP_EOL;
echo 'allowedStart: ' . $allowedStart->toString() . PHP_EOL;
echo 'allowedEnd: ' . $allowedEnd->toString() . PHP_EOL;
echo 'between: ' . ($now->between($allowedStart, $allowedEnd) ? 'YES' : 'NO') . PHP_EOL;
echo PHP_EOL;
echo 'CarbonImmutable? ' . (now() instanceof \Carbon\CarbonImmutable ? 'YES' : 'NO') . PHP_EOL;
echo 'App timezone: ' . config('app.timezone') . PHP_EOL;
