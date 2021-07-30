<?php

namespace Permafrost\RayScan;

use Permafrost\PhpCodeSearch\Results\FileSearchResults;
use Permafrost\PhpCodeSearch\Searcher;
use Permafrost\RayScan\Configuration\Configuration;
use Symfony\Component\Finder\Finder;

class CodeScanner
{
    /** @var Configuration */
    protected $config;

    /** @var array */
    protected $paths;

    public function __construct(Configuration $config, $path)
    {
        if (! is_array($path)) {
            $path = [$path];
        }

        $this->config = $config;
        $this->paths = $this->loadDirectoryFiles($path);
    }

    public function scanFile(string $file): FileSearchResults
    {
        $searcher = new Searcher();

        return $searcher
            ->functions(['ray', 'rd'])
            ->methods(['ray'])
            ->static(['Ray'])
            ->classes(['Ray'])
            ->search($file);
    }

    public function scan(?array $paths = null, ?callable $postScanCallback = null): array
    {
        $paths = $paths ?? $this->paths();

        $results = [];

        foreach($paths as $path) {
            if ($this->isPathIgnored($path)) {
                continue;
            }

            $scanResults = $this->scanFile($path);

            if ($postScanCallback) {
                $postScanCallback();
            }

            if (! $scanResults) {
                continue;
            }

            if ($scanResults->hasErrors()) {
                // TODO: handle scan errors
            }

            if (! $scanResults->hasErrors() && count($scanResults->results)) {
                $results[] = $scanResults;
            }
        }

        return $results;
    }

    public function paths(): array
    {
        return $this->paths;
    }

    protected function loadDirectoryFiles(array $paths): array
    {
        $result = [];

        foreach($paths as $path) {
            if (is_file($path)) {
                $result[] = realpath($path);
                continue;
            }

            $finder = Finder::create()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->ignoreVCSIgnored(file_exists("{$path}/.gitignore"))
                ->ignoreUnreadableDirs(true)
                ->in($path)
                ->exclude($this->config->ignorePaths)
                ->exclude('vendor')
                ->exclude('node_modules')
                ->name('*.php')
                ->files();

            foreach ($finder as $file) {
                $result[] = $file;
            }
        }

        return $result;
    }

    protected function isPathIgnored(string $path): bool
    {
        if (in_array($path, $this->config->ignorePaths, true)) {
            return true;
        }

        if (in_array(basename($path), $this->config->ignorePaths, true)) {
            return true;
        }

        foreach($this->config->ignorePaths as $ignoreFile) {
            $ignoreFile = str_replace(['*', '?', '~'], ['.*', '.', '\\~'], $ignoreFile);

            if (preg_match('~' . $ignoreFile . '~', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
