<?php
require __DIR__ . '/../../var/www/html/vendor/autoload.php';
$app = require __DIR__ . '/../../var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = \Enpii\Assistant\Models\Persona::query()->orderBy('name')->get(['id','slug','name','is_default','is_active'])->toArray();
echo "count=" . count($rows) . "\n";
foreach ($rows as $r) {
    echo $r['slug'] . " | " . $r['name'] . " | default=" . ($r['is_default']?'1':'0') . " | active=" . ($r['is_active']?'1':'0') . "\n";
}
