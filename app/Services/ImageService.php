<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    public function storeUploaded(UploadedFile $file, string $dir): string
    {
        return $file->store($dir);
    }

    public function replace(UploadedFile $newFile, ?string $oldPath): string
    {
        if ($oldPath) {
            $this->delete($oldPath);
        }

        return $this->storeUploaded($newFile, 'public/product_images');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::delete($path);
        }
    }

    public function getThumbnailPath(string $path): string
    {
        return $path;
    }
}
