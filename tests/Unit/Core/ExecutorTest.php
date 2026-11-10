<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\Entities;
use MxAgentTools\Core\Executor;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Entity\FileKeeperInterface;
use MxAgentTools\Tests\Fakes\FakeEntity;
use MxAgentTools\Tests\Fakes\FakeWriteTool;
use MxAgentTools\Tests\Fakes\MemoryJournal;
use PHPUnit\Framework\TestCase;

class ExecutorTest extends TestCase
{
    /** @var FakeEntity */
    private $items;
    /** @var MemoryJournal */
    private $journal;
    /** @var Executor */
    private $executor;
    /** @var FakeWriteTool */
    private $tool;

    protected function setUp(): void
    {
        $this->items = new FakeEntity('item', true);
        $this->items->store[5] = ['id' => 5, 'title' => 'Старое', 'deleted' => '0', 'files' => ['a.jpg', 'b.jpg']];
        $entities = new Entities();
        $entities->add($this->items);
        $this->journal = new MemoryJournal();
        $this->executor = new Executor($entities, $this->journal, 'agent');
        $this->tool = new FakeWriteTool($this->items);
    }

    public function testPlanDoesNotWriteAndHashesBefore(): void
    {
        $plan = $this->executor->plan($this->tool, ['id' => 5, 'set' => ['title' => 'Новое']]);
        $this->assertSame(['item:5'], $plan['targets']);
        $this->assertSame('Старое', $this->items->store[5]['title']);
        $this->assertSame(Canon::hash(['item:5' => $this->items->store[5]]), $plan['hash']);
        $this->assertContains('item:5 title: «Старое» → «Новое»', $plan['diff']);
        $this->assertSame([], $this->journal->entries);
    }

    public function testApplyRefusesStalePlan(): void
    {
        $plan = $this->executor->plan($this->tool, ['id' => 5, 'set' => ['title' => 'Новое']]);
        $this->items->store[5]['title'] = 'Поправили в админке';
        try {
            $this->executor->apply($this->tool, ['id' => 5, 'set' => ['title' => 'Новое']], $plan['hash'], '');
            $this->fail('нужен отказ');
        } catch (Refusal $e) {
            $this->assertSame(Refusal::STALE_PLAN, $e->code());
            $this->assertArrayHasKey('current_hash', $e->details());
        }
        $this->assertSame('Поправили в админке', $this->items->store[5]['title']);
        $this->assertSame([], $this->journal->entries);
    }

    public function testApplyJournalsBeforeWriteAndAfter(): void
    {
        $args = ['id' => 5, 'set' => ['title' => 'Новое']];
        $plan = $this->executor->plan($this->tool, $args);
        $done = $this->executor->apply($this->tool, $args, $plan['hash'], 'Иван: заголовок');
        $this->assertSame(1, $done['journal']);
        [$firstId, $first] = $this->journal->history[0];
        $this->assertSame(1, $firstId);
        $this->assertSame('started', $first['status']);
        $this->assertSame('Старое', $first['before']['item:5']['title']);
        $entry = $this->journal->entries[1];
        $this->assertSame('done', $entry['status']);
        $this->assertSame('Новое', $entry['after']['item:5']['title']);
        $this->assertSame('Иван: заголовок', $entry['note']);
        $this->assertSame('agent', $entry['user']);
        $this->assertSame([], $done['unexpected']);
    }

    public function testFailedApplyKeepsBeforeAndRollsBackWithExpect(): void
    {
        $args = ['id' => 5, 'set' => ['title' => 'Новое'], 'fail' => true];
        $plan = $this->executor->plan($this->tool, $args);
        try {
            $this->executor->apply($this->tool, $args, $plan['hash'], '');
            $this->fail('нужен сбой');
        } catch (\RuntimeException $e) {
            $this->assertSame('процессор упал', $e->getMessage());
        }
        $this->assertSame('failed', $this->journal->entries[1]['status']);
        $rb = $this->executor->rollbackPlan(1);
        $this->assertTrue($rb['diverged'], 'после оборванной записи «после» неизвестно');
        $this->assertTrue($rb['changed']);
        $this->executor->rollbackApply(1, $rb['hash'], '');
        $this->assertSame('Старое', $this->items->store[5]['title']);
    }

