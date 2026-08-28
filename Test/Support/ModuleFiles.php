<?php
/**
 * ModuleFiles.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Support;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;

/**
 * Reading a module off disk, the way Magento reads it.
 */
trait ModuleFiles
{
    /**
     * Every PHP file in the module that is not a test.
     *
     * @return string[]
     */
    private static function sourceFiles(string $moduleDir): array
    {
        $files = [];

        foreach (self::phpFiles($moduleDir) as $file) {
            if (!str_contains($file, '/Test/')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return string[]
     */
    private static function phpFiles(string $moduleDir): array
    {
        $files = [];

        if (!is_dir($moduleDir)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && !str_contains($file->getPathname(), '/vendor/')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Every `di.xml` in the module, global and per-area.
     *
     * @return string[]
     */
    private static function diFiles(string $moduleDir): array
    {
        return self::etcFiles($moduleDir, 'di.xml');
    }

    /**
     * Every file of the given name under `etc/`, global scope first and then
     * each area directory.
     *
     * @return string[]
     */
    private static function etcFiles(string $moduleDir, string $fileName): array
    {
        $files = [];
        $etc = $moduleDir . '/etc';

        if (!is_dir($etc)) {
            return $files;
        }

        if (is_file($etc . '/' . $fileName)) {
            $files[] = $etc . '/' . $fileName;
        }

        foreach (new DirectoryIterator($etc) as $entry) {
            if ($entry->isDir() && !$entry->isDot() && is_file($entry->getPathname() . '/' . $fileName)) {
                $files[] = $entry->getPathname() . '/' . $fileName;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Every XML file the module ships under `etc/`, at any depth.
     *
     * @return string[]
     */
    private static function allEtcXml(string $moduleDir): array
    {
        $files = [];
        $etc = $moduleDir . '/etc';

        if (!is_dir($etc)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($etc));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'xml') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Parse one XML file, or null when it will not parse at all.
     */
    private static function loadXml(string $file): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }

    /**
     * Every element below the given one, at any depth.
     *
     * @return SimpleXMLElement[]
     */
    private static function descendants(SimpleXMLElement $element): array
    {
        $found = [];

        foreach ($element->children() as $child) {
            $found[] = $child;

            foreach (self::descendants($child) as $descendant) {
                $found[] = $descendant;
            }
        }

        return $found;
    }

    /**
     * The `<virtualType name="...">` declared anywhere in the module.
     *
     * @return array<string, string> Virtual type name => the type it extends.
     */
    private static function virtualTypes(string $moduleDir): array
    {
        $virtualTypes = [];

        foreach (self::diFiles($moduleDir) as $file) {
            $xml = self::loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->virtualType as $virtualType) {
                $virtualTypes[(string) $virtualType['name']] = (string) $virtualType['type'];
            }
        }

        return $virtualTypes;
    }

    /**
     * The module's PSR-4 prefix and the directory it maps to.
     *
     * @return array{0: string, 1: string} Namespace prefix, absolute directory.
     */
    private static function psr4(string $moduleDir): array
    {
        $manifest = $moduleDir . '/composer.json';

        if (!is_file($manifest)) {
            return ['', $moduleDir];
        }

        $decoded = json_decode((string) file_get_contents($manifest), true);

        foreach ($decoded['autoload']['psr-4'] ?? [] as $namespace => $relative) {
            return [(string) $namespace, rtrim($moduleDir . '/' . $relative, '/')];
        }

        return ['', $moduleDir];
    }

    /**
     * The file a class name would live in, or null when it is outside this
     * module's namespace and therefore somebody else's to provide.
     */
    private static function fileForClass(string $moduleDir, string $class): ?string
    {
        [$prefix, $dir] = self::psr4($moduleDir);
        $class = ltrim($class, '\\');

        if ($prefix === '' || !str_starts_with($class . '\\', $prefix)) {
            return null;
        }

        return $dir . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }

    /**
     * Whether the module can supply this name: a file of its own, a virtualType
     * it declares, or a class the autoloader already knows about.
     */
    private static function isResolvableName(string $moduleDir, string $name): bool
    {
        $name = ltrim($name, '\\');
        $virtualTypes = self::virtualTypes($moduleDir);

        if (isset($virtualTypes[$name])) {
            return true;
        }

        foreach (['\\Proxy', '\\Interceptor', '\\Factory'] as $generated) {
            if (str_ends_with($name, $generated)) {
                return self::isResolvableName($moduleDir, substr($name, 0, -strlen($generated)));
            }
        }

        $file = self::fileForClass($moduleDir, $name);

        if ($file !== null) {
            return is_file($file);
        }

        return class_exists($name) || interface_exists($name);
    }

    /**
     * The module name in `etc/module.xml`, e.g. "Commerce_ShareCart".
     */
    private static function moduleName(string $moduleDir): string
    {
        $xml = is_file($moduleDir . '/etc/module.xml')
            ? self::loadXml($moduleDir . '/etc/module.xml')
            : null;

        return $xml === null ? '' : (string) ($xml->module['name'] ?? '');
    }

    /**
     * The `<argument name="section">` given to this module's config reader.
     */
    private static function configSection(string $moduleDir): string
    {
        foreach (self::diFiles($moduleDir) as $file) {
            $xml = self::loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->type as $type) {
                foreach ($type->arguments->argument ?? [] as $argument) {
                    if ((string) $argument['name'] === 'section') {
                        return trim((string) $argument);
                    }
                }
            }
        }

        return '';
    }
}
