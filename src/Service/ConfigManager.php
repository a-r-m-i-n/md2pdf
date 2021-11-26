<?php

declare(strict_types = 1);

namespace Armin\Md2Pdf\Service;

use Armin\Md2Pdf\Configuration;
use Symfony\Component\Yaml\Yaml;

class ConfigManager
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    public function load(): Configuration
    {
        $yaml = Yaml::parseFile($this->path);

        return new Configuration($yaml);
    }
}
