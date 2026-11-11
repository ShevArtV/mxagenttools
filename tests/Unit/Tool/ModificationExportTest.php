<?php

namespace MxAgentTools\Tests\Unit\Tool;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Tests\Fakes\FakeExternalIds;
use MxAgentTools\Tests\Fakes\FakeModifications;
use MxAgentTools\Tool\Modification\ModificationExport;
use PHPUnit\Framework\TestCase;

class ModificationExportTest extends TestCase
{
    private function mods(): FakeModifications
    {
        return new FakeModifications([
            774 => ['title' => 'Пижама', 'mods' => [
                1612 => ['name' => '', 'options' => ['size' => ['M'], 'color' => ['синий']]],
                1613 => ['name' => 'Пижама синяя L', 'options' => ['size' => ['L']]],
            ]],
            286 => ['title' => 'Маска', 'mods' => [900 => ['name' => '  ', 'options' => []]]],
        ]);
    }

    public function testDefaultCodesAndNames(): void
    {
        $ext = new FakeExternalIds();
        $out = (new ModificationExport($this->mods(), $ext))->read(['products' => '286, 774']);
        $this->assertSame([
            ['product' => 286, 'modification' => 900, 'name' => 'Маска', 'external_id' => 'mod-286-900', 'source' => 'temp'],
            ['product' => 774, 'modification' => 1612, 'name' => 'Пижама — синий, M', 'external_id' => 'mod-774-1612',
                'source' => 'temp'],
            ['product' => 774, 'modification' => 1613, 'name' => 'Пижама синяя L', 'external_id' => 'mod-774-1613',
                'source' => 'temp'],
        ], $out['rows']);
        $this->assertSame([], $out['missing']);
        $this->assertSame([900 => 286, 1612 => 774, 1613 => 774], $ext->asked);
    }

    public function testPluginReplacesCodes(): void
    {
        $ext = new FakeExternalIds([1612 => 'a1b2-uuid', 1613 => '', 900 => 5, 777 => 'чужой']);
        $rows = (new ModificationExport($this->mods(), $ext))->read(['products' => [774, 286]])['rows'];
        $this->assertSame(['a1b2-uuid', '1c'], [$rows[0]['external_id'], $rows[0]['source']]);
        $this->assertSame(['mod-774-1613', 'temp'], [$rows[1]['external_id'], $rows[1]['source']]);
        $this->assertSame(['mod-286-900', 'temp'], [$rows[2]['external_id'], $rows[2]['source']]);
        $this->assertCount(3, $rows);
    }

    public function testMissingAndNoEventWithoutModifications(): void
    {
        $ext = new FakeExternalIds();
        $out = (new ModificationExport($this->mods(), $ext))->read(['products' => [5, '6', 5]]);
        $this->assertSame(['rows' => [], 'missing' => [5, 6]], $out);
        $this->assertNull($ext->asked);
    }

    public function testTitle(): void
    {
        $this->assertSame('A — x, y, 1', ModificationExport::title('A', ['size' => ['1'], 'color' => ['x', 'y']]));
        $this->assertSame('A', ModificationExport::title('A', []));
    }

    public function testLimit(): void
    {
        $this->assertCount(200, ModificationExport::productIds(range(1, 200)));
        $this->expectException(Refusal::class);
        ModificationExport::productIds(range(1, 201));
    }

    /** @dataProvider badProducts */
    public function testBadProducts($value): void
    {
        $this->expectException(Refusal::class);
        ModificationExport::productIds($value);
    }

    public function badProducts(): array
    {
        return ['нет' => [null], 'пусто' => [''], 'пустой список' => [[]], 'не id' => ['774,abc'], 'ноль' => [[0]],
            'вложенный' => [[[1]]]];
    }
}
