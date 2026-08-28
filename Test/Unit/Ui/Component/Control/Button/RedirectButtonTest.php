<?php
/**
 * RedirectButtonTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Ui\Component\Control\Button;

use Commerce\Foundation\Ui\Component\Control\Button\RedirectButton;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RedirectButtonTest extends TestCase
{
    private RequestInterface&MockObject $request;
    private UrlInterface&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
    }

    public function testTheLabelClassAndSortOrderArePassedThrough(): void
    {
        $this->urlBuilder->method('getUrl')->willReturn('https://admin.test/back/');

        $data = $this->button(['label' => 'Back', 'htmlClass' => 'action-primary', 'sortOrder' => 5])
            ->getButtonData();

        self::assertSame('Back', $data['label']);
        self::assertSame('action-primary', $data['class']);
        self::assertSame(5, $data['sort_order']);
    }

    public function testTheRouteIsAssembledFromItsThreeParts(): void
    {
        $this->urlBuilder->expects(self::once())
            ->method('getUrl')
            ->with('commerce_share/cart/index', [])
            ->willReturn('https://admin.test/commerce_share/cart/');

        $this->button(['route' => 'commerce_share', 'controller' => 'cart', 'action' => 'index'])
            ->getButtonData();
    }

    /**
     * `*` is Magento's same-as-current wildcard and reaches the URL builder
     * untouched.
     */
    public function testTheCurrentRouteWildcardsAreForwardedUnchanged(): void
    {
        $this->urlBuilder->expects(self::once())
            ->method('getUrl')
            ->with('*/*/index', [])
            ->willReturn('https://admin.test/here/');

        $this->button(['action' => 'index'])->getButtonData();
    }

    public function testTheConfiguredRequestParameterIsForwarded(): void
    {
        $this->request->method('getParam')->with('id')->willReturn('42');
        $this->urlBuilder->expects(self::once())
            ->method('getUrl')
            ->with('*/*/edit', ['id' => '42'])
            ->willReturn('https://admin.test/edit/id/42/');

        $this->button(['action' => 'edit', 'paramKey' => 'id'])->getButtonData();
    }

    /**
     * A grid keyed on `alert_id` still links to a route expecting `id`; without
     * the rename the target controller sees no id at all.
     */
    public function testTheParameterCanBeForwardedUnderADifferentName(): void
    {
        $this->request->method('getParam')->with('alert_id')->willReturn('7');
        $this->urlBuilder->expects(self::once())
            ->method('getUrl')
            ->with('*/*/edit', ['id' => '7'])
            ->willReturn('https://admin.test/edit/id/7/');

        $this->button(['action' => 'edit', 'paramKey' => 'alert_id', 'targetKey' => 'id'])
            ->getButtonData();
    }

    /**
     * An absent parameter is not forwarded as an empty one, which would be a
     * different URL.
     */
    public function testAnAbsentOrEmptyParameterIsOmittedEntirely(): void
    {
        $this->request->method('getParam')->willReturn(null);
        $this->urlBuilder->expects(self::exactly(2))
            ->method('getUrl')
            ->with('*/*/index', [])
            ->willReturn('https://admin.test/here/');

        $this->button(['action' => 'index', 'paramKey' => 'id'])->getButtonData();

        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getParam')->willReturn('');
        $this->button(['action' => 'index', 'paramKey' => 'id'])->getButtonData();
    }

    public function testTheHandlerNavigatesToTheBuiltUrl(): void
    {
        $this->urlBuilder->method('getUrl')->willReturn('https://admin.test/grid/');

        self::assertSame(
            'window.location.href = "https:\/\/admin.test\/grid\/";',
            $this->button([])->getButtonData()['on_click']
        );
    }

    /**
     * The URL carries a request parameter into an inline script.
     */
    public function testAQuoteBearingUrlCannotBreakOutOfTheHandler(): void
    {
        $hostile = 'https://admin.test/edit/id/";alert(1);"/';
        $this->urlBuilder->method('getUrl')->willReturn($hostile);

        $onClick = $this->button(['paramKey' => 'id'])->getButtonData()['on_click'];

        self::assertStringNotContainsString('";alert(1);"', $onClick);
        self::assertStringContainsString('\\"', $onClick);
        self::assertSame(
            'window.location.href = ' . (new Json())->serialize($hostile) . ';',
            $onClick
        );
    }

    /**
     * A trailing backslash would escape the closing quote the encoder emits.
     */
    public function testATrailingBackslashCannotEscapeTheClosingQuote(): void
    {
        $this->urlBuilder->method('getUrl')->willReturn('https://admin.test/x\\');

        $onClick = $this->button([])->getButtonData()['on_click'];

        self::assertStringEndsWith('\\\\";', $onClick);
    }

    /**
     * The data is rendered inside the admin page's own <script> block.
     */
    public function testAScriptClosingSequenceIsEscaped(): void
    {
        $this->urlBuilder->method('getUrl')->willReturn('https://admin.test/</script><img src=x onerror=alert(1)>');

        $onClick = $this->button([])->getButtonData()['on_click'];

        self::assertStringNotContainsString('</script>', $onClick);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function button(array $overrides): RedirectButton
    {
        $config = $overrides + [
            'label' => 'Back',
            'htmlClass' => 'action-secondary',
            'route' => '*',
            'controller' => '*',
            'action' => '*',
            'paramKey' => null,
            'targetKey' => null,
            'sortOrder' => 10,
        ];

        return new RedirectButton(
            $this->request,
            $this->urlBuilder,
            new Json(),
            $config['label'],
            $config['htmlClass'],
            $config['route'],
            $config['controller'],
            $config['action'],
            $config['paramKey'],
            $config['targetKey'],
            $config['sortOrder']
        );
    }
}
