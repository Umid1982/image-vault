<?php

namespace App\Services;


use App\Models\Image;
use App\Repositories\ImageRepository;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $path = $data['file']->storeAs(
            "images/{$data['user_id']}",
            'image_' . time() . '_' . Str::random(8) . '.' . $data['file']->extension(),
            'public'
        );

        return $this->imageRepository->create([
            'user_id' => $data['user_id'],
            'path' => $path,
            'original_name' => $data['original_name'],
            'mime' => $data['mime'],
            'size' => $data['size'],
        ]);
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

