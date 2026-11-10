<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\Canon;
use MxAgentTools\Core\Diff;
use MxAgentTools\Core\EntityRef;
use PHPUnit\Framework\TestCase;

class CanonDiffTest extends TestCase
{
    public function testCanonIgnoresKeyOrderButNotListOrder(): void
    {
        $this->assertSame(Canon::json(['b' => 1, 'a' => ['y' => 2, 'x' => 3]]), Canon::json(['a' => ['x' => 3, 'y' => 2], 'b' => 1]));
        $this->assertNotSame(Canon::hash([1, 2]), Canon::hash([2, 1]));
        $this->assertSame('{"a":"ж/з"}', Canon::json(['a' => 'ж/з']));
        $this->assertSame(sha1('{"a":1}'), Canon::hash(['a' => 1]));
        $this->assertSame('[]', Canon::json([]));
    }

    public function testCanonSurvivesJsonRoundTrip(): void
    {
        $snapshot = ['resource:5' => ['fields' => ['pagetitle' => 'A', 'id' => '5'], 'tvs' => ['x' => '']], 'tv:1' => null];
        $this->assertTrue(Canon::equal($snapshot, json_decode(json_encode($snapshot), true)));
    }

    public function testDiffLines(): void
    {
        $before = ['resource:5' => ['fields' => ['pagetitle' => 'A', 'published' => '1'], 'tvs' => ['color' => 'red']],
            'tv:3' => ['fields' => ['name' => 'x']]];
        $after = ['resource:5' => ['fields' => ['pagetitle' => 'B', 'published' => '1'], 'tvs' => ['color' => 'red']],
            'tv:3' => null, 'resource:new' => ['fields' => ['pagetitle' => 'C', 'alias' => '']]];
        $this->assertSame([
            'resource:5 fields.pagetitle: «A» → «B»',
            'tv:3: удаляется',
            'resource:new: создаётся',
            'resource:new fields.pagetitle: «C»',
        ], Diff::lines($before, $after));
    }

    public function testDiffListsAndLongValues(): void
    {
        $lines = Diff::lines(['g:1' => ['files' => [['id' => 1], ['id' => 2]], 'cats' => [3, 4]]],
            ['g:1' => ['files' => [['id' => 2]], 'cats' => [4], 'content' => str_repeat('я', 300)]]);
        $this->assertContains('g:1 files[0].id: «1» → «2»', $lines);
        $this->assertContains('g:1 files[1].id: «2» → нет', $lines);
        $this->assertContains('g:1 cats: «[3,4]» → «[4]»', $lines);
        $long = array_values(array_filter($lines, function ($l) {
            return strpos($l, 'content') !== false;
        }))[0];
        $this->assertStringContainsString('(300 симв.)', $long);
    }

    public function testThreeWay(): void
    {
        $lines = Diff::threeWay(['r:1' => ['t' => 'bot']], ['r:1' => ['t' => 'human']], ['r:1' => ['t' => 'old']]);
        $this->assertSame(['r:1 t: у бота «bot» → сейчас «human» → станет «old»'], $lines);
    }

    public function testEmptyListBecomesItemsWithoutNoiseLine(): void
    {
        $lines = Diff::lines(['gallery:9' => ['files' => []]], ['gallery:9' => ['files' => [['id' => 5, 'name' => 'a']]]]);
        $this->assertSame(['gallery:9 files[0].id: нет → «5»', 'gallery:9 files[0].name: нет → «a»'], $lines);
        $this->assertSame(['gallery:9 files[0].id: «5» → нет'],
            Diff::lines(['gallery:9' => ['files' => [['id' => 5]]]], ['gallery:9' => ['files' => []]]));
    }

    public function testUnexpectedOnlyNamedFields(): void
    {
        $predicted = ['gallery:9' => ['files' => [['id' => 0, 'name' => 'a', 'description' => 'x']]], 'resource:5' => ['alias' => 'new']];
        $after = ['gallery:9' => ['files' => [['id' => 77, 'name' => 'a', 'description' => 'x', 'url' => '/u.jpg', 'hash' => 'h']]],
            'resource:5' => ['alias' => 'new-1']];
        $this->assertSame(['resource:5 alias: план «new» → записано «new-1»'], Diff::unexpected($predicted, $after));
        $this->assertSame(['resource:7: не создан'], Diff::unexpected(['resource:7' => ['id' => 0]], ['resource:7' => null]));
    }

    public function testEntityRef(): void
    {
        $ref = EntityRef::fromKey('setting:site_name');
        $this->assertSame('setting', $ref->type());
        $this->assertSame('site_name', $ref->id());
        $this->assertSame(5, EntityRef::fromKey('resource:5')->id());
        $this->expectException(\InvalidArgumentException::class);
        new EntityRef('Bad Type', 1);
    }
}
