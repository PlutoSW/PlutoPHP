<?php

namespace Pluto\Core;

class Storage
{
    private $storage = __DIR__ . '/../../storage';
    private $path = '/';
    public function __construct()
    {
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777);
        }
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setPath($path)
    {
        $path = \trim($path, '/');
        $this->path = $this->storage . "/" . $path . '/';
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777);
        }
        return $this;
    }

    public function files(string $ext = '*')
    {
        $files =  glob($this->path . $ext);
        $return = [];
        foreach ($files as $file) {
            $return[] = str_replace($this->path, '', $file);
        }
        return $return;
    }

    public function fileExists($file)
    {
        return file_exists($this->path . $file);
    }

    public function isFile($file)
    {
        return is_file($this->path . $file);
    }

    public function isDirectory($file)
    {
        return is_dir($this->path . $file);
    }

    public function upload($filename, $file)
    {
        return \move_uploaded_file($file['tmp_name'], $this->path . $filename);
    }

    public function getContents($file)
    {
        return file_get_contents($this->path . $file);
    }

    public function setContents($file, $content)
    {
        return file_put_contents($this->path . $file, $content);
    }

    public function fileMtime($file)
    {
        return filemtime($this->path . $file);
    }

    public function fileCtime($file)
    {
        return filectime($this->path . $file);
    }

    public function fileSize($file)
    {
        return \filesize($this->path . $file);
    }

    public function unlink($file)
    {
        return unlink($this->path . $file);
    }

    public function chmod($file, $mode)
    {
        return \chmod($this->path . $file, $mode);
    }

    public function realPath($file)
    {
        $baseName = basename($this->storage);
        $path = \explode($baseName, $this->path . $file);
        $file = $path[1];
        return '/' . $baseName . $file;
    }

    public function fileUrl($file)
    {
        $host = \getenv('HOST');
        return $host . $this->realPath($file);
    }
}
