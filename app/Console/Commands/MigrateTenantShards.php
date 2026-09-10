<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\DatabaseShard;
use App\Tenancy\Services\ShardConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MigrateTenantShards extends Command
{
    protected $signature = 'tenancy:migrate-shards
        {--shard= : Migrate only one shard code}
        {--pretend : Show SQL without executing it}
        {--force : Run in production}';

    protected $description = 'Run the canonical tenant schema migrations on every active shard.';

    public function handle(ShardConnectionManager $connections): int
    {
        $query = DatabaseShard::query()->where('status', 'active')->orderBy('code');

        if (is_string($this->option('shard')) && $this->option('shard') !== '') {
            $query->where('code', $this->option('shard'));
        }

        $shards = $query->get();

        if ($shards->isEmpty()) {
            $this->warn('No active shard matched the command options.');

            return self::SUCCESS;
        }

        $migrationPath = (string) config('tenancy.shard_migration_path', 'database/migrations/shard');
        $latestVersion = $this->latestMigrationVersion($migrationPath);
        $failed = false;

        foreach ($shards as $shard) {
            $this->newLine();
            $this->info("Migrating shard [{$shard->code}] at [{$shard->host}/{$shard->database_name}]...");

            $startedAt = now();
            $this->recordVersion((int) $shard->row_id, $latestVersion, 'running', $startedAt, null, null);

            try {
                $connections->connect($shard);

                $exitCode = Artisan::call('migrate', [
                    '--database' => (string) config('tenancy.tenant_connection', 'tenant'),
                    '--path' => $migrationPath,
                    '--force' => (bool) $this->option('force'),
                    '--pretend' => (bool) $this->option('pretend'),
                ]);

                $this->output->write(Artisan::output());

                if ($exitCode !== self::SUCCESS) {
                    throw new \RuntimeException("Migration command exited with code {$exitCode}.");
                }

                $status = $this->option('pretend') ? 'pretended' : 'completed';
                $this->recordVersion((int) $shard->row_id, $latestVersion, $status, $startedAt, now(), null);
                $this->info("Shard [{$shard->code}] {$status}.");
            } catch (Throwable $exception) {
                $failed = true;
                $this->recordVersion(
                    (int) $shard->row_id,
                    $latestVersion,
                    'failed',
                    $startedAt,
                    now(),
                    $exception->getMessage(),
                );
                report($exception);
                $this->error("Shard [{$shard->code}] failed: {$exception->getMessage()}");
            } finally {
                $connections->disconnect();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function latestMigrationVersion(string $migrationPath): ?string
    {
        $files = glob(base_path($migrationPath.'/*.php')) ?: [];
        sort($files);

        $latest = end($files);

        return is_string($latest) ? pathinfo($latest, PATHINFO_FILENAME) : null;
    }

    private function recordVersion(
        int $shardId,
        ?string $targetVersion,
        string $status,
        \DateTimeInterface $startedAt,
        ?\DateTimeInterface $completedAt,
        ?string $error,
    ): void {
        $table = DB::connection((string) config('tenancy.platform_connection', 'platform'))
            ->table('shard_schema_versions');

        $currentVersion = $table
            ->where('shard_id', $shardId)
            ->value('current_version');

        $table->updateOrInsert(
            ['shard_id' => $shardId],
            [
                'target_version' => $targetVersion,
                'current_version' => $status === 'completed' ? $targetVersion : $currentVersion,
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'error_message' => $error,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
