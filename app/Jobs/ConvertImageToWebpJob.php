<?php

namespace App\Jobs;

use App\Models\Image as ImageModel;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use RuntimeException;

class ConvertImageToWebpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $backoff = [60, 300, 900];
    public string $jobId;
    private int $originalSize;

    public function __construct(public ImageModel $image)
    {
        $this->jobId = uniqid('webp_', true);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        Log::info('ConvertImageToWebpJob started', [
            'job_id' => $this->jobId,
            'image_id' => $this->image->id,
            'attempt' => $this->attempts(),
        ]);

        $this->originalSize = $this->image->size;

        if ($this->shouldSkipProcessing()) {
            return;
        }

        $this->convertToWebp();
    }

    /**
     * Проверяем, нужно ли пропустить обработку
     */
    private function shouldSkipProcessing(): bool
    {
        // 1. Проверка существования файла
        if (!$this->fileExists()) {
            $this->markAsSkipped('source_file_not_found');
            return true;
        }

        // 2. Проверка что файл ещё не конвертирован
        if ($this->image->mime === 'image/webp') {
            $this->markAsAlreadyConverted();
            return true;
        }

        return false;
    }

    /**
     * Проверка существования файла
     */
    private function fileExists(): bool
    {
        return Storage::disk('public')->exists($this->image->path);
    }

    /**
     * Основная логика конвертации
     */
    private function convertToWebp(): void
    {
        try {
            $webpPath = $this->generateWebpPath($this->image->path);
            $quality = $this->calculateOptimalQuality($this->image->mime);

            $this->processImageConversion($webpPath, $quality);
            $newSize = Storage::disk('public')->size($webpPath);

            $this->handleOriginalFileDeletion($newSize);
            $this->updateImageRecord($webpPath, $newSize, $quality);
            $this->logSuccess($newSize, $quality);

        } catch (Exception $e) {
            $this->handleConversionError($e);
            throw $e;
        }
    }

    /**
     * Обработка конвертации изображения
     */
    private function processImageConversion(string $webpPath, int $quality): void
    {
        $sourcePath = storage_path('app/public/' . $this->image->path);
        $driver = $this->getImageDriver();
        $manager = new ImageManager($driver);

        Log::debug('ConvertImageToWebpJob: driver initialized', [
            'job_id' => $this->jobId,
            'driver' => get_class($driver),
        ]);

        $image = $manager->read($sourcePath);
        $image->toWebp($quality)->save(storage_path('app/public/' . $webpPath));

        if (!Storage::disk('public')->exists($webpPath)) {
            throw new RuntimeException('WebP file was not created');
        }
    }

    /**
     * Логика удаления оригинального файла
     */
    private function handleOriginalFileDeletion(int $newSize): void
    {
        if ($this->shouldDeleteOriginal($this->originalSize, $newSize)) {
            Storage::disk('public')->delete($this->image->path);
            Log::info('ConvertImageToWebpJob: original file deleted', [
                'job_id' => $this->jobId,
                'image_id' => $this->image->id,
                'saved_bytes' => $this->originalSize - $newSize,
            ]);
        } else {
            Log::warning('ConvertImageToWebpJob: webp larger than original, keeping both', [
                'job_id' => $this->jobId,
                'image_id' => $this->image->id,
                'original_size' => $this->originalSize,
                'webp_size' => $newSize,
            ]);
        }
    }

    /**
     * Обновление записи в БД
     */
    private function updateImageRecord(string $webpPath, int $newSize, int $quality): void
    {
        $this->image->update([
            'path' => $webpPath,
            'mime' => 'image/webp',
            'size' => $newSize,
            'conversion_status' => 'completed',
            'converted_at' => now(),
            'conversion_quality' => $quality,
            'original_size' => $this->originalSize,
            'compression_ratio' => $this->originalSize > 0
                ? round((1 - $newSize / $this->originalSize) * 100, 2)
                : 0,
            'conversion_attempts' => $this->attempts(),
        ]);
    }

    /**
     * Пометить как пропущенное
     */
    private function markAsSkipped(string $reason): void
    {
        Log::warning('ConvertImageToWebpJob: source file not found', [
            'job_id' => $this->jobId,
            'image_id' => $this->image->id,
            'path' => $this->image->path,
        ]);

        $this->image->update([
            'conversion_status' => 'skipped',
            'conversion_skipped_at' => now(),
            'conversion_skip_reason' => $reason,
            'original_size' => $this->originalSize,
        ]);
    }

    /**
     * Пометить как уже конвертированное
     */
    private function markAsAlreadyConverted(): void
    {
        Log::info('ConvertImageToWebpJob: already in webp format', [
            'job_id' => $this->jobId,
            'image_id' => $this->image->id,
        ]);

        $this->image->update([
            'conversion_status' => 'already_converted',
            'converted_at' => now(),
            'original_size' => $this->originalSize,
        ]);
    }

    /**
     * Обработка ошибок конвертации
     */
    private function handleConversionError(Exception $e): void
    {
        Log::error('ConvertImageToWebpJob failed', [
            'job_id' => $this->jobId,
            'image_id' => $this->image->id,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts(),
            'trace' => substr($e->getTraceAsString(), 0, 1000),
        ]);

        $this->image->update([
            'conversion_status' => 'failed',
            'conversion_error' => substr($e->getMessage(), 0, 255),
            'conversion_failed_at' => now(),
            'conversion_attempts' => $this->attempts(),
            'original_size' => $this->originalSize,
        ]);
    }

    /**
     * Логирование успешного завершения
     */
    private function logSuccess(int $newSize, int $quality): void
    {
        Log::info('ConvertImageToWebpJob completed successfully', [
            'job_id' => $this->jobId,
            'image_id' => $this->image->id,
            'original_size_kb' => round($this->originalSize / 1024, 2),
            'new_size_kb' => round($newSize / 1024, 2),
            'saved_percent' => $this->originalSize > 0
                ? round((1 - $newSize / $this->originalSize) * 100, 2)
                : 0,
            'quality' => $quality,
        ]);
    }

    /**
     * Выбор драйвера для обработки изображений с fallback
     */
    private function getImageDriver(): object
    {
        // Сначала пробуем Imagick (лучшее качество и производительность)
        if (extension_loaded('imagick')) {
            try {
                return new \Intervention\Image\Drivers\Imagick\Driver();
            } catch (Exception $e) {
                Log::warning('Imagick driver failed, falling back to GD', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback на GD
        if (extension_loaded('gd')) {
            return new \Intervention\Image\Drivers\Gd\Driver();
        }

        throw new RuntimeException('No image processing extension available (imagick or gd)');
    }

    /**
     * Генерация пути для WebP файла
     */
    private function generateWebpPath(string $originalPath): string
    {
        // Заменяем расширение на .webp
        $webpPath = preg_replace('/\.(jpg|jpeg|png|jfif)$/i', '.webp', $originalPath);

        // Если не удалось заменить (неизвестное расширение), добавляем .webp
        if ($webpPath === $originalPath) {
            $webpPath = $originalPath . '.webp';
        }

        return $webpPath;
    }

    /**
     * Расчёт оптимального качества для WebP
     */
    private function calculateOptimalQuality(string $mimeType): int
    {
        // JPEG обычно можно сильнее сжимать без потери качества
        if (str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg')) {
            return 80; // JPEG -> WebP с качеством 80%
        }

        // PNG с прозрачностью требует более высокого качества
        if (str_contains($mimeType, 'png')) {
            return 85; // PNG -> WebP с качеством 85%
        }

        return 85; // По умолчанию
    }

    /**
     * Решение об удалении оригинала
     */
    private function shouldDeleteOriginal(int $originalSize, int $webpSize): bool
    {
        // Если WebP больше оригинала на 20% - не удаляем
        if ($webpSize > $originalSize * 1.2) {
            return false;
        }

        // Если WebP меньше хотя бы на 5% - удаляем
        if ($webpSize < $originalSize * 0.95) {
            return true;
        }

        // В остальных случаях удаляем (WebP обычно лучше даже при одинаковом размере)
        return true;
    }
}
