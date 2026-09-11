<?php
/**
 * @package   Kingletas_Foundation
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\Foundation\Ui\Component\Control\Button;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Declarative form button (Save, Save and Continue, ...).
 */
class FormActionButton implements ButtonProviderInterface
{
    /** @var array<string, mixed> */
    private const array DEFAULT_MAGE_INIT = ['button' => ['event' => 'save']];

    /**
     * @param string               $label     Button caption, already translated.
     * @param string               $htmlClass CSS classes.
     * @param string               $formRole  data-form-role attribute the form listens for.
     * @param array<string, mixed> $mageInit  Overrides merged over the default mage-init.
     * @param int                  $sortOrder Position among sibling buttons.
     */
    public function __construct(
        private readonly string $label,
        private readonly string $htmlClass = 'action-secondary',
        private readonly string $formRole = 'save',
        private readonly array $mageInit = [],
        private readonly int $sortOrder = 10
    ) {
    }

    /**
     * The label is passed through rather than wrapped in __(); declare the
     * di.xml argument with translate="true" to have it translated there.
     *
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'class' => $this->htmlClass,
            'label' => $this->label,
            'data_attribute' => [
                'form-role' => $this->formRole,
                'mage-init' => array_replace_recursive(self::DEFAULT_MAGE_INIT, $this->mageInit),
            ],
            'on_click' => '',
            'sort_order' => $this->sortOrder,
        ];
    }
}
