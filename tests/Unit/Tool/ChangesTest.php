<?php

namespace MxAgentTools\Tests\Unit\Tool;

use MxAgentTools\Core\Refusal;
use MxAgentTools\Core\Values;
use MxAgentTools\Tool\Gallery\Position;
use MxAgentTools\Tool\Modification\ModificationChange;
use MxAgentTools\Tool\Resource\ResourceChange;
use MxAgentTools\Tool\Setting\SettingValue;
use MxAgentTools\Tool\Tv\TvChange;
use PHPUnit\Framework\TestCase;

class ChangesTest extends TestCase
{
    private function product(): array
    {
        return ['id' => 5, 'class_key' => 'msProduct',
            'fields' => ['pagetitle' => 'A', 'template' => '3', 'published' => '1', 'deleted' => '0'],
            'tvs' => ['color' => 'red'], 'product' => ['price' => '100', 'tags' => ['x']],
            'options' => ['size' => ['L', 'M']], 'categories' => [7]];
    }

    public function testResourceChangeMerges(): void
    {
        $after = ResourceChange::merge($this->product(), ['fields' => ['pagetitle' => 'B', 'published' => false],
            'tvs' => ['color' => ['red', 'blue']], 'product' => ['price' => 120, 'tags' => 'y'],
            'options' => ['size' => ['S', 'M', 'S'], 'material' => 'шёлк'], 'categories' => [9, 7, 9]]);
        $this->assertSame('B', $after['fields']['pagetitle']);
        $this->assertSame('0', $after['fields']['published']);
        $this->assertSame('red||blue', $after['tvs']['color']);
        $this->assertSame(['price' => '120', 'tags' => ['y']], $after['product']);
        $this->assertSame(['material' => ['шёлк'], 'size' => ['M', 'S']], $after['options']);
        $this->assertSame([7, 9], $after['categories']);
        $this->assertSame([], ResourceChange::merge($this->product(), ['options' => ['size' => []]])['options']);
    }

    /** @dataProvider badResourceChanges */
    public function testResourceChangeRefusals(array $args): void
    {
        $this->expectException(Refusal::class);
        $before = $this->product();
        if (isset($args['_plain'])) {
            unset($before['product'], $before['options'], $before['categories'], $args['_plain']);
        }
        ResourceChange::merge($before, $args);
    }

    public function badResourceChanges(): array
    {
        return [
            'нечего менять' => [[]],
            'корзина' => [['fields' => ['deleted' => 1]]],
            'id' => [['fields' => ['id' => 6]]],
            'нет поля' => [['fields' => ['nope' => 1]]],
            'TV не шаблона' => [['tvs' => ['other' => 'x']]],
            '@EVAL в TV' => [['tvs' => ['color' => '@EVAL return 1;']]],
            '@SELECT списком' => [['tvs' => ['color' => ['@SELECT 1']]]],
            'не товар' => [['product' => ['price' => 1], '_plain' => true]],
            'нет поля товара' => [['product' => ['nope' => 1]]],
            'поле объектом' => [['fields' => ['pagetitle' => ['a' => 1]]]],
        ];
    }

    public function testTvChange(): void
    {
        $new = TvChange::merge(null, ['fields' => ['name' => 'color', 'caption' => 'Цвет', 'elements' => 'red||blue'],
            'templates' => [5, 3, 5], 'input_properties' => ['allowBlank' => true]]);
        $this->assertSame('text', $new['fields']['type']);
        $this->assertSame([['template' => 3, 'rank' => 0], ['template' => 5, 'rank' => 0]], $new['templates']);
        $this->assertSame(['allowBlank' => '1'], $new['input_properties']);
        $kept = TvChange::merge($new + ['templates' => []], ['templates' => [3]]);
        $this->assertSame([['template' => 3, 'rank' => 0]], $kept['templates']);
        $ranked = TvChange::merge(['templates' => [['template' => 3, 'rank' => 4]]] + $new, ['templates' => [3, 8]]);
        $this->assertSame([['template' => 3, 'rank' => 4], ['template' => 8, 'rank' => 0]], $ranked['templates']);
    }

    /** @dataProvider badTvChanges */
    public function testTvChangeRefusals(array $args): void
    {
        $this->expectException(Refusal::class);
        TvChange::merge(null, $args);
    }

    public function badTvChanges(): array
    {
        return [
            'нечего' => [[]],
            'без имени' => [['fields' => ['caption' => 'x']]],
            'static_file' => [['fields' => ['name' => 'x', 'static_file' => '/etc/passwd']]],
            '@SELECT в elements' => [['fields' => ['name' => 'x', 'elements' => '@SELECT pagetitle FROM modx_site_content']]],
            '@EVAL в default_text' => [['fields' => ['name' => 'x', 'default_text' => '@EVAL return 1;']]],
            '@CHUNK в параметрах' => [['fields' => ['name' => 'x'], 'output_properties' => ['tpl' => '@CHUNK x']]],
            'имя с пробелом' => [['fields' => ['name' => 'a b']]],
        ];
    }

    public function testModificationChange(): void
    {
        $before = ['id' => 7, 'rid' => 5, 'fields' => ['price' => '100', 'active' => '1', 'image' => '11'],
            'options' => ['color' => ['red']], 'images' => [[11, 0], [12, 1]]];
        $after = ModificationChange::merge($before, ['fields' => ['price' => 90.5], 'options' => ['size' => 'M', 'color' => []],
            'images' => [12, 11, 12]]);
        $this->assertSame('90.5', $after['fields']['price']);
        $this->assertSame(['size' => ['M']], $after['options']);
        $this->assertSame([[12, 0], [11, 1]], $after['images']);
        $this->assertSame('12', $after['fields']['image']);
        $this->assertSame('0', ModificationChange::merge($before, ['images' => []])['fields']['image']);
        $this->expectException(Refusal::class);
        ModificationChange::merge($before, ['fields' => ['image' => 12]]);
    }

    public function testPosition(): void
    {
        $this->assertSame([0, 1, 2], Position::insert([1, 2], 0, 'first'));
        $this->assertSame([1, 2, 0], Position::insert([1, 2], 0, 'last'));
        $this->assertSame([1, 0, 2], Position::insert([1, 2], 0, '2'));
        $this->assertSame([1, 2, 0], Position::insert([1, 2], 0, 99));
        $this->assertSame([3, 1, 2], Position::permutation([1, 2, 3], ['3', 1, 2]));
        try {
            Position::insert([1], 0, 'middle');
            $this->fail('позиция');
        } catch (Refusal $e) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(Refusal::class);
        Position::permutation([1, 2, 3], [1, 2, 2]);
    }

    public function testSettingValueAndValues(): void
    {
        $this->assertSame('x', SettingValue::from(['value' => 'x']));
        $this->assertSame('', SettingValue::from(['clear' => true]));
        $this->assertSame(5, Values::id(['id' => '5'], 'id'));
        $this->assertSame(['a', '1'], Values::strList(['a', 1]));
        foreach ([[[]], [['value' => 'x', 'clear' => true]]] as [$args]) {
            try {
                SettingValue::from($args);
                $this->fail('нужен отказ');
            } catch (Refusal $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(Refusal::class);
        Values::id(['id' => '5; DROP'], 'id');
    }
}
