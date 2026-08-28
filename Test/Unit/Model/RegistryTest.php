<?php
/**
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
 * The registry's whole job is to be boring, and the two ways it stops being
 * boring are both about `null`.
 */
final class RegistryTest extends TestCase
{
    public function testAValueComesBackOut(): void
    {
        $registry = new Registry();
        $registry->set('order', 42);

        self::assertSame(42, $registry->get('order'));
    }

    public function testAnAbsentKeyReturnsTheDefault(): void
    {
        self::assertNull((new Registry())->get('absent'));
        self::assertSame('fallback', (new Registry())->get('absent', 'fallback'));
    }

    /**
     * A stored null must win over the default.
     */
    public function testAStoredNullIsReturnedRatherThanTheDefault(): void
    {
        $registry = new Registry();
        $registry->set('parent_sku', null);

        self::assertNull($registry->get('parent_sku', 'fallback'));
        self::assertTrue($registry->has('parent_sku'));
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

        self::assertSame(1, $registry->get('order'));
    }

    public function testAGracefulSetOnAStoredNullIsStillACollision(): void
    {
        $registry = new Registry();
        $registry->set('order', null);
        $registry->set('order', 'replacement', graceful: true);

        self::assertNull($registry->get('order'), 'A key holding null is a key that is taken.');
    }

    public function testReplaceOverwritesWithoutComplaint(): void
    {
        $registry = new Registry();
        $registry->set('order', 1);
        $registry->replace('order', 2);

        self::assertSame(2, $registry->get('order'));
    }

    public function testReplaceWorksOnAKeyThatWasNeverSet(): void
    {
        $registry = new Registry();
        $registry->replace('order', 2);

        self::assertSame(2, $registry->get('order'));
    }

    public function testRemoveFreesTheKeyForReuse(): void
    {
        $registry = new Registry();
        $registry->set('order', 1);
        $registry->remove('order');

        self::assertFalse($registry->has('order'));
        $registry->set('order', 2);
        self::assertSame(2, $registry->get('order'));
    }

    public function testRemovingAnAbsentKeyIsNotAnError(): void
    {
        $registry = new Registry();
        $registry->remove('never-set');

        self::assertFalse($registry->has('never-set'));
    }

    public function testFlushEmptiesEverything(): void
    {
        $registry = new Registry();
        $registry->set('a', 1);
        $registry->set('b', 2);
        $registry->flush();

        self::assertFalse($registry->has('a'));
        self::assertFalse($registry->has('b'));
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

        self::assertSame(0, $object->destructCalls);
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

        self::assertSame(0, $object->destructCalls);
    }

    public function testTheSameObjectComesBackRatherThanACopy(): void
    {
        $registry = new Registry();
        $object = new stdClass();
        $registry->set('service', $object);

        self::assertSame($object, $registry->get('service'));
    }

    public function testFalseAndZeroAndEmptyStringAreAllStoredValues(): void
    {
        $registry = new Registry();
        $registry->set('false', false);
        $registry->set('zero', 0);
        $registry->set('empty', '');

        self::assertFalse($registry->get('false', 'default'));
        self::assertSame(0, $registry->get('zero', 'default'));
        self::assertSame('', $registry->get('empty', 'default'));
    }
}
