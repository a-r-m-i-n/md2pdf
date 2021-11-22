<?php

declare(strict_types = 1);

namespace Armin\Md2Pdf;

use Seld\PharUtils\Timestamps;
use Symfony\Component\Finder\Finder;

class Compiler
{
    private const PHAR_FILE = 'md2pdf.phar';
    private const BINARY_NAME = 'md2pdf';

    /**
     * Creates phar file.
     *
     * @throws \Exception
     */
    public static function compile(): void
    {
        echo 'Start compiling PHAR...' . PHP_EOL;
        if (file_exists(self::PHAR_FILE)) {
            echo 'Removing existing file ' . self::PHAR_FILE . '...' . PHP_EOL;
            unlink(self::PHAR_FILE);
        }
        if (!empty(Application::getApplicationVersionFromComposerJson())) {
            $pharPath = self::BINARY_NAME . '-' . Application::getApplicationVersionFromComposerJson() . '.phar';
            if (file_exists($pharPath)) {
                echo 'Removing existing file ' . $pharPath . '...' . PHP_EOL;
                unlink($pharPath);
            }
        }
        echo 'Creating new PHAR file...' . PHP_EOL;
        $phar = new \Phar(self::PHAR_FILE, 0, self::PHAR_FILE);
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        $phar->startBuffering();

        // Sort files by real path
        $finderSort = function (\SplFileInfo $a, \SplFileInfo $b) {
            return strcmp(str_replace('\\', '/', $a->getRealPath()), str_replace('\\', '/', $b->getRealPath()));
        };

        echo 'Collecting project files...' . PHP_EOL;
        $finder = new Finder();
        $finder->files()
               ->ignoreVCS(true)
               ->name('*.php')
               ->notName('Compiler.php')
               ->in(__DIR__ . '/../src')
               ->in(__DIR__ . '/../bin')
               ->sort($finderSort);
        foreach ($finder as $file) {
            self::addFile($phar, $file);
        }

        echo 'Collecting vendor files...' . PHP_EOL;
        $finder = new Finder();
        $finder->files()
               ->ignoreVCS(true)
               ->name('*.php')
               ->name('LICENSE')
               ->exclude('Tests')
               ->exclude('tests')
               ->exclude('docs')
               ->in(__DIR__ . '/../vendor/dflydev')
               ->in(__DIR__ . '/../vendor/league')
               ->in(__DIR__ . '/../vendor/mpdf')
               ->in(__DIR__ . '/../vendor/myclabs')
               ->in(__DIR__ . '/../vendor/nette')
               ->in(__DIR__ . '/../vendor/paragonie')
               ->in(__DIR__ . '/../vendor/symfony/console')
               ->in(__DIR__ . '/../vendor/symfony/deprecation-contracts')
               ->in(__DIR__ . '/../vendor/symfony/event-dispatcher')
               ->in(__DIR__ . '/../vendor/symfony/event-dispatcher-contracts')
               ->in(__DIR__ . '/../vendor/symfony/filesystem')
               ->in(__DIR__ . '/../vendor/symfony/finder')
               ->in(__DIR__ . '/../vendor/symfony/options-resolver')
               ->in(__DIR__ . '/../vendor/symfony/polyfill-*')
               ->in(__DIR__ . '/../vendor/symfony/process')
               ->in(__DIR__ . '/../vendor/symfony/service-contracts')
               ->in(__DIR__ . '/../vendor/symfony/stopwatch')
               ->in(__DIR__ . '/../vendor/symfony/string')
               ->in(__DIR__ . '/../vendor/symfony/yaml')
               ->in(__DIR__ . '/../vendor/psr/')
               ->in(__DIR__ . '/../vendor/setasign/')
               ->sort($finderSort);
        foreach ($finder as $file) {
            self::addFile($phar, $file);
        }

        echo 'Collecting asset files...' . PHP_EOL;
        // Add required assets to PHAR
        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*')
            ->in(__DIR__ . '/../vendor/mpdf/mpdf/data')
            ->sort($finderSort);
        foreach ($finder as $file) {
            self::addFile($phar, $file);
        }
        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('DejaVuSerif*.ttf')
            ->in(__DIR__ . '/../vendor/mpdf/mpdf/ttfonts')
            ->sort($finderSort);
        foreach ($finder as $file) {
            self::addFile($phar, $file);
        }

        echo 'Add autoloader and binaries...' . PHP_EOL;
        // More files to add
        self::addComposerAutoloader($phar);
        self::addBinary($phar);

        // Set stub
        $phar->setStub(self::getStub());
        $phar->stopBuffering();

        echo 'Add LICENSE and composer.json...' . PHP_EOL;
        // disabled for interoperability with systems without gzip ext
        // $phar->compressFiles(\Phar::GZ);
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../LICENSE'), false);
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../composer.json'), false);
        unset($phar);

        echo 'Updating timestamps...' . PHP_EOL;
        // re-sign the phar with reproducible timestamp / signature
        $util = new Timestamps(self::PHAR_FILE);
        $util->updateTimestamps((new \DateTime())->getTimestamp());

        echo 'Saving...' . PHP_EOL;
        $util->save(self::PHAR_FILE, \Phar::SHA1);

        $pharPath = self::PHAR_FILE;
        if (!empty(Application::getApplicationVersionFromComposerJson())) {
            $pharPath = self::BINARY_NAME . '-' . Application::getApplicationVersionFromComposerJson() . '.phar';
            $r = rename(self::PHAR_FILE, $pharPath);
            if (!$r) {
                throw new \RuntimeException(sprintf('Unable to rename %s to %s', self::PHAR_FILE, $pharPath));
            }
        }
        echo 'Saved: ' . $pharPath . ' (' . round(filesize($pharPath) / 1024 / 1024, 2) . ' MB)' . PHP_EOL;
    }

