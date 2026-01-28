<?php

namespace App\Services;


use App\Jobs\ConvertImageToWebpJob;
use App\Models\Image;
use App\Repositories\ImageRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public function __construct(protected ImageRepository $imageRepository)
    {
    }

    /**
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->imageRepository->listByUser(
            $filters['user_id'],
            $filters['per_page']
        );
    }

    /**
     * @param array $data
     * @return Image|null
     */
    public function upload(array $data): ?Image
    {
        try {
            Log::info('Image upload started', [
                'user_id' => $data['user_id'],
                'original_name' => $data['original_name'],
                'size' => $data['size'],
            ]);

            $hash = hash_file('sha256', $data['file']->getRealPath());

            // Проверка дедупликации
            $existing = $this->imageRepository->findByHashForUser($hash, $data['user_id']);

            if ($existing) {
                Log::info('Duplicate image prevented', [
                    'user_id' => $data['user_id'],
                    'existing_image_id' => $existing->id,
                    'hash' => $hash,
                ]);
                return $existing;
            }

            // Сохраняем файл
            $path = $data['file']->storeAs(
                "images/{$data['user_id']}",
                'image_' . time() . '_' . Str::random(8) . '.' . $data['file']->extension(),
                'public'
            );

            if (!$path) {
                Log::error('Failed to store image on disk', [
                    'user_id' => $data['user_id'],
                    'original_name' => $data['original_name'],
                ]);
                return null;
            }

            // Создаём запись в БД
            $image = $this->imageRepository->create([
                'user_id' => $data['user_id'],
                'path' => $path,
                'original_name' => $data['original_name'],
                'mime' => $data['mime'],
                'size' => $data['size'],
                'hash' => $hash,
            ]);

            Log::info('Image uploaded successfully', [
                'user_id' => $data['user_id'],
                'image_id' => $image->id,
                'path' => $path,
                'size' => $data['size'],
            ]);

            // Запускаем асинхронную конвертацию
            ConvertImageToWebpJob::dispatch($image);

            return $image;

        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'user_id' => $data['user_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $data['original_name'] ?? 'unknown',
            ]);
            return null;
        }
    }

    /**
     * @param int $id
     * @return Image|null
     */
    public function get(int $id): ?Image
    {
        return $this->imageRepository->findForUser(
            $id,
            auth()->id()
        );
    }

    public function delete(int $id): bool
    {
        $image = $this->get($id);

        if (!$image) {
            return false;
        }

        Storage::disk('public')->delete($image->path);

        return (bool)$image->delete();
    }
}

