<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model;

use Commerce\Foundation\Model\Registry;
use Magento\Framework\Exception\AlreadyExistsException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * A stored null is a value, and setting a key twice is a collision either way.
 */
class RegistryTest extends TestCase
{
    public function testAValueComesBackOut(): void
    {
        $registry = new Registry();
        $registry->set('order', 42);

        $this->assertSame(42, $registry->get('order'));
    }

    public function testAnAbsentKeyReturnsTheDefault(): void
    {
        $this->assertNull((new Registry())->get('absent'));
        $this->assertSame('fallback', (new Registry())->get('absent', 'fallback'));
    }

    /**
     * A stored null must win over the default.
     */
    public function testAStoredNullIsReturnedRatherThanTheDefault(): void
    {
        $registry = new Registry();
        $registry->set('parent_sku', null);

        $this->assertNull($registry->get('parent_sku', 'fallback'));
        $this->assertTrue($registry->has('parent_sku'));
    }

    public function testSettingAKeyTwiceThrows(): void
    {
        $registry = new Registry();
        $registry->set('order', 1);

        $this->expectException(AlreadyExistsException::class);
        $this->expectExceptionMessage('order');

        $registry->set('order', 2);
    }

    /**
     * Graceful means "leave what is there", not "overwrite quietly".
     */
    public function testAGracefulSetKeepsTheExistingValue(): void
    {
        $registry = new Registry();
        $registry->set('order', 1);
        $registry->set('order', 2, graceful: true);

        $this->assertSame(1, $registry->get('order'));
    }

    public function testAGracefulSetOnAStoredNullIsStillACollision(): void
    {
        $registry = new Registry();
        $registry->set('order', null);
        $registry->set('order', 'replacement', graceful: true);

        $this->assertNull($registry->get('order'), 'A key holding null is a key that is taken.');
    }

    public function testReplaceOverwritesWithoutComplaint(): void
    {
        $registry = new Registry();
        $registry->set('order', 1);
        $registry->replace('order', 2);

        $this->assertSame(2, $registry->get('order'));
    }

    public function testReplaceWorksOnAKeyThatWasNeverSet(): void
    {
        $registry = new Registry();
        $registry->replace('order', 2);

        $this->assertSame(2, $registry->get('order'));
    }

    public function testRemoveFreesTheKeyForReuse(): void
    {
        $registry = new Registry();
        $registry->set('order', 1);
        $registry->remove('order');

        $this->assertFalse($registry->has('order'));
        $registry->set('order', 2);
        $this->assertSame(2, $registry->get('order'));
    }

    public function testRemovingAnAbsentKeyIsNotAnError(): void
    {
        $registry = new Registry();
        $registry->remove('never-set');

        $this->assertFalse($registry->has('never-set'));
    }

    public function testFlushEmptiesEverything(): void
    {
        $registry = new Registry();
        $registry->set('a', 1);
        $registry->set('b', 2);
        $registry->flush();

        $this->assertFalse($registry->has('a'));
        $this->assertFalse($registry->has('b'));
    }

    /**
     * Calling `__destruct()` on a stored object when clearing it runs teardown
     * once explicitly and again when PHP collects the object.
     */
    public function testFlushDoesNotRunTeardownOnStoredObjects(): void
    {
        $registry = new Registry();
        $object = new class extends stdClass {
            public int $destructCalls = 0;

            public function __destruct()
            {
                $this->destructCalls++;
            }
        };

        $registry->set('service', $object);
        $registry->flush();

        $this->assertSame(0, $object->destructCalls);
    }

    public function testRemoveDoesNotRunTeardownOnAStoredObject(): void
    {
        $registry = new Registry();
        $object = new class extends stdClass {
            public int $destructCalls = 0;

            public function __destruct()
            {
                $this->destructCalls++;
            }
        };

        $registry->set('service', $object);
        $registry->remove('service');

        $this->assertSame(0, $object->destructCalls);
    }

    public function testTheSameObjectComesBackRatherThanACopy(): void
    {
        $registry = new Registry();
        $object = new stdClass();
        $registry->set('service', $object);

        $this->assertSame($object, $registry->get('service'));
    }

    public function testFalseAndZeroAndEmptyStringAreAllStoredValues(): void
    {
        $registry = new Registry();
        $registry->set('false', false);
        $registry->set('zero', 0);
        $registry->set('empty', '');

        $this->assertFalse($registry->get('false', 'default'));
        $this->assertSame(0, $registry->get('zero', 'default'));
        $this->assertSame('', $registry->get('empty', 'default'));
    }
}
