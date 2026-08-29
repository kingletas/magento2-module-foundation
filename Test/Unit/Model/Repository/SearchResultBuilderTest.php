<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Model\Repository;

use Commerce\Foundation\Model\Repository\SearchResultBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PHPUnit\Framework\TestCase;

/**
 * Most of this class is one line of ordering, and that line is the whole point.
 */
class SearchResultBuilderTest extends TestCase
{
    public function testTheCriteriaGoesThroughTheCoreCollectionProcessor(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $collection = $this->collection();
        $processor = $this->createMock(CollectionProcessorInterface::class);

        $processor->expects($this->once())
            ->method('process')
            ->with($criteria, $collection);

        $this->builder($processor)->build($criteria, $collection);
    }

    public function testTheResultCarriesTheCriteriaTheCountAndTheItems(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $items = [new \stdClass(), new \stdClass()];
        $collection = $this->collection(size: 57, items: $items);

        $results = $this->builder()->build($criteria, $collection);

        $this->assertSame($criteria, $results->getSearchCriteria());
        $this->assertSame(57, $results->getTotalCount());
        $this->assertSame($items, $results->getItems());
    }

    /**
     * `getSize()` is asked before `getItems()` loads the page, or the total
     * describes the page.
     */
    public function testTheTotalIsReadBeforeTheItemsAreLoaded(): void
    {
        $order = [];
        $collection = $this->collection(size: 57, items: [], order: $order);

        $this->builder()->build($this->createMock(SearchCriteriaInterface::class), $collection);

        $this->assertSame(['getSize', 'getItems'], $collection->calls);
    }

    /**
     * An empty filtered result is a total of 0 rather than a null the caller
     * has to guess about.
     */
    public function testAnEmptyResultIsZeroAndAnEmptyArray(): void
    {
        $results = $this->builder()->build(
            $this->createMock(SearchCriteriaInterface::class),
            $this->collection(size: 0, items: [])
        );

        $this->assertSame(0, $results->getTotalCount());
        $this->assertSame([], $results->getItems());
    }

    private function builder(?CollectionProcessorInterface $processor = null): SearchResultBuilder
    {
        $factory = $this->createMock(SearchResultsInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (): SearchResults => new SearchResults());

        return new SearchResultBuilder(
            $processor ?? $this->createMock(CollectionProcessorInterface::class),
            $factory
        );
    }

    /**
     * @param object[] $items
     * @param string[] $order
     */
    private function collection(int $size = 0, array $items = [], array $order = []): AbstractCollection
    {
        return new class ($size, $items) extends AbstractCollection {
            /** @var string[] */
            public array $calls = [];

            /**
             * @param object[] $collectionItems
             */
            public function __construct(private readonly int $size, private readonly array $collectionItems)
            {
                // `parent::__construct` is not called: none of what the base
                // needs is reachable here.
            }

            public function getSize()
            {
                $this->calls[] = 'getSize';

                return $this->size;
            }

            public function getItems()
            {
                $this->calls[] = 'getItems';

                return $this->collectionItems;
            }
        };
    }
}
