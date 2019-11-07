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
     * @var string
     */
    protected $temporaryFolder;

    /**
     * @var array
     */
    public $temporaryFiles = [];

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

        register_shutdown_function([$this, 'removeTemporaryFiles']);
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
     * Get TemporaryFolder.
     *
     * @return string
     */
    public function getTemporaryFolder()
    {
        if ($this->temporaryFolder === null) {
            return sys_get_temp_dir();
        }

        return $this->temporaryFolder;
    }

    /**
     * Set temporaryFolder.
     *
     * @param string $temporaryFolder
     *
     * @return $this
     */
    public function setTemporaryFolder($temporaryFolder)
    {
        $this->temporaryFolder = $temporaryFolder;

        return $this;
    }

    /**
     * Reads the output from the generated temp file
     *
     * @return string|null
     */
    protected function getTempFileContents()
    {
        $filename=$this->generateTempFile();
        return file_get_contents($filename);
    }

    /**
     * Creates a temporary file.
     * The file is not created if the $content argument is null.
     *
     * @param string $content   Optional content for the temporary file
     * @param string $extension An optional extension for the filename
     *
     * @return string The filename
     */
    protected function generateTempFile($content = null, $extension = null)
    {
        $dir = rtrim($this->getTemporaryFolder(), DIRECTORY_SEPARATOR);

        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf("Unable to create directory: %s\n", $dir));
            }
        } elseif (!is_writable($dir)) {
            throw new \RuntimeException(sprintf("Unable to write in directory: %s\n", $dir));
        }

        $filename = $dir . DIRECTORY_SEPARATOR . uniqid('browsershot_output', true);

        if (null === $extension) {
            $extension=$this->getFileExtension();
        }
        $filename .= '.' . $extension;

        if (null !== $content) {
            file_put_contents($filename, $content);
        }else{
            $this->browsershot()->save($filename);
        }

        $this->temporaryFiles[] = $filename;

        return $filename;
    }

    /**
     * Delegates the call of methods to underlying Browsershot
     *
     * @param string $name
     * @param array $arguments
     * @return \Cavaon\Browsershot\Wrapper
     */
    public function __call($name, $arguments)
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
        $this->removeTemporaryFiles();

        return [];
    }

    public function __destruct()
    {
        $this->removeTemporaryFiles();
    }

        /**
     * Removes all temporary files.
     */
    public function removeTemporaryFiles()
    {
        foreach ($this->temporaryFiles as $file) {
            $this->unlink($file);
        }
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
