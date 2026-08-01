<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtectedFileService
{
    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'local');
    }

    public function exists(string $path): bool
    {
        return Storage::disk('local')->exists($path) || Storage::disk('public')->exists($path);
    }

    public function download(string $path, ?string $name = null): StreamedResponse
    {
        $this->migrateLegacyFile($path);

        return Storage::disk('local')->download($path, $name);
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }

    public function migrateLegacyFile(string $path): bool
    {
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('public')->delete($path);

            return true;
        }

        if (! Storage::disk('public')->exists($path)) {
            return false;
        }

        $stream = Storage::disk('public')->readStream($path);
        if ($stream === false) {
            return false;
        }

        try {
            if (! Storage::disk('local')->writeStream($path, $stream)) {
                return false;
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        Storage::disk('public')->delete($path);

        return true;
    }
}