    private static function addFile(\Phar $phar, \SplFileInfo $file, bool $strip = true): void
    {
        $path = self::getRelativeFilePath($file);
        $content = file_get_contents($file->getRealPath());
        if ($strip) {
            $content = self::stripWhitespace($content);
        } elseif ('LICENSE' === $file->getBasename()) {
            $content = "\n" . $content . "\n";
        }
        $phar->addFromString($path, $content);
    }

    /**
     * @param \SplFileInfo $file
     */
    private static function getRelativeFilePath($file): string
    {
        $realPath = $file->getRealPath();
        $pathPrefix = \dirname(__DIR__) . DIRECTORY_SEPARATOR;
        $pos = strpos($realPath, $pathPrefix);
        $relativePath = (false !== $pos) ? substr_replace($realPath, '', $pos, strlen($pathPrefix)) : $realPath;

        return str_replace('\\', '/', $relativePath);
    }

    /**
     * @param \Phar $phar
     */
    private static function addBinary($phar): void
    {
        $content = file_get_contents(__DIR__ . '/../bin/' . self::BINARY_NAME);
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('bin/' . self::BINARY_NAME, $content);

        $content = file_get_contents(__DIR__ . '/../bin/bootstrap.php');
        $phar->addFromString('bin/bootstrap.php', $content);
    }

    private static function getStub(): string
    {
        $stub = <<<'EOF'
            #!/usr/bin/env php
            <?php
            // Avoid APC causing random fatal errors per https://github.com/composer/composer/issues/264
            if (extension_loaded('apc') && ini_get('apc.enable_cli') && ini_get('apc.cache_by_default')) {
                if (version_compare(phpversion('apc'), '3.0.12', '>=')) {
                    ini_set('apc.cache_by_default', 0);
                } else {
                    fwrite(STDERR, 'Warning: APC <= 3.0.12 may cause fatal errors when running commands.'.PHP_EOL);
                    fwrite(STDERR, 'Update APC, or set apc.enable_cli or apc.cache_by_default to 0 in your php.ini.'.PHP_EOL);
                }
            }
            EOF;

        return $stub . "Phar::mapPhar('" . self::BINARY_NAME . ".phar');\n\n" .
            'require \'phar://' . self::BINARY_NAME . '.phar/bin/' . self::BINARY_NAME . '\';' . "\n" .
            '__HALT_COMPILER();';
    }

    private static function isBinary($str): bool
    {
        return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
    }

    private static function stripWhitespace(string $source): string
    {
        if (!\function_exists('token_get_all') || self::isBinary($source)) {
            return $source;
        }
        $output = '';
        foreach (token_get_all($source) as $token) {
            if (\is_string($token)) {
                $output .= $token;
            } elseif (\in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }

    private static function addComposerAutoloader(\Phar $phar): void
    {
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/autoload.php'));
        if (file_exists(__DIR__ . '/../vendor/composer/installed.json')) {
            self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/installed.json'));
        }
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/autoload_namespaces.php'));
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/autoload_psr4.php'));
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/autoload_classmap.php'));
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/autoload_files.php'));
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/autoload_real.php'));
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/autoload_static.php'));
        if (file_exists(__DIR__ . '/../vendor/composer/include_paths.php')) {
            self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/include_paths.php'));
        }
        if (file_exists(__DIR__ . '/../vendor/composer/platform_check.php')) {
            self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/platform_check.php'));
        }
        self::addFile($phar, new \SplFileInfo(__DIR__ . '/../vendor/composer/ClassLoader.php'));
    }
}
