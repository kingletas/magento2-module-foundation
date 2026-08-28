<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Support;

use SimpleXMLElement;

/**
 * Checks a module's `di.xml` against its own constructors.
 */
trait DiWiringAssertions
{
    use ModuleFiles;

    /**
     * Every interface a constructor in this module asks for is one the object
     * manager can actually supply.
     *
     * @param string   $moduleDir Absolute path to the module root.
     * @param string[] $templates Classes a store is expected to complete.
     */
    public function assertEveryInjectedInterfaceIsResolvable(
        string $moduleDir,
        array $templates = []
    ): void {
        $interfaces = $this->declaredInterfaces($moduleDir);
        $constructors = $this->constructorParameters($moduleDir);
        [$preferences, $arguments] = $this->diConfiguration($moduleDir);

        $unresolvable = [];

        foreach ($constructors as $class => $parameters) {
            if (in_array($class, $templates, true)) {
                continue;
            }

            foreach ($parameters as [$type, $name]) {
                if (!in_array($type, $interfaces, true) || in_array($type, $preferences, true)) {
                    continue;
                }

                if (in_array($name, $arguments[$class] ?? [], true)) {
                    continue;
                }

                $unresolvable[] = sprintf('%s::__construct($%s) needs %s', $class, $name, $type);
            }
        }

        $this->assertSame(
            [],
            $unresolvable,
            "The object manager cannot build these. Add a <preference> for the interface, or an "
            . "<argument> naming the implementation on the consuming <type>:\n  "
            . implode("\n  ", $unresolvable)
        );
    }

    /**
     * Every `<preference>` in this module points at a class that exists and
     * actually implements what it claims to.
     *
     * @param string $moduleDir Absolute path to the module root.
     */
    public function assertEveryPreferenceResolvesToAnImplementation(string $moduleDir): void
    {
        $problems = [];

        foreach ($this->preferencePairs($moduleDir) as [$for, $type]) {
            if (!interface_exists($for) && !class_exists($for)) {
                $problems[] = sprintf('%s is preferred but does not exist', $for);
                continue;
            }

            if (!class_exists($type)) {
                $problems[] = sprintf('%s is preferred to %s, which does not exist', $for, $type);
                continue;
            }

            if (!is_subclass_of($type, $for) && $type !== $for) {
                $problems[] = sprintf('%s does not implement %s', $type, $for);
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * Nothing in this module is named `…\Proxy` unless the generator can
     * actually produce it.
     *
     * @param string $moduleDir Absolute path to the module root.
     */
    public function assertNoVirtualTypeIsReferencedThroughAGeneratedProxy(string $moduleDir): void
    {
        $virtualTypes = [];
        $proxyReferences = [];

        foreach ($this->diFiles($moduleDir) as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->virtualType as $virtualType) {
                $virtualTypes[] = (string) $virtualType['name'];
            }

            foreach ($this->descendants($xml) as $node) {
                $value = trim((string) $node);

                if (str_ends_with($value, '\\Proxy')) {
                    $proxyReferences[] = $value;
                }
            }
        }

        $broken = [];

        // A virtualType may not be named `…\Proxy` at all.
        foreach ($virtualTypes as $virtualType) {
            if (str_ends_with($virtualType, '\\Proxy')) {
                $broken[] = sprintf(
                    '%s is a virtualType named with the reserved \Proxy suffix; '
                    . 'name it \Lazy (or anything else) and setup:di:compile will accept it',
                    $virtualType
                );
            }
        }

        // And nothing may reference the proxy of a virtualType, which is a
        // class the generator has nothing to generate from.
        foreach (array_unique($proxyReferences) as $reference) {
            $target = substr($reference, 0, -strlen('\\Proxy'));

            if (in_array($target, $virtualTypes, true)) {
                $broken[] = sprintf(
                    '%s proxies the virtualType %s, which the generator cannot produce',
                    $reference,
                    $target
                );
            }
        }

        $this->assertSame([], array_values(array_unique($broken)), implode("\n  ", $broken));
    }

    /**
     * @return string[] Fully qualified interface names declared in the module.
     */
    private function declaredInterfaces(string $moduleDir): array
    {
        $interfaces = [];

        foreach ($this->sourceFiles($moduleDir) as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) === 1
                && preg_match('/^\s*interface\s+(\w+)/m', $source, $name) === 1
            ) {
                $interfaces[] = trim($namespace[1]) . '\\' . $name[1];
            }
        }

        return $interfaces;
    }

    /**
     * @return array<string, array<int, array{0: string, 1: string}>> Class => [[type, parameter name], …].
     */
    private function constructorParameters(string $moduleDir): array
    {
        $constructors = [];

        foreach ($this->sourceFiles($moduleDir) as $file) {
            $source = (string) file_get_contents($file);

            if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1) {
                continue;
            }

            if (preg_match('/^\s*(?:abstract\s+)?class\s+(\w+)/m', $source, $name) !== 1) {
                continue;
            }

            $currentNamespace = trim($namespace[1]);
            $class = $currentNamespace . '\\' . $name[1];

            if (preg_match('/public function __construct\((.*?)\)\s*\{/s', $source, $signature) !== 1) {
                continue;
            }

            $imports = [];

            foreach ($this->useStatements($source) as $fqcn => $alias) {
                $imports[$alias] = $fqcn;
            }

            $parameters = [];

            foreach (preg_split('/,(?![^(]*\))/', $signature[1]) ?: [] as $chunk) {
                if (preg_match('/([\w\\\\|?]+)\s+\$(\w+)/', $chunk, $parameter) !== 1) {
                    continue;
                }

                $type = ltrim($parameter[1], '?');

                // A union type is satisfied by any of its arms; the container
                // never has to pick, so it is not this check's business.
                if (str_contains($type, '|')) {
                    continue;
                }

                $parameters[] = [
                    ltrim(
                        $imports[$type] ?? (str_contains($type, '\\') ? $type : $currentNamespace . '\\' . $type),
                        '\\'
                    ),
                    $parameter[2],
                ];
            }

            $constructors[$class] = $parameters;
        }

        return $constructors;
    }

    /**
     * @return array<string, string> Fully qualified name => alias.
     */
    private function useStatements(string $source): array
    {
        preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $source, $matches, PREG_SET_ORDER);

        $imports = [];

        foreach ($matches as $match) {
            $fqcn = $match[1];
            $imports[$fqcn] = $match[2] ?? substr((string) strrchr('\\' . $fqcn, '\\'), 1);
        }

        return $imports;
    }

    /**
     * @return array{0: string[], 1: array<string, string[]>} Preferences, and type => explicitly named arguments.
     */
    private function diConfiguration(string $moduleDir): array
    {
        $preferences = [];
        $arguments = [];

        foreach ($this->diFiles($moduleDir) as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->preference as $preference) {
                $preferences[] = (string) $preference['for'];
            }

            foreach (['type', 'virtualType'] as $element) {
                foreach ($xml->{$element} as $node) {
                    $name = (string) $node['name'];
                    $target = (string) ($node['type'] ?? '') ?: $name;

                    foreach ($node->arguments->argument ?? [] as $argument) {
                        $arguments[$target][] = (string) $argument['name'];
                        $arguments[$name][] = (string) $argument['name'];
                    }
                }
            }
        }

        return [$preferences, $arguments];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function preferencePairs(string $moduleDir): array
    {
        $pairs = [];

        foreach ($this->diFiles($moduleDir) as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->preference as $preference) {
                $pairs[] = [(string) $preference['for'], (string) $preference['type']];
            }
        }

        return $pairs;
    }
}