    public function testRollbackRestoresAndMarksEntry(): void
    {
        $this->applyTitle('Новое');
        $rb = $this->executor->rollbackPlan(1);
        $this->assertFalse($rb['diverged']);
        $this->assertSame('Старое', $rb['after']['item:5']['title']);
        $done = $this->executor->rollbackApply(1, $rb['hash'], 'откат');
        $this->assertSame('Старое', $this->items->store[5]['title']);
        $this->assertSame(2, $done['journal']);
        $this->assertSame(2, $this->journal->entries[1]['rolled_back']);
        $this->assertSame(1, $this->journal->entries[2]['rollback_of']);
        $again = $this->executor->rollbackPlan(1);
        $this->assertFalse($again['changed']);
    }

    public function testRollbackOfRollbackRedoes(): void
    {
        $this->applyTitle('Новое');
        $rb = $this->executor->rollbackPlan(1);
        $this->executor->rollbackApply(1, $rb['hash'], '');
        $redo = $this->executor->rollbackPlan(2);
        $this->executor->rollbackApply(2, $redo['hash'], '');
        $this->assertSame('Новое', $this->items->store[5]['title']);
    }

    public function testDivergedRollbackNeedsFreshPlanHash(): void
    {
        $this->applyTitle('Новое');
        $this->items->store[5]['title'] = 'Правка человека';
        $rb = $this->executor->rollbackPlan(1);
        $this->assertTrue($rb['diverged']);
        $this->assertContains('item:5 title: у бота «Новое» → сейчас «Правка человека» → станет «Старое»', $rb['divergence']);
        $this->items->store[5]['title'] = 'Ещё правка';
        try {
            $this->executor->rollbackApply(1, $rb['hash'], '');
            $this->fail('нужен отказ: план отката устарел');
        } catch (Refusal $e) {
            $this->assertSame(Refusal::STALE_PLAN, $e->code());
        }
        $fresh = $this->executor->rollbackPlan(1);
        $this->executor->rollbackApply(1, $fresh['hash'], '');
        $this->assertSame('Старое', $this->items->store[5]['title']);
    }

    public function testCreatedObjectIsRemovedSoftlyAndCountsAsAbsent(): void
    {
        $args = ['create' => ['title' => 'Новый', 'deleted' => '0']];
        $plan = $this->executor->plan($this->tool, $args);
        $this->assertSame([], $plan['targets']);
        $this->assertContains('item:new: создаётся', $plan['diff']);
        $done = $this->executor->apply($this->tool, $args, $plan['hash'], '');
        $key = $done['created'][0];
        $id = (int)substr($key, 5);
        $rb = $this->executor->rollbackPlan($done['journal']);
        $this->executor->rollbackApply($done['journal'], $rb['hash'], '');
        $this->assertSame('1', $this->items->store[$id]['deleted'], 'созданное — в корзину, не стёрто');
        $this->assertFalse($this->executor->rollbackPlan($done['journal'])['changed'], 'в корзине = «объекта нет»');
    }

    public function testDeletedObjectRestoredWithKeptFiles(): void
    {
        $args = ['id' => 5, 'delete' => true];
        $plan = $this->executor->plan($this->tool, $args);
        $this->assertContains('item:5: удаляется', $plan['diff']);
        $done = $this->executor->apply($this->tool, $args, $plan['hash'], '');
        $kept = $this->journal->entries[$done['journal']]['kept']['item:5'];
        $this->assertSame(['a.jpg', 'b.jpg'], array_keys($kept));
        $rb = $this->executor->rollbackPlan($done['journal']);
        $this->executor->rollbackApply($done['journal'], $rb['hash'], '');
        $this->assertSame('Старое', $this->items->store[5]['title']);
        $this->assertSame(base64_encode('bytes of a.jpg'), $this->items->restored[FileKeeperInterface::KEPT]['a.jpg']['data']);
        $this->assertArrayNotHasKey(FileKeeperInterface::KEPT, $this->items->store[5]);
    }

    public function testRollbackUnknownJournalIsRefusal(): void
    {
        $this->expectException(Refusal::class);
        $this->executor->rollbackPlan(99);
    }

    private function applyTitle(string $title): void
    {
        $args = ['id' => 5, 'set' => ['title' => $title]];
        $plan = $this->executor->plan($this->tool, $args);
        $this->executor->apply($this->tool, $args, $plan['hash'], '');
    }
}
