<?php

namespace App\Console\Commands;

use App\Jobs\ConvertImageToWebpJob;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RetryFailedWebpConversions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:retry-failed
                            {--hours=24 : Retry conversions failed in last N hours}
                            {--limit=50 : Maximum number of images to retry}
                            {--status=failed : Status to retry (failed, permanently_failed, skipped)}
                            {--force : Force retry even if max attempts reached}
                            {--dry-run : Show what would be retried without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed WebP conversion jobs for images';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info(' Starting failed WebP conversions retry...');
        $this->newLine();

        // Выводим параметры
        $this->info('Parameters:');
        $this->line("  • Hours: {$this->option('hours')}");
        $this->line("  • Limit: {$this->option('limit')}");
        $this->line("  • Status: {$this->option('status')}");
        $this->line("  • Force: " . ($this->option('force') ? 'Yes' : 'No'));
        $this->line("  • Dry run: " . ($this->option('dry-run') ? 'Yes' : 'No'));
        $this->newLine();

        $query = $this->buildQuery();
        $images = $query->get();

        if ($images->isEmpty()) {
            $this->warn('📭 No failed conversions found to retry.');
            return Command::SUCCESS;
        }

        $this->info(" Found {$images->count()} failed conversion(s).");

        if ($this->option('dry-run')) {
            $this->showDryRunResults($images);
            return Command::SUCCESS;
        }

        $retriedCount = $this->retryImages($images);

        $this->newLine();

        if ($retriedCount > 0) {
            $this->info(" Successfully retried {$retriedCount} image(s).");
            Log::info('RetryFailedWebpConversions command executed', [
                'retried_count' => $retriedCount,
                'parameters' => $this->getParametersArray(),
            ]);
        } else {
            $this->warn('⚠️ No images were retried.');
        }

        return Command::SUCCESS;
    }

    /**
     * Build the query based on command options
     */
    private function buildQuery()
    {
        $status = $this->option('status');
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');

        $query = Image::query();

        // Фильтр по статусу
        if ($status === 'all') {
            $query->whereIn('conversion_status', ['failed', 'permanently_failed', 'skipped']);
        } else {
            $query->where('conversion_status', $status);
        }

        // Фильтр по времени
        if ($hours > 0) {
            $cutoffTime = Carbon::now()->subHours($hours);

            if ($status === 'failed' || $status === 'all') {
                $query->where(function ($q) use ($cutoffTime) {
                    $q->where('conversion_failed_at', '>=', $cutoffTime)
                        ->orWhere('conversion_skipped_at', '>=', $cutoffTime);
                });
            } elseif ($status === 'permanently_failed') {
                $query->where('conversion_permanently_failed_at', '>=', $cutoffTime);
            } elseif ($status === 'skipped') {
                $query->where('conversion_skipped_at', '>=', $cutoffTime);
            }
        }

        // Проверка количества попыток (если не force)
        if (!$this->option('force')) {
            $query->where('conversion_attempts', '<', 3); // Макс попыток из джобы
        }

        // Сортировка и лимит
        return $query->orderBy('conversion_failed_at', 'asc')
            ->limit($limit);
    }

    /**
     * Retry conversion for images
     */
    private function retryImages($images): int
    {
        $retriedCount = 0;
        $progressBar = $this->output->createProgressBar($images->count());
        $progressBar->start();

        foreach ($images as $image) {
            try {
                // Обновляем статус перед повторной попыткой
                $image->update([
                    'conversion_status' => 'pending',
                    'conversion_attempts' => 0,
                    'conversion_error' => null,
                    'conversion_failed_at' => null,
                    'conversion_permanently_failed_at' => null,
                ]);

                // Запускаем джобу
                ConvertImageToWebpJob::dispatch($image)->onQueue('images');

                $retriedCount++;
                $this->debug("Retried image ID: {$image->id}");

            } catch (\Exception $e) {
                $this->error("Failed to retry image ID: {$image->id} - {$e->getMessage()}");
                Log::error('Failed to retry image conversion', [
                    'image_id' => $image->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        return $retriedCount;
    }

    /**
     * Show dry run results
     */
    private function showDryRunResults($images): void
    {
        $this->info(' Dry run results (would retry):');
        $this->newLine();

        $headers = ['ID', 'User ID', 'Original Name', 'Status', 'Failed At', 'Attempts', 'Error'];
        $rows = [];

        foreach ($images as $image) {
            $rows[] = [
                $image->id,
                $image->user_id,
                $image->original_name,
                $image->conversion_status,
                $image->conversion_failed_at?->format('Y-m-d H:i:s') ??
                    $image->conversion_skipped_at?->format('Y-m-d H:i:s') ?? 'N/A',
                $image->conversion_attempts,
                substr($image->conversion_error ?? 'N/A', 0, 30) . '...',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->info("Total: {$images->count()} image(s) would be retried.");
    }

    /**
     * Get parameters as array for logging
     */
    private function getParametersArray(): array
    {
        return [
            'hours' => $this->option('hours'),
            'limit' => $this->option('limit'),
            'status' => $this->option('status'),
            'force' => $this->option('force'),
        ];
    }

    /**
     * Debug output (only in verbose mode)
     */
    private function debug(string $message): void
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line("  [DEBUG] {$message}");
        }
    }
}
