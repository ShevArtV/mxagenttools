<?php

namespace MxAgentTools\Tests\Unit\Tool;

use MxAgentTools\Core\Entities;
use MxAgentTools\Core\Executor;
use MxAgentTools\Core\Refusal;
use MxAgentTools\Entity\ProductColor;
use MxAgentTools\Tests\Fakes\FakeEntity;
use MxAgentTools\Tests\Fakes\MemoryJournal;
use MxAgentTools\Tests\Fakes\MemoryProductColors;
use MxAgentTools\Tool\Color\ProductColorGet;
use MxAgentTools\Tool\Color\ProductColorSet;
use PHPUnit\Framework\TestCase;

class ProductColorTest extends TestCase
{
    private function setUpAll(): array
    {
        $colors = new MemoryProductColors();
        $colors->opts = [1054 => ['Белый'], 1055 => ['Белый', 'Чёрный'], 1056 => ['Зелёный']];
        $colors->put(254, 'Белый', ''); // дубль с пустым pattern — не донор
        $colors->put(796, 'Белый', 'assets/images/product_colors/id-eng-garden-1.jpg');
        $entities = new Entities();
        $entities->add(new ProductColor($colors));
        $entities->add(new FakeEntity('resource'));
        return [$colors, new Executor($entities, new MemoryJournal(), 'agent'), new ProductColorSet($colors)];
    }

    public function testDonorSkipsEmptyPatternAndRollbackRemovesCreatedRow(): void
    {
        [$colors, $executor, $tool] = $this->setUpAll();
        $args = ['product' => 1054];
        $plan = $executor->plan($tool, $args);
        $this->assertNull($plan['before']['product_color:1054:Белый']);
        $this->assertSame('assets/images/product_colors/id-eng-garden-1.jpg', $plan['after']['product_color:1054:Белый']['pattern']);
        $out = $executor->apply($tool, $args, $plan['hash'], '');
        $this->assertSame('assets/images/product_colors/id-eng-garden-1.jpg', $colors->row(1054, 'Белый')['pattern']);
        $this->assertSame(['mgr/color/create'], $colors->calls);

        $rb = $executor->rollbackPlan($out['journal']);
        $executor->rollbackApply($out['journal'], $rb['hash'], '');
        $this->assertNull($colors->row(1054, 'Белый'));
    }

    public function testRepeatedApplyUpdatesOneRow(): void
    {
        [$colors, $executor, $tool] = $this->setUpAll();
        $args = ['product' => 1054];
        $executor->apply($tool, $args, $executor->plan($tool, $args)['hash'], '');
        $again = $executor->plan($tool, $args);
        $executor->apply($tool, $args, $again['hash'], '');
        $this->assertSame(['mgr/color/create', 'mgr/color/update'], $colors->calls);
        $this->assertCount(1, $colors->rows(1054));
    }

    public function testUpdateKeepsOwnImagesAndRollbackRestoresRow(): void
    {
        [$colors, $executor, $tool] = $this->setUpAll();
        $colors->put(1054, 'Белый', 'old.jpg', ['image' => '77']);
        $args = ['product' => 1054, 'donor' => 796];
        $plan = $executor->plan($tool, $args);
        $out = $executor->apply($tool, $args, $plan['hash'], '');
        $this->assertSame('77', $colors->row(1054, 'Белый')['image']);
        $this->assertSame('assets/images/product_colors/id-eng-garden-1.jpg', $colors->row(1054, 'Белый')['pattern']);
        $rb = $executor->rollbackPlan($out['journal']);
        $executor->rollbackApply($out['journal'], $rb['hash'], '');
        $this->assertSame('old.jpg', $colors->row(1054, 'Белый')['pattern']);
    }

    public function testRefusals(): void
    {
        [$colors, $executor, $tool] = $this->setUpAll();
        foreach ([['product' => 1055], // два значения — нужен value
            ['product' => 1055, 'value' => 'Красный'], // нового цвета не заводим
            ['product' => 1054, 'donor' => 254], // донор с пустым pattern
            ['product' => 1056], // донора цвета нет
            ['product' => 1054, 'donor' => 1054], ['product' => 50000]] as $args) {
            try {
                $executor->plan($tool, $args);
                $this->fail('нужен отказ: ' . json_encode($args, JSON_UNESCAPED_UNICODE));
            } catch (Refusal $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testStaleRowIsReplaced(): void
    {
        [$colors, $executor, $tool] = $this->setUpAll();
        $colors->put(1054, 'Бежевый', 'x.jpg'); // в опции color уже нет
        $args = ['product' => 1054];
        $plan = $executor->plan($tool, $args);
        $this->assertNull($plan['after']['product_color:1054:Бежевый']);
        $out = $executor->apply($tool, $args, $plan['hash'], '');
        $this->assertNull($colors->row(1054, 'Бежевый'));
        $rb = $executor->rollbackPlan($out['journal']);
        $executor->rollbackApply($out['journal'], $rb['hash'], '');
        $this->assertSame('x.jpg', $colors->row(1054, 'Бежевый')['pattern']);
        $this->assertNull($colors->row(1054, 'Белый'));
    }

    public function testFailedCreateLeavesNoBlankRow(): void
    {
        [$colors, $executor, $tool] = $this->setUpAll();
        $colors->createFails['Белый'] = 'процессор упал';
        $args = ['product' => 1054];
        try {
            $executor->apply($tool, $args, $executor->plan($tool, $args)['hash'], '');
            $this->fail('нужна ошибка');
        } catch (\RuntimeException $e) {
            $this->assertSame('процессор упал', $e->getMessage());
        }
        $this->assertNull($colors->row(1054, 'Белый'));
    }

    public function testGetShowsRowsAndDonors(): void
    {
        [$colors] = $this->setUpAll();
        $colors->put(1054, 'Белый', '');
        $out = (new ProductColorGet($colors))->read(['product' => 1054]);
        $this->assertTrue($out['colors'][0]['exists']);
        $this->assertFalse($out['colors'][0]['has_pattern']);
        $this->assertSame(796, $out['donors']['Белый'][0]['product']);
        $none = (new ProductColorGet($colors))->read(['product' => 1056]);
        $this->assertFalse($none['colors'][0]['exists']);
    }
}
