<?php

namespace App\Jobs;

use App\Models\Image as ImageModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class ConvertImageToWebpJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    /**
     * @param ImageModel $image
     */
    public function __construct(public ImageModel $image) {}

    /**
     * @return void
     */
    public function handle(): void
    {
        $source = storage_path('app/public/' . $this->image->path);

        if (!file_exists($source)) {
            return;
        }

        $manager = new ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());

        $image = $manager->read($source);

        $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $this->image->path);

        $image
            ->toWebp(85)
            ->save(storage_path('app/public/' . $webpPath));

        Storage::disk('public')->delete($this->image->path);

        $this->image->update([
            'path' => $webpPath,
            'mime' => 'image/webp',
            'size' => filesize(storage_path('app/public/' . $webpPath)),
        ]);
    }

}
