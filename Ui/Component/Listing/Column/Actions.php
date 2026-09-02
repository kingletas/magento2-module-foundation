<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Configurable row-actions column.
 */
class Actions extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = [],
        private readonly string $indexField = 'entity_id'
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     *
     * @param  array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');
        // The field carrying the row id, and the URL parameter it is passed as.
        $idField = (string) ($this->getData('config/indexField') ?: $this->indexField);
        $urlParam = (string) ($this->getData('config/entityParam') ?: $idField);

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$idField])) {
                continue;
            }

            $actions = $this->buildActions((string) $item[$idField], $urlParam);

            if ($actions !== []) {
                $item[$name] = $actions;
            }
        }

        return $dataSource;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildActions(string $id, string $urlParam): array
    {
        $actions = [];

        $viewPath = (string) $this->getData('config/viewUrlPath');

        if ($viewPath !== '') {
            $actions['view'] = [
                'href' => $this->urlBuilder->getUrl($viewPath, [$urlParam => $id]),
                'label' => __('View'),
                'hidden' => false,
            ];
        }

        $editPath = (string) $this->getData('config/editUrlPath');

        if ($editPath !== '') {
            $actions['edit'] = [
                'href' => $this->urlBuilder->getUrl($editPath, [$urlParam => $id]),
                'label' => __('Edit'),
                'hidden' => false,
            ];
        }

        $deletePath = (string) $this->getData('config/deleteUrlPath');

        if ($deletePath !== '') {
            $actions['delete'] = [
                'href' => $this->urlBuilder->getUrl($deletePath, [$urlParam => $id]),
                'label' => __('Delete'),
                'hidden' => false,
                // Deletion is irreversible; the grid must ask first.
                'confirm' => [
                    'title' => __('Delete record'),
                    'message' => __(
                        'Are you sure you want to delete record "%1"? This cannot be undone.',
                        $this->escaper->escapeHtml($id)
                    ),
                ],
            ];
        }

        return $actions;
    }
}
