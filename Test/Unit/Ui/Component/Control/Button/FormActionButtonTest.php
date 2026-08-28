<?php
/**
 * FormActionButtonTest.php
 *
 * @package     Commerce_Foundation
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Foundation\Test\Unit\Ui\Component\Control\Button;

use Commerce\Foundation\Ui\Component\Control\Button\FormActionButton;
use PHPUnit\Framework\TestCase;

class FormActionButtonTest extends TestCase
{
    public function testTheDefaultsProduceASaveButton(): void
    {
        $data = (new FormActionButton('Save Thread Colour'))->getButtonData();

        $this->assertSame('Save Thread Colour', $data['label']);
        $this->assertSame('action-secondary', $data['class']);
        $this->assertSame('save', $data['data_attribute']['form-role']);
        $this->assertSame(['button' => ['event' => 'save']], $data['data_attribute']['mage-init']);
        $this->assertSame(10, $data['sort_order']);
    }

    /**
     * Every part of the button is reachable from di.xml, because that is where
     * it is declared.
     */
    public function testEveryPieceIsConfigurable(): void
    {
        $data = (new FormActionButton(
            'Save and Continue',
            'action-primary save',
            'saveAndContinue',
            ['button' => ['event' => 'saveAndContinueEdit']],
            80
        ))->getButtonData();

        $this->assertSame('Save and Continue', $data['label']);
        $this->assertSame('action-primary save', $data['class']);
        $this->assertSame('saveAndContinue', $data['data_attribute']['form-role']);
        $this->assertSame(
            ['button' => ['event' => 'saveAndContinueEdit']],
            $data['data_attribute']['mage-init']
        );
        $this->assertSame(80, $data['sort_order']);
    }

    /**
     * Merged recursively, so di.xml only has to state the difference from the
     * defaults.
     */
    public function testMageInitOverridesAreMergedOverTheDefault(): void
    {
        $data = (new FormActionButton(
            'Delete',
            'action-secondary',
            'delete',
            ['button' => ['target' => '#edit_form']]
        ))->getButtonData();

        $this->assertSame(
            ['button' => ['event' => 'save', 'target' => '#edit_form']],
            $data['data_attribute']['mage-init']
        );
    }

    /**
     * The form binds its own click handling through mage-init; an inline
     * on_click would fire alongside it and submit twice.
     */
    public function testOnClickIsEmptySoTheFormKeepsControlOfSubmission(): void
    {
        $this->assertSame('', (new FormActionButton('Save'))->getButtonData()['on_click']);
    }

    /**
     * One instance can be asked for its data by several containers on the same
     * page; the array-merge must not accumulate across calls.
     */
    public function testTheDataIsStableAcrossCalls(): void
    {
        $button = new FormActionButton('Save', 'action-primary', 'save', ['button' => ['target' => '#f']]);

        $this->assertSame($button->getButtonData(), $button->getButtonData());
    }
}
