<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Test\Support;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use ReflectionProperty;

/**
 * Installs a test object manager and puts the previous one back, because the framework never does.
 */
trait ObjectManagerIsolation
{
    private ?ObjectManagerInterface $objectManagerBeforeIsolation = null;

    private bool $objectManagerWasSet = false;

    /**
     * Call from `setUp()`, before anything that reaches for the object manager.
     */
    protected function useObjectManager(ObjectManagerInterface $objectManager): void
    {
        $this->objectManagerBeforeIsolation = $this->currentObjectManager();
        $this->objectManagerWasSet = $this->objectManagerBeforeIsolation !== null;

        ObjectManager::setInstance($objectManager);
    }

    /**
     * Call from `tearDown()`; restoring "nothing was installed" needs reflection, because
     * `setInstance()` cannot be handed null.
     */
    protected function releaseObjectManager(): void
    {
        if ($this->objectManagerWasSet && $this->objectManagerBeforeIsolation !== null) {
            ObjectManager::setInstance($this->objectManagerBeforeIsolation);

            return;
        }

        $instance = new ReflectionProperty(ObjectManager::class, '_instance');
        $instance->setValue(null, null);
    }

    private function currentObjectManager(): ?ObjectManagerInterface
    {
        try {
            return ObjectManager::getInstance();
        } catch (\RuntimeException) {
            return null;
        }
    }
}
