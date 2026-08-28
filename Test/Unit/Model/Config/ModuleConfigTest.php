<?php
/**
 * ModuleConfigTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Config;

use Commerce\Foundation\Model\Config\ModuleConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ModuleConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private ModuleConfig $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new ModuleConfig($this->scopeConfig, 'acme_thing');
    }

    public function testQualifiesPathsWithTheConfiguredSection(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('getValue')
            ->with('acme_thing/general/name', ScopeInterface::SCOPE_STORE, 7)
            ->willReturn('value');

        self::assertSame('value', $this->config->getString('general/name', '', 7));
    }

    public function testALeadingSlashOnTheRelativePathIsTolerated(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('getValue')
            ->with('acme_thing/general/name', self::anything(), self::anything())
            ->willReturn('v');

        $this->config->getString('/general/name');
    }

    public function testTypedGettersFallBackWhenTheValueIsUnusable(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        self::assertSame('fallback', $this->config->getString('a', 'fallback'));
        self::assertSame(9, $this->config->getInt('a', 9));
        self::assertSame(1.5, $this->config->getFloat('a', 1.5));
        self::assertSame([], $this->config->getList('a'));
    }

    /**
     * A misconfigured 0 batch size turns a chunked loop into an infinite one,
     * so zero and negatives must fall back rather than being honoured.
     */
    public function testPositiveIntRejectsZeroAndNegatives(): void
    {
        $this->scopeConfig->method('getValue')->willReturnOnConsecutiveCalls('0', '-5', '25');

        self::assertSame(100, $this->config->getPositiveInt('batch', 100));
        self::assertSame(100, $this->config->getPositiveInt('batch', 100));
        self::assertSame(25, $this->config->getPositiveInt('batch', 100));
    }

    public function testListIsTrimmedAndStrippedOfBlanks(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(' a@x.com , , b@x.com ,');

        self::assertSame(['a@x.com', 'b@x.com'], $this->config->getList('recipients'));
    }

    /**
     * Config values arrive from the database as strings, so `(bool) '0'` is the
     * single most common source of "the toggle does nothing".
     */
    public function testFlagsGoThroughIsSetFlagRatherThanACast(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('acme_thing/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        self::assertFalse($this->config->isSetFlag('general/enabled'));
    }

    public function testExposesItsSection(): void
    {
        self::assertSame('acme_thing', $this->config->getSection());
    }
}
