<?php
/**
 * CopyColumnMigrator.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Fake;

use Commerce\Foundation\Model\Setup\Patch\AbstractColumnMigrator;

/**
 * The smallest possible concrete migrator, which is what a store's own subclass
 * looks like.
 */
class CopyColumnMigrator extends AbstractColumnMigrator
{
}
