<?php

declare(strict_types=1);
use App\Models\Platform\Tenant;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$t = Tenant::query()->where('code', 'local')->first();
echo json_encode($t?->only(['row_id', 'code', 'name', 'district_code', 'status']), JSON_PRETTY_PRINT).PHP_EOL;
