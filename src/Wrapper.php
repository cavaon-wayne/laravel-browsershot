<?php

namespace Cavaon\Browsershot;

use Spatie\Browsershot\Browsershot;
use Spatie\Image\Manipulations;
use Cavaon\Browsershot\Traits\Responsable;
use Cavaon\Browsershot\Traits\ContentLoadable;
use Cavaon\Browsershot\Traits\Storable;

/**
 * @mixin Browsershot
 * @mixin Manipulations
 */
abstract class Wrapper
{
    use Responsable, ContentLoadable, Storable;

    /**
     * Browsershot base class to generate PDFs
     *
     * @var \Spatie\Browsershot\Browsershot
     */
    protected $browsershot;

    /**
    * Directory where the temporary pdf will be stored
    *
    * @var string
    */
    protected $tempFile;

    public function __construct(string $url = 'http://github.com/Cavaon/laravel-browsershot')
    {
        $browsershot = new Browsershot($url);
        $browsershot->setNodeBinary(config('browsershot.nodeBinary'))
                    ->setNpmBinary(config('browsershot.npmBinary'))
                    ->setProxyServer(config('browsershot.proxyServer'));

        // @codeCoverageIgnoreStart
        if (!empty(config('browsershot.chromePath'))) {
            $browsershot->setChromePath(config('browsershot.chromePath'));
        }

        if (config('browsershot.noSandbox')) {
            $browsershot->noSandbox();
        }

        foreach (config('browsershot.additionalOptions') as $key => $value) {
            $browsershot->setOption($key, $value);
        }
        // @codeCoverageIgnoreEnd

        $this->browsershot = $browsershot;

        $this->loadUrl($url);

        register_shutdown_function([$this, 'removeTemporaryFile']);
    }

    /**
     * Extension file of the generated output
     *
     * @return string
     */
    abstract protected function getFileExtension(): string;

    /**
     * Mime Type of the generated output
     *
     * @return string
     */
    abstract protected function getMimeType(): string;

    /**
     * Access underlying browsershot instance
     *
     * @return Browsershot
     */
    protected function browsershot(): Browsershot
    {
        return $this->browsershot;
    }

    /**
     * Gets the temp file path
     *
     * @return string
     */
    public function getTempFilePath(): string
    {
        $this->generateTempFile();

        return $this->tempFile;
    }

    /**
     * Reads the output from the generated temp file
     *
     * @return string|null
     */
    protected function getTempFileContents(): ?string
    {
        $this->generateTempFile();

        return file_get_contents($this->tempFile);
    }

    /**
     * Generates temp file
     *
     * @return Wrapper
     */
    protected function generateTempFile(): Wrapper
    {
        $tempFileName = tempnam(sys_get_temp_dir(), 'browsershot_output');

        $this->tempFile = $tempFileName . '.' . $this->getFileExtension();

        $this->browsershot()->save($this->tempFile);

        return $this;
    }

    /**
     * Delegates the call of methods to underlying Browsershot
     *
     * @param string $name
     * @param array $arguments
     * @return \Cavaon\Browsershot\Wrapper
     */
    public function __call($name, $arguments): Wrapper
    {
        try {
            $this->browsershot()->$name(...$arguments);
            return $this;
        } catch (\Error $e) {
            throw new \BadMethodCallException('Method ' . static::class . '::' . $name . '() does not exists');
        }
    }

    /**
     * Unlink temp files if any
     *
     * @codeCoverageIgnore
     * @return array
     */
    public function __sleep()
    {
        $this->removeTemporaryFile;

        return [];
    }

    public function __destruct()
    {
        $this->removeTemporaryFile();
    }

        /**
     * Removes all temporary files.
     */
    public function removeTemporaryFile()
    {
        $this->unlink($this->tempFile);
    }

    /**
     * Wrapper for the "unlink" function.
     *
     * @param string $filename
     *
     * @return bool
     */
    protected function unlink($filename)
    {
        return $this->fileExists($filename) ? @unlink($filename) : false;
    }

    /**
     * Wrapper for the "file_exists" function.
     *
     * @param string $filename
     *
     * @return bool
     */
    protected function fileExists($filename)
    {
        return file_exists($filename);
    }
}
