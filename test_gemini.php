<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$gemini = app(\App\Services\Ai\GeminiClient::class);

echo "Testing Gemini connection...\n";
try {
    // Generate a simple prompt instead since testConnection might not exist or might need testing
    $result = $gemini->generate("Say hello", config('services.gemini.model'));
    echo "Connection successful! Response:\n";
    print_r($result);
} catch (\Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
