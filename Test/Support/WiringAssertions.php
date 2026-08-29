<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Test\Support;

use ReflectionClass;
use ReflectionMethod;
use SimpleXMLElement;

/**
 * The module's XML against the code it names.
 */
trait WiringAssertions
{
    use ModuleFiles;

    /**
     * Every XML file in `etc/` parses.
     */
    public function assertEveryConfigFileParses(string $moduleDir): void
    {
        $broken = [];

        foreach ($this->allEtcXml($moduleDir) as $file) {
            if ($this->loadXml($file) === null) {
                $broken[] = $this->relative($moduleDir, $file);
            }
        }

        $this->assertSame([], $broken, "These files do not parse as XML:\n  " . implode("\n  ", $broken));
    }

    /**
     * Every observer in `events.xml` names something that can be built.
     */
    public function assertEveryObserverExists(string $moduleDir): void
    {
        $problems = [];

        foreach ($this->etcFiles($moduleDir, 'events.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->event as $event) {
                foreach ($event->observer as $observer) {
                    $instance = (string) $observer['instance'];

                    if (!$this->isResolvableName($moduleDir, $instance)) {
                        $problems[] = sprintf(
                            '%s: observer "%s" on event "%s" names %s, which is neither a class in this '
                            . 'module nor a virtualType it declares',
                            $this->relative($moduleDir, $file),
                            (string) $observer['name'],
                            (string) $event['name'],
                            $instance
                        );
                    }
                }
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * Every cron job names a class that exists and a method it really has.
     */
    public function assertEveryCronJobIsCallable(string $moduleDir): void
    {
        $problems = [];

        foreach ($this->etcFiles($moduleDir, 'crontab.xml') as $file) {
            foreach ($this->cronJobs($moduleDir, $file) as $problem) {
                $problems[] = $problem;
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * Every registered console command takes its name from `di.xml`.
     */
    public function assertEveryCommandIsNamedInDi(string $moduleDir): void
    {
        $problems = [];
        $names = [];

        foreach ($this->registeredCommands($moduleDir) as $class) {
            $name = $this->declaredCommandName($moduleDir, $class);

            if ($name === null) {
                $problems[] = sprintf('%s is registered but no di.xml argument names it.', $class);
                continue;
            }

            if (isset($names[$name])) {
                $problems[] = sprintf('%s and %s are both named %s.', $names[$name], $class, $name);
            }

            $names[$name] = $class;

            $file = $this->fileForClass($moduleDir, $class);

            if ($file !== null && preg_match('/->setName\(/', (string) file_get_contents($file)) === 1) {
                $problems[] = sprintf('%s calls setName(); the name belongs in di.xml.', $class);
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * @return string[] Every class listed under CommandListInterface.
     */
    private function registeredCommands(string $moduleDir): array
    {
        $commands = [];

        foreach ($this->etcFiles($moduleDir, 'di.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            $items = $xml->xpath(
                '//type[@name="Magento\\Framework\\Console\\CommandListInterface"]'
                . '/arguments/argument[@name="commands"]/item'
            ) ?: [];

            foreach ($items as $item) {
                $commands[] = trim((string) $item);
            }
        }

        return $commands;
    }

    private function declaredCommandName(string $moduleDir, string $class): ?string
    {
        foreach ($this->etcFiles($moduleDir, 'di.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            $arguments = $xml->xpath(
                sprintf('//type[@name="%s"]/arguments/argument[@name="name"]', $class)
            ) ?: [];

            foreach ($arguments as $argument) {
                $name = trim((string) $argument);

                if ($name !== '') {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Every REST route names a service that exists, is authorised, and returns
     * something Magento can actually serialise.
     */
    public function assertEveryWebApiRouteIsServiceable(string $moduleDir): void
    {
        $problems = [];

        foreach ($this->etcFiles($moduleDir, 'webapi.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            $declared = $this->declaredAclResources($moduleDir);

            foreach ($xml->route as $route) {
                foreach ($this->webApiRouteProblems($moduleDir, $file, $route, $declared) as $problem) {
                    $problems[] = $problem;
                }
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * @param string[] $declaredResources
     * @return string[]
     */
    private function webApiRouteProblems(
        string $moduleDir,
        string $file,
        SimpleXMLElement $route,
        array $declaredResources
    ): array {
        $where = sprintf(
            '%s: %s %s',
            $this->relative($moduleDir, $file),
            (string) $route['method'],
            (string) $route['url']
        );
        $class = (string) ($route->service['class'] ?? '');
        $method = (string) ($route->service['method'] ?? '');
        $problems = [];

        if (!interface_exists($class) && !class_exists($class)) {
            return [sprintf('%s names %s, which does not exist', $where, $class)];
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->hasMethod($method)) {
            return [sprintf('%s names %s::%s(), which does not exist', $where, $class, $method)];
        }

        foreach ($this->routeResourceProblems($where, $route, $declaredResources) as $problem) {
            $problems[] = $problem;
        }

        foreach ($this->routeUrlParameterProblems($where, $route, $reflection->getMethod($method)) as $problem) {
            $problems[] = $problem;
        }

        foreach ($this->returnTypeProblems($where, $reflection->getMethod($method)) as $problem) {
            $problems[] = $problem;
        }

        return $problems;
    }

    /**
     * @param string[] $declaredResources
     * @return string[]
     */
    private function routeResourceProblems(
        string $where,
        SimpleXMLElement $route,
        array $declaredResources
    ): array {
        $refs = [];

        foreach ($route->resources->resource ?? [] as $resource) {
            $refs[] = (string) $resource['ref'];
        }

        if ($refs === []) {
            return [sprintf('%s declares no <resources>, so nothing decides who may call it', $where)];
        }

        $problems = [];

        foreach ($refs as $ref) {
            // Only this module's own ids are checkable from here; a core one is
            // declared in somebody else's acl.xml.
            if (str_starts_with($ref, 'Magento_') || $ref === 'anonymous' || $ref === 'self') {
                continue;
            }

            if (!in_array($ref, $declaredResources, true)) {
                $problems[] = sprintf(
                    '%s is authorised against "%s", which this module\'s acl.xml does not declare',
                    $where,
                    $ref
                );
            }
        }

        return $problems;
    }

    /**
     * @return string[]
     */
    private function routeUrlParameterProblems(
        string $where,
        SimpleXMLElement $route,
        ReflectionMethod $method
    ): array {
        $names = [];

        foreach ($method->getParameters() as $parameter) {
            $names[] = $parameter->getName();
        }

        $problems = [];

        if (preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', (string) $route['url'], $matches) === false) {
            return $problems;
        }

        foreach ($matches[1] as $placeholder) {
            if (!in_array($placeholder, $names, true)) {
                $problems[] = sprintf(
                    '%s has ":%s" in its URL and %s() has no parameter of that name, so it is never filled in',
                    $where,
                    $placeholder,
                    $method->getName()
                );
            }
        }

        return $problems;
    }

    /**
     * Everything Magento has to reflect on the way out.
     *
     * @return string[]
     */
    private function returnTypeProblems(string $where, ReflectionMethod $method): array
    {
        $problems = [];
        $annotation = $this->returnAnnotation($method);

        if ($annotation === null) {
            return [sprintf(
                '%s: %s::%s() has no @return annotation, and TypeProcessor reads the type from there rather '
                . 'than from the signature',
                $where,
                $method->getDeclaringClass()->getShortName(),
                $method->getName()
            )];
        }

        foreach ($this->dataInterfacesIn($annotation) as $interface) {
            foreach ($this->gettersWithoutReturnAnnotation($interface) as $getter) {
                $problems[] = sprintf(
                    '%s returns %s, whose %s() has no @return annotation - the first call that reaches it '
                    . 'throws from inside TypeProcessor',
                    $where,
                    $interface,
                    $getter
                );
            }
        }

        return $problems;
    }

    private function returnAnnotation(ReflectionMethod $method): ?string
    {
        $docBlock = $method->getDocComment();

        if ($docBlock === false || preg_match('/@return\s+([^\s]+)/', $docBlock, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * The data interfaces named by a `@return`, array notation included.
     *
     * @return string[]
     */
    private function dataInterfacesIn(string $annotation): array
    {
        $interfaces = [];

        foreach (explode('|', $annotation) as $type) {
            $type = ltrim(rtrim(trim($type), '[]'), '\\');

            if ($type !== '' && interface_exists($type) && str_contains($type, '\\Api\\Data\\')) {
                $interfaces[] = $type;
            }
        }

        return $interfaces;
    }

    /**
     * @return string[]
     */
    private function gettersWithoutReturnAnnotation(string $interface): array
    {
        $missing = [];

        foreach ((new ReflectionClass($interface))->getMethods() as $method) {
            $name = $method->getName();

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            if (!str_starts_with($name, 'get') && !str_starts_with($name, 'is') && !str_starts_with($name, 'has')) {
                continue;
            }

            if ($this->returnAnnotation($method) === null) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Every resource id this module's `acl.xml` declares, at any depth.
     *
     * @return string[]
     */
    private function declaredAclResources(string $moduleDir): array
    {
        $ids = [];

        foreach ($this->etcFiles($moduleDir, 'acl.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($this->aclPaths($xml->acl->resources[0] ?? null) as $path) {
                foreach ($path as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @return string[]
     */
    private function cronJobs(string $moduleDir, string $file): array
    {
        $xml = $this->loadXml($file);

        if ($xml === null) {
            return [];
        }

        $problems = [];

        foreach ($xml->group as $group) {
            foreach ($group->job as $job) {
                $instance = (string) $job['instance'];
                $method = (string) $job['method'];
                $target = $this->concreteClassFor($moduleDir, $instance);

                if ($target === null) {
                    $problems[] = sprintf(
                        '%s: job "%s" names %s, which does not exist',
                        $this->relative($moduleDir, $file),
                        (string) $job['name'],
                        $instance
                    );
                    continue;
                }

                if (!method_exists($target, $method)) {
                    $problems[] = sprintf(
                        '%s: job "%s" calls %s::%s(), which does not exist',
                        $this->relative($moduleDir, $file),
                        (string) $job['name'],
                        $target,
                        $method
                    );
                }

                // A job is scheduled by a literal <schedule> or by a
                // <config_path> pointing at one.
                if ((string) $job->schedule === '' && (string) $job->config_path === '') {
                    $problems[] = sprintf(
                        '%s: job "%s" has neither <schedule> nor <config_path>, so it never runs',
                        $this->relative($moduleDir, $file),
                        (string) $job['name']
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * Consumers, topics and queues agree with each other.
     */
    public function assertMessageQueueWiringAgrees(string $moduleDir): void
    {
        $problems = array_merge(
            $this->consumerHandlerProblems($moduleDir),
            $this->topicProblems($moduleDir)
        );

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * @return string[]
     */
    private function consumerHandlerProblems(string $moduleDir): array
    {
        $problems = [];
        $boundQueues = $this->boundQueues($moduleDir);

        foreach ($this->etcFiles($moduleDir, 'queue_consumer.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->consumer as $consumer) {
                $name = (string) $consumer['name'];
                $handler = (string) $consumer['handler'];
                [$class, $method] = array_pad(explode('::', $handler, 2), 2, '');
                $target = $this->concreteClassFor($moduleDir, $class);

                if ($target === null) {
                    $problems[] = sprintf('consumer "%s" is handled by %s, which does not exist', $name, $class);
                } elseif ($method !== '' && !method_exists($target, $method)) {
                    $problems[] = sprintf(
                        'consumer "%s" calls %s::%s(), which does not exist',
                        $name,
                        $target,
                        $method
                    );
                }

                $queue = (string) $consumer['queue'];

                if ($boundQueues !== [] && !in_array($queue, $boundQueues, true)) {
                    $problems[] = sprintf(
                        'consumer "%s" reads queue "%s", which no <binding> in queue_topology.xml '
                        . 'delivers to - published messages are routed nowhere and dropped silently',
                        $name,
                        $queue
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * @return string[]
     */
    private function topicProblems(string $moduleDir): array
    {
        $problems = [];
        $declared = [];

        foreach ($this->etcFiles($moduleDir, 'communication.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->topic as $topic) {
                $name = (string) $topic['name'];
                $declared[] = $name;

                // The attribute has to be *present and false*.
                if ((string) ($topic['is_synchronous'] ?? '') !== 'false') {
                    $problems[] = sprintf(
                        'topic "%s" does not say is_synchronous="false"; an omitted value defaults to '
                        . 'true, so it is published inline and its consumer never runs',
                        $name
                    );
                }
            }
        }

        if ($declared === []) {
            return $problems;
        }

        foreach ($this->publishedTopics($moduleDir) as $topic => $where) {
            if (!in_array($topic, $declared, true)) {
                $problems[] = sprintf(
                    '%s publishes topic "%s", which communication.xml does not declare',
                    $where,
                    $topic
                );
            }
        }

        return $problems;
    }

    /**
     * @return string[]
     */
    private function boundQueues(string $moduleDir): array
    {
        $queues = [];

        foreach ($this->etcFiles($moduleDir, 'queue_topology.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->exchange as $exchange) {
                foreach ($exchange->binding as $binding) {
                    if ((string) $binding['destinationType'] === 'queue') {
                        $queues[] = (string) $binding['destination'];
                    }
                }
            }
        }

        return $queues;
    }

    /**
     * Topics named by a publisher, wherever they are declared.
     *
     * @return array<string, string> Topic => the file that names it.
     */
    private function publishedTopics(string $moduleDir): array
    {
        $topics = [];

        foreach ($this->etcFiles($moduleDir, 'queue_publisher.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->publisher as $publisher) {
                $topics[(string) $publisher['topic']] = $this->relative($moduleDir, $file);
            }
        }

        return $topics;
    }

    /**
     * A config-section ACL resource hangs from the exact core chain.
     */
    public function assertAclConfigResourceUsesTheCoreChain(string $moduleDir): void
    {
        $problems = [];

        foreach ($this->etcFiles($moduleDir, 'acl.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($this->aclPaths($xml->acl->resources[0] ?? null) as $path) {
                $index = array_search('Magento_Config::config', $path, true);

                if ($index === false) {
                    continue;
                }

                $chain = array_slice($path, 0, $index + 1);

                if ($chain !== $this->aclConfigChain()) {
                    $problems[] = sprintf(
                        "%s declares Magento_Config::config under\n      %s\n    and the only chain that "
                        . "re-parents the core node instead of duplicating it is\n      %s",
                        $this->relative($moduleDir, $file),
                        implode(' > ', $chain),
                        implode(' > ', $this->aclConfigChain())
                    );
                }
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * Every root-to-leaf path of resource ids.
     *
     * @param  string[] $prefix
     * @return array<int, string[]>
     */
    private function aclPaths(?SimpleXMLElement $resources, array $prefix = []): array
    {
        if ($resources === null) {
            return [];
        }

        $paths = [];

        foreach ($resources->resource as $resource) {
            $path = $prefix;
            $path[] = (string) $resource['id'];
            $paths[] = $path;

            foreach ($this->aclPaths($resource, $path) as $child) {
                $paths[] = $child;
            }
        }

        return $paths;
    }

    /**
     * Every admin controller is actually protected, by a resource that exists.
     */
    public function assertEveryAdminControllerRequiresLogin(string $moduleDir): void
    {
        $problems = [];
        $declared = $this->aclResourceIds($moduleDir);

        foreach ($this->adminControllers($moduleDir) as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            if (!$reflection->isSubclassOf(\Magento\Backend\App\AbstractAction::class)) {
                $problems[] = sprintf(
                    '%s does not extend Magento\Backend\App\Action, so nothing checks the session or the '
                    . 'ACL before it runs - implementing HttpGetActionInterface alone protects nothing',
                    $class
                );
                continue;
            }

            $resource = $reflection->getConstant('ADMIN_RESOURCE');

            if (!is_string($resource) || $resource === '') {
                $problems[] = sprintf('%s declares no ADMIN_RESOURCE', $class);
                continue;
            }

            if ($declared !== [] && !in_array($resource, $declared, true)
                && !str_starts_with($resource, 'Magento_')
            ) {
                $problems[] = sprintf(
                    '%s is guarded by "%s", which this module\'s acl.xml does not declare',
                    $class,
                    $resource
                );
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * @return string[]
     */
    private function adminControllers(string $moduleDir): array
    {
        [$prefix, $dir] = $this->psr4($moduleDir);
        $classes = [];

        foreach ($this->sourceFiles($moduleDir) as $file) {
            if (!str_contains($file, '/Controller/Adminhtml/')) {
                continue;
            }

            $relative = substr($file, strlen($dir) + 1, -strlen('.php'));
            $classes[] = $prefix . str_replace('/', '\\', $relative);
        }

        return $classes;
    }

    /**
     * @return string[]
     */
    private function aclResourceIds(string $moduleDir): array
    {
        $ids = [];

        foreach ($this->etcFiles($moduleDir, 'acl.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($this->aclPaths($xml->acl->resources[0] ?? null) as $path) {
                foreach ($path as $id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * A UI grid data source is declared in the *global* `di.xml`.
     */
    public function assertGridDataSourcesAreDeclaredGlobally(string $moduleDir): void
    {
        $problems = [];
        $factory = 'Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory';

        foreach ($this->diFiles($moduleDir) as $file) {
            if (str_ends_with($file, '/etc/di.xml')) {
                continue;
            }

            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->type as $type) {
                if (trim((string) $type['name'], '\\') === $factory) {
                    $problems[] = sprintf(
                        '%s declares the UI CollectionFactory. Area arguments replace the global array '
                        . 'wholesale rather than adding to it, which unregisters every core grid in that '
                        . 'area. Move the <type> to etc/di.xml',
                        $this->relative($moduleDir, $file)
                    );
                }
            }
        }

        $this->assertSame([], $problems, implode("\n  ", $problems));
    }

    /**
     * Every field a store can set has a `config.xml` default.
     *
     * @param string[] $deliberatelyUnset Config paths that have no default on purpose.
     */
    public function assertEverySettingHasADefault(string $moduleDir, array $deliberatelyUnset = []): void
    {
        $fields = $this->systemXmlPaths($moduleDir);
        $defaults = $this->configXmlPaths($moduleDir);
        $section = $this->configSection($moduleDir);
        $problems = [];

        foreach ($fields as $path => $isSecret) {
            if ($isSecret || in_array($path, $defaults, true) || in_array($path, $deliberatelyUnset, true)) {
                continue;
            }

            $problems[] = sprintf(
                '%s has no default in config.xml, so it reads as null until somebody opens the form '
                . 'and saves it. Add one, or name it in settingsWithNoDefault() with the reason',
                $path
            );
        }

        foreach ($deliberatelyUnset as $path) {
            if (!isset($fields[$path])) {
                $problems[] = sprintf(
                    '%s is named as deliberately unset but no longer appears in system.xml - drop it '
                    . 'from settingsWithNoDefault()',
                    $path
                );
                continue;
            }

            if (in_array($path, $defaults, true)) {
                $problems[] = sprintf(
                    '%s is named as deliberately unset and now has a default in config.xml - drop it '
                    . 'from settingsWithNoDefault()',
                    $path
                );
            }
        }

        $this->assertSame(
            [],
            $problems,
            ($section === '' ? '' : sprintf("Section %s:\n  ", $section)) . implode("\n  ", $problems)
        );
    }

    /**
     * Every settable field, and whether it is a credential.
     *
     * @return array<string, bool> Full config path => is an encrypted secret.
     */
    private function systemXmlPaths(string $moduleDir): array
    {
        $paths = [];

        foreach ($this->etcFiles($moduleDir, 'system.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->system->section ?? [] as $section) {
                foreach ($section->group ?? [] as $group) {
                    foreach ($group->field ?? [] as $field) {
                        $path = sprintf(
                            '%s/%s/%s',
                            (string) $section['id'],
                            (string) $group['id'],
                            (string) $field['id']
                        );
                        $paths[$path] = (string) ($field['type'] ?? '') === 'obscure'
                            || str_contains((string) $field->backend_model, 'Backend\\Encrypted');
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * @return string[]
     */
    private function configXmlPaths(string $moduleDir): array
    {
        $paths = [];

        foreach ($this->etcFiles($moduleDir, 'config.xml') as $file) {
            $xml = $this->loadXml($file);

            if ($xml === null) {
                continue;
            }

            foreach ($xml->children() as $scope) {
                foreach ($scope->children() as $section) {
                    foreach ($section->children() as $group) {
                        foreach ($group->children() as $field) {
                            $paths[] = sprintf('%s/%s/%s', $section->getName(), $group->getName(), $field->getName());
                        }
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * The concrete class a di.xml name resolves to, following one virtualType
     * hop, or null when nothing on disk answers to it.
     */
    private function concreteClassFor(string $moduleDir, string $name): ?string
    {
        $name = ltrim($name, '\\');
        $virtualTypes = $this->virtualTypes($moduleDir);
        $seen = [];

        while (isset($virtualTypes[$name]) && !isset($seen[$name])) {
            $seen[$name] = true;
            $name = $virtualTypes[$name];
        }

        if (class_exists($name)) {
            return $name;
        }

        $file = $this->fileForClass($moduleDir, $name);

        return $file !== null && is_file($file) ? $name : null;
    }

    /**
     * The chain a config-section ACL resource must hang from, in order.
     *
     * @return string[]
     */
    private function aclConfigChain(): array
    {
        return [
            'Magento_Backend::admin',
            'Magento_Backend::stores',
            'Magento_Backend::stores_settings',
            'Magento_Config::config',
        ];
    }

    private function relative(string $moduleDir, string $file): string
    {
        return str_starts_with($file, $moduleDir . '/') ? substr($file, strlen($moduleDir) + 1) : $file;
    }
}
