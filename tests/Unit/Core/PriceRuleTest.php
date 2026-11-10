<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\PriceRule;
use MxAgentTools\Core\Refusal;
use PHPUnit\Framework\TestCase;

class PriceRuleTest extends TestCase
{
    public function testOpsAndRounding(): void
    {
        $this->assertSame(['10350', '0'], (new PriceRule('percent', -10, 0))->apply('11500.00', '0', 'x'));
        $this->assertSame(['10300', '0'], (new PriceRule('percent', -10, 100, 'down'))->apply('11500.00', '0', 'x'));
        $this->assertSame(['10400', '0'], (new PriceRule('percent', -10, 100, 'up'))->apply('11500.00', '0', 'x'));
        $this->assertSame(['10350', '0'], (new PriceRule('percent', -10, 10, 'nearest'))->apply('11500', '0', 'x'));
        $this->assertSame(['1234.57', ''], (new PriceRule('add', 0.567, 0))->apply('1234', '', 'x'));
        $this->assertSame(['990', ''], (new PriceRule('set', 990))->apply('1500', '', 'x'));
        $this->assertSame(['11500.00', '0'], (new PriceRule('add', 0))->apply('11500.00', '0', 'x'), 'без правки — строки как были');
    }

    public function testDiscountAndUndiscount(): void
    {
        $this->assertSame(['9200', '11500'], (new PriceRule('percent', -20, 0, 'nearest', 'discount'))->apply('11500.00', '0', 'x'));
        // скидка поверх скидки — база прежняя old_price
        $this->assertSame(['8050', '11500'], (new PriceRule('percent', -30, 0, 'nearest', 'discount'))->apply('9200', '11500', 'x'));
        $this->assertSame(['11500', '0'], (new PriceRule('', null, 0, 'nearest', 'undiscount'))->apply('9200', '11500.00', 'x'));
        $this->assertSame(['9200', '0'], (new PriceRule('', null, 0, 'nearest', 'undiscount'))->apply('9200', '0', 'x'));
    }

    public function testRefusals(): void
    {
        foreach ([['bad', 1, 0, 'nearest', 'price'], ['percent', 'x', 0, 'nearest', 'price'], ['percent', 1, 5, 'nearest', 'price'],
            ['percent', 1, 0, 'sideways', 'price'], ['percent', 1, 0, 'nearest', 'free']] as $a) {
            try {
                new PriceRule(...$a);
                $this->fail(json_encode($a));
            } catch (Refusal $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(Refusal::class);
        (new PriceRule('percent', -100))->apply('100', '0', 'товар 5');
    }
}
