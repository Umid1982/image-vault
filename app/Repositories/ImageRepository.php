<?php

namespace App\Repositories;

use App\Models\Image;
use Illuminate\Pagination\LengthAwarePaginator;

class ImageRepository
{
    /**
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listByUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return Image::query()
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param int $id
     * @param int $userId
     * @return Image|null
     */
    public function findForUser(int $id, int $userId): ?Image
    {
        return Image::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * @param array $data
     * @return Image
     */
    public function create(array $data): Image
    {
        return Image::query()->create($data);
    }

    /**
     * @param string $hash
     * @param int $userId
     * @return Image|null
     */
    public function findByHashForUser(string $hash,int $userId): ?Image
    {
        return Image::query()
            ->where('hash', $hash)
            ->where('user_id', $userId)
            ->first();
    }

}

