<?php

namespace MxAgentTools\Tests\Unit\Tool;

use MxAgentTools\Core\Entities;
use MxAgentTools\Core\Executor;
use MxAgentTools\Core\LinkPairs;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Entity\ProductLink;
use MxAgentTools\Modx\Selections;
use MxAgentTools\Tests\Fakes\MemoryProductLinks;
use MxAgentTools\Tool\Batch\BatchIds;
use MxAgentTools\Tool\Link\ProductLinkCreate;
use MxAgentTools\Tool\Link\ProductLinkDelete;
use MxAgentTools\Tool\Link\ProductLinkSet;
use MxAgentTools\Tests\Fakes\MemoryJournal;
use PHPUnit\Framework\TestCase;

class ProductLinksTest extends TestCase
{
    public function testPairBothSides(): void
    {
        $this->assertSame([[1, 2], [2, 1]], LinkPairs::pair('many_to_many', 1, 2, true));
        $this->assertSame([[1, 2]], LinkPairs::pair('one_to_many', 1, 2, false));
        $this->assertSame([[2, 1], [1, 2]], LinkPairs::pair('many_to_one', 1, 2, true));
    }

    public function testGroupIsEveryWithEvery(): void
    {
        $rows = LinkPairs::group('many_to_many', [10, 11, 12, 11]);
        $this->assertCount(6, $rows);
        $this->assertContains([12, 10], $rows);
    }

    public function testGroupRefusals(): void
    {
        $this->expectException(Refusal::class);
        LinkPairs::group('one_to_many', [1, 2]);
    }

    public function testGroupNeedsTwo(): void
    {
        $this->expectException(Refusal::class);
        LinkPairs::group('many_to_many', [1]);
    }

    public function testSelfLinkRefused(): void
    {
        $this->expectException(Refusal::class);
        LinkPairs::pair('many_to_many', 5, 5, true);
    }

    public function testIdRoundTrip(): void
    {
        $this->assertSame([1, 1054, 7], LinkPairs::parse(LinkPairs::id(1, 1054, 7)));
    }

    private function setUpExecutor(): array
    {
        $links = new MemoryProductLinks();
        $entities = new Entities();
        $entities->add(new ProductLink($links));
        $executor = new Executor($entities, new MemoryJournal(), 'agent');
        $selections = (new \ReflectionClass(Selections::class))->newInstanceWithoutConstructor();
        return [$links, $executor, new BatchIds($selections, 500)];
    }

    /** Набор: apply создаёт недостающее, повтор не плодит дублей, откат убирает только записанное */
    public function testSetPlanApplyRollback(): void
    {
        [$links, $executor, $ids] = $this->setUpExecutor();
        $links->rows[] = '1:10:11'; // одна строка уже есть
        $tool = new ProductLinkSet($links, $ids);
        $args = ['ids' => [10, 11, 12]];

        $plan = $executor->plan($tool, $args);
        $this->assertSame(6, $plan['total']);
        $this->assertSame(5, $plan['changed']);
        $this->assertSame(1, $plan['unchanged']);
        $out = $executor->apply($tool, $args, $plan['hash'], '');
        $this->assertCount(6, $links->rows);

        $again = $executor->plan($tool, $args);
        $this->assertSame(0, $again['changed']);
        $executor->apply($tool, $args, $again['hash'], '');
        $this->assertCount(6, $links->rows);

        $rb = $executor->rollbackPlan($out['journal']);
        $executor->rollbackApply($out['journal'], $rb['hash'], '');
        $this->assertSame(['1:10:11'], $links->rows);
    }

    public function testCreateRefusesIntoExistingGroupUnlessSingle(): void
    {
        [$links, $executor] = $this->setUpExecutor();
        $links->rows = ['1:10:11', '1:11:10'];
        $tool = new ProductLinkCreate($links);
        try {
            $executor->plan($tool, ['product' => 12, 'related' => 10]);
            $this->fail('нужен отказ');
        } catch (Refusal $e) {
            $this->assertStringContainsString('product.link.set', $e->getMessage());
        }
        $plan = $executor->plan($tool, ['product' => 12, 'related' => 10, 'single' => true]);
        $this->assertCount(2, $plan['targets']);
    }

    public function testDeleteTouchesOnlyThePair(): void
    {
        [$links, $executor] = $this->setUpExecutor();
        $links->rows = ['1:10:11', '1:11:10', '1:10:12', '1:12:10'];
        $tool = new ProductLinkDelete($links);
        $plan = $executor->plan($tool, ['product' => 10, 'related' => 11]);
        $executor->apply($tool, ['product' => 10, 'related' => 11], $plan['hash'], '');
        $this->assertSame(['1:10:12', '1:12:10'], $links->rows);
        $this->expectException(Refusal::class);
        $executor->plan($tool, ['product' => 10, 'related' => 11]);
    }
}
