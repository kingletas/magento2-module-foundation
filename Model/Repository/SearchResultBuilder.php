<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Model\Repository;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Turns a SearchCriteria plus a collection into a SearchResults.
 */
class SearchResultBuilder
{
    public function __construct(
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * Apply $criteria to $collection and wrap the outcome in a SearchResults.
     */
    public function build(
        SearchCriteriaInterface $criteria,
        AbstractCollection $collection,
        ?SearchResultsInterface $results = null
    ): SearchResultsInterface {
        $this->collectionProcessor->process($criteria, $collection);

        /** @var SearchResultsInterface $results */
        $results ??= $this->searchResultsFactory->create();
        $results->setSearchCriteria($criteria);
        // getSize() runs its own COUNT with the filters but without the LIMIT,
        // so it must be asked before getItems() triggers the paged load.
        $results->setTotalCount($collection->getSize());
        $results->setItems($collection->getItems());

        return $results;
    }
}
