<?php
/**
 * @package   Commerce_Foundation
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Foundation\Ui\Component\Control\Button;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Declarative admin button that navigates to another route.
 */
class RedirectButton implements ButtonProviderInterface
{
    /**
     * @param string      $label      Button caption, already translated.
     * @param string      $route      Route id, or '*' for the current route.
     * @param string      $controller Controller name, or '*'.
     * @param string      $action     Action name, or '*'.
     * @param string|null $paramKey   Request parameter to forward, e.g. "id".
     * @param string|null $targetKey  Name to forward it under; defaults to $paramKey.
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
        private readonly Json $json,
        private readonly string $label,
        private readonly string $htmlClass = 'action-secondary',
        private readonly string $route = '*',
        private readonly string $controller = '*',
        private readonly string $action = '*',
        private readonly ?string $paramKey = null,
        private readonly ?string $targetKey = null,
        private readonly int $sortOrder = 10
    ) {
    }

    /**
     * The label is passed through rather than wrapped in __(); declare the
     * di.xml argument with translate="true" to have it translated there.
     *
     * @inheritDoc
     */
    public function getButtonData(): array
    {
        return [
            'class' => $this->htmlClass,
            'label' => $this->label,
            'on_click' => $this->buildOnClick(),
            'sort_order' => $this->sortOrder,
        ];
    }

    private function buildOnClick(): string
    {
        $params = [];

        if ($this->paramKey !== null) {
            $value = $this->request->getParam($this->paramKey);

            if ($value !== null && $value !== '') {
                $params[$this->targetKey ?? $this->paramKey] = $value;
            }
        }

        $url = $this->urlBuilder->getUrl(
            implode('/', [$this->route, $this->controller, $this->action]),
            $params
        );

        return sprintf('window.location.href = %s;', $this->json->serialize($url));
    }
}
