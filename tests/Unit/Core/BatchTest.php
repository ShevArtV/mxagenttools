<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\BatchRollback;
use MxAgentTools\Core\Entities;
use MxAgentTools\Core\Executor;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Tests\Fakes\FakeBatchTool;
use MxAgentTools\Tests\Fakes\FakeEntity;
use MxAgentTools\Tests\Fakes\MemoryJournal;
use PHPUnit\Framework\TestCase;

class BatchTest extends TestCase
{
    /** @var FakeEntity */
    private $items;
    /** @var MemoryJournal */
    private $journal;
    /** @var Executor */
    private $executor;
    /** @var FakeBatchTool */
    private $tool;

    protected function setUp(): void
    {
        $this->items = new FakeEntity('item');
        for ($i = 1; $i <= 30; $i++) {
            $this->items->store[$i] = ['id' => $i, 'price' => (string)(100 * $i), 'title' => "T{$i}"];
        }
        $entities = new Entities();
        $entities->add($this->items);
        $this->journal = new MemoryJournal();
        $this->executor = new Executor($entities, $this->journal, 'agent');
        $this->tool = new FakeBatchTool($this->items);
    }

    public function testPlanSummaryFirst20AndHashOverAll(): void
    {
        $args = ['ids' => range(1, 25), 'set' => ['price' => '100']];
        $plan = $this->executor->plan($this->tool, $args);
        $this->assertSame(25, $plan['total']);
        $this->assertSame(24, $plan['changed']); // у первого уже 100
        $this->assertSame(1, $plan['unchanged']);
        $this->assertSame(['item price' => 24], $plan['summary']);
        $this->assertCount(20, $plan['diff']);
        $this->assertSame(4, $plan['more']);
        $this->assertArrayNotHasKey('before', $plan);
        $this->items->store[25]['title'] = 'правили';
        $this->expectException(Refusal::class);
        $this->executor->apply($this->tool, $args, $plan['hash'], '');
    }

    public function testSmallBatchKeepsSnapshots(): void
    {
        $plan = $this->executor->plan($this->tool, ['ids' => [2, 3], 'set' => ['price' => '1']]);
        $this->assertArrayHasKey('before', $plan);
        $this->assertSame(['item price' => 2], $plan['summary']);
    }

    public function testPagesOfOneBatchRollBackTogetherNewestFirst(): void
    {
        foreach ([[1, 2, 3], [4, 5, 6], [7, 8]] as $page) {
            $args = ['ids' => $page, 'set' => ['price' => '1']];
            $plan = $this->executor->plan($this->tool, $args);
            $res = $this->executor->apply($this->tool, $args, $plan['hash'], 'note', 'op-1');
            $this->assertSame('op-1', $res['batch']);
        }
        $other = ['ids' => [20], 'set' => ['price' => '2']];
        $this->executor->apply($this->tool, $other, $this->executor->plan($this->tool, $other)['hash'], '', 'op-2');
        $this->assertSame([3, 2, 1], $this->journal->batchEntries('op-1'));
        $this->assertCount(3, $this->journal->recent(10, 0, 'op-1'));

        $rb = new BatchRollback($this->executor, $this->journal);
        $plan = $rb->plan('op-1');
        $this->assertSame(8, $plan['total']);
        $this->assertSame(['item price' => 8], $plan['summary']);
        $this->assertSame([3, 2, 1], array_column($plan['entries'], 'journal'));
        $done = $rb->apply('op-1', $plan['hash'], 'откат');
        $this->assertTrue($done['complete']);
        for ($i = 1; $i <= 8; $i++) {
            $this->assertSame((string)(100 * $i), $this->items->store[$i]['price']);
        }
        $this->assertSame('1', $this->items->store[20]['price'] === '2' ? '1' : 'x'); // чужая операция не тронута
        $this->expectException(Refusal::class);
        $rb->plan('op-1'); // уже откачена
    }

    public function testFailureMidPageJournalHoldsWrittenRollbackRestores(): void
    {
        $args = ['ids' => [1, 2, 3, 4], 'set' => ['price' => '7'], 'fail_at' => 3];
        $plan = $this->executor->plan($this->tool, $args);
        try {
            $this->executor->apply($this->tool, $args, $plan['hash'], '', 'op-f');
            $this->fail('нужен сбой');
        } catch (\RuntimeException $e) {
            $this->assertSame('процессор упал', $e->getMessage());
        }
        $this->assertSame('7', $this->items->store[2]['price']);
        $this->assertSame('failed', $this->journal->entries[1]['status']);
        $rb = new BatchRollback($this->executor, $this->journal);
        $plan = $rb->plan('op-f');
        $this->assertTrue($plan['diverged']);
        $rb->apply('op-f', $plan['hash'], '');
        $this->assertSame(['100', '200', '300', '400'], array_map(function ($i) {
            return $this->items->store[$i]['price'];
        }, [1, 2, 3, 4]));
    }

    public function testStaleBatchRollbackRefused(): void
    {
        $args = ['ids' => [1, 2], 'set' => ['price' => '5']];
        $this->executor->apply($this->tool, $args, $this->executor->plan($this->tool, $args)['hash'], '', 'op-s');
        $rb = new BatchRollback($this->executor, $this->journal);
        $plan = $rb->plan('op-s');
        $this->items->store[2]['title'] = 'руками';
        try {
            $rb->apply('op-s', $plan['hash'], '');
            $this->fail('нужен stale_plan');
        } catch (Refusal $e) {
            $this->assertSame(Refusal::STALE_PLAN, $e->code());
        }
        $this->assertSame('5', $this->items->store[1]['price']);
    }
}
