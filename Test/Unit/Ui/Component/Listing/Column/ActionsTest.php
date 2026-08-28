<?php
/**
 * ActionsTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Ui\Component\Listing\Column;

use Commerce\Foundation\Ui\Component\Listing\Column\Actions;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ActionsTest extends TestCase
{
    private UrlInterface&MockObject $urlBuilder;
    private Escaper&MockObject $escaper;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->urlBuilder->method('getUrl')->willReturnCallback(
            static function (string $path, array $params = []): string {
                return 'https://admin.test/' . $path . '/' . http_build_query($params);
            }
        );

        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnCallback(
            static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
        );
    }

    public function testEachConfiguredRouteBecomesAnAction(): void
    {
        $column = $this->column([
            'viewUrlPath' => 'commerce/alert/view',
            'editUrlPath' => 'commerce/alert/edit',
            'deleteUrlPath' => 'commerce/alert/delete',
        ]);

        $actions = $this->firstItem($column, [['entity_id' => 42]])['actions'];

        $this->assertSame(['view', 'edit', 'delete'], array_keys($actions));
        $this->assertSame('https://admin.test/commerce/alert/view/entity_id=42', $actions['view']['href']);
        $this->assertSame('https://admin.test/commerce/alert/edit/entity_id=42', $actions['edit']['href']);
        $this->assertSame('View', (string) $actions['view']['label']);
        $this->assertFalse($actions['view']['hidden']);
    }

    /**
     * A grid configuring one route gets that route only, never placeholders for
     * the others.
     */
    public function testOnlyTheConfiguredRoutesAreRendered(): void
    {
        $column = $this->column(['viewUrlPath' => 'commerce/alert/view']);

        $this->assertSame(['view'], array_keys($this->firstItem($column, [['entity_id' => 42]])['actions']));
    }

    /**
     * With nothing configured the column contributes nothing rather than an
     * empty actions array.
     */
    public function testAnUnconfiguredColumnLeavesTheRowUntouched(): void
    {
        $item = $this->firstItem($this->column([]), [['entity_id' => 42]]);

        $this->assertSame(['entity_id' => 42], $item);
    }

    public function testTheIdFieldDefaultsToEntityIdAndIsConfigurable(): void
    {
        $column = $this->column(['indexField' => 'alert_id', 'viewUrlPath' => 'commerce/alert/view']);

        $actions = $this->firstItem($column, [['alert_id' => 7]])['actions'];

        $this->assertSame('https://admin.test/commerce/alert/view/alert_id=7', $actions['view']['href']);
    }

    /**
     * The constructor default is what a grid gets when the ui_component XML
     * says nothing, so it has to be honoured as well as the XML override.
     */
    public function testTheConstructorDefaultIdFieldIsUsedWhenTheXmlIsSilent(): void
    {
        $column = $this->column(['viewUrlPath' => 'commerce/alert/view'], 'alert_id');

        $actions = $this->firstItem($column, [['alert_id' => 7]])['actions'];

        $this->assertSame('https://admin.test/commerce/alert/view/alert_id=7', $actions['view']['href']);
    }

    /**
     * The bug `entityParam` exists to fix: a grid keyed on `alert_id` linking
     * to a route that expects `id`.
     */
    public function testTheRowIsGuardedOnTheSameFieldTheLinkIsBuiltFrom(): void
    {
        $column = $this->column([
            'indexField' => 'alert_id',
            'entityParam' => 'id',
            'viewUrlPath' => 'commerce/alert/view',
        ]);

        $actions = $this->firstItem($column, [['alert_id' => 7]])['actions'];

        $this->assertSame('https://admin.test/commerce/alert/view/id=7', $actions['view']['href']);
    }

    /**
     * A grid row can arrive without the key - a left join with no match, or a
     * collection selecting a narrower column set.
     */
    public function testARowMissingTheIdFieldIsSkipped(): void
    {
        $column = $this->column(['viewUrlPath' => 'commerce/alert/view']);

        $items = $this->prepare($column, [['other' => 1], ['entity_id' => 42]])['data']['items'];

        $this->assertSame(['other' => 1], $items[0]);
        $this->assertArrayHasKey('actions', $items[1]);
    }

    /**
     * Deletion is irreversible and the action links are one click apart, so it
     * is confirmed.
     */
    public function testDeleteCarriesAConfirmationNamingTheRecord(): void
    {
        $column = $this->column(['deleteUrlPath' => 'commerce/alert/delete']);

        $delete = $this->firstItem($column, [['entity_id' => 42]])['actions']['delete'];

        $this->assertArrayHasKey('confirm', $delete);
        $this->assertStringContainsString('42', (string) $delete['confirm']['message']);
        $this->assertStringContainsString('cannot be undone', (string) $delete['confirm']['message']);
    }

    /**
     * The id reaches the confirmation dialog as markup.
     */
    public function testTheIdInTheConfirmationIsEscaped(): void
    {
        $column = $this->column(['deleteUrlPath' => 'commerce/alert/delete']);

        $delete = $this->firstItem($column, [['entity_id' => '<img src=x onerror=alert(1)>']])['actions']['delete'];

        $this->assertStringNotContainsString('<img', (string) $delete['confirm']['message']);
    }

    public function testADataSourceWithoutItemsIsReturnedUnchanged(): void
    {
        $column = $this->column(['viewUrlPath' => 'commerce/alert/view']);

        $this->assertSame([], $column->prepareDataSource([]));
        $this->assertSame(
            ['data' => ['items' => 'not-an-array']],
            $column->prepareDataSource(['data' => ['items' => 'not-an-array']])
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function column(array $config, string $indexField = 'entity_id'): Actions
    {
        return new Actions(
            $this->createMock(ContextInterface::class),
            $this->createMock(UiComponentFactory::class),
            $this->urlBuilder,
            $this->escaper,
            [],
            ['name' => 'actions', 'config' => $config],
            $indexField
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function prepare(Actions $column, array $items): array
    {
        return $column->prepareDataSource(['data' => ['items' => $items]]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function firstItem(Actions $column, array $items): array
    {
        return $this->prepare($column, $items)['data']['items'][0];
    }
}
