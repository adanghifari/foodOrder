<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domains\Chatbot\Services\ChatbotIntentService;

$service = new ChatbotIntentService();

$inputs = [
    'kosongkan keranjang',
    'kosongkan',
    'hapus semua',
    'semuanya',
    'hapus',
    'kurangi'
];

foreach ($inputs as $input) {
    $res = $service->detect($input);
    echo "Input: '$input'\n";
    echo "Intent: " . $res['intent'] . "\n";
    echo "Clear Cart: " . ($res['criteria']['clear_cart'] ?? false ? 'true' : 'false') . "\n";
    echo "Entities: " . json_encode($res['entities']) . "\n";
    echo "---------------------------------\n";
}
