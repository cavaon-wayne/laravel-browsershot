<?php

namespace Cavaon\Browsershot\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Cavaon\Browsershot\Wrapper;

trait Storable
{
    /**
     * Stores the generated output file to local storage
     *
     * @param string      $file_path
     * @param string $visibility 'private' or 'public'
     * @return bool
     */
    public function store(string $file_path, $visibility='public'): string
    {
        $this->generateTempFile();

        return Storage::put($file_path, file_get_contents($this->tempFile), $visibility);
    }

    /**
     * Creates a random name for the file
     *
     * @return string
     */
    protected function getRandomFileName(): string
    {
        return Str::random() . '.' . $this->getFileExtension();
    }

    abstract protected function getFileExtension(): string;

    abstract protected function generateTempFile(): Wrapper;
}
