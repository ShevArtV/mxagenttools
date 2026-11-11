<?php

namespace MxAgentTools\Tests\Unit\Core;

use MxAgentTools\Core\BindingGuard;
use MxAgentTools\Core\QueryGuard;
use MxAgentTools\Core\Refusal;
use PHPUnit\Framework\TestCase;

class GuardsTest extends TestCase
{
    /** @dataProvider deniedBindings */
    public function testDeniedBindings(string $value): void
    {
        $this->expectException(Refusal::class);
        BindingGuard::check('tvs.x', $value);
    }

    public function deniedBindings(): array
    {
        return [['@EVAL return 1;'], ['  @eval phpinfo();'], ['@FILE /etc/passwd'], ['@SELECT id FROM modx_users'],
            ['@CHUNK secret'], ['@SNIPPET run'], ['@x @EVAL return 1;'], ['@INHERIT @EVAL 1'], ["\n@Select 1"]];
    }

    /** @dataProvider allowedValues */
    public function testAllowedValues(string $value): void
    {
        BindingGuard::check('tvs.x', $value);
        $this->addToAssertionCount(1);
    }

    public function allowedValues(): array
    {
        return [['@INHERIT'], ['@RESOURCE 5'], ['mail@example.com @EVAL'], ['обычный текст'], [''], ['@ нет команды']];
    }

    public function testNestedValuesChecked(): void
    {
        $this->expectException(Refusal::class);
        BindingGuard::check('input_properties', ['a' => 'ok', 'b' => ['@FILE x']]);
    }

    public function testQueryDeniedClasses(): void
    {
        foreach (['modUser', 'modUserProfile', 'MODUSERGROUPMEMBER', 'modSession', 'modManagerLog', 'modActiveUser',
            'modUserSetting', 'msOrder', 'msOrderAddress', 'msOrderProduct', 'msOrderLog', 'msCustomerProfile', 'msCustomer',
            'mxApiToken', 'mxApiClient', 'mxatJournal', 'mxatUpload'] as $class) {
            try {
                QueryGuard::checkClass($class);
                $this->fail("{$class} должен быть закрыт");
            } catch (Refusal $e) {
                $this->addToAssertionCount(1);
            }
        }
        QueryGuard::checkClass('modResource');
        $this->expectException(Refusal::class);
        QueryGuard::checkClass('modResource; DROP');
    }

    public function testQuerySettingValueOnlyInSelect(): void
    {
        $fields = ['key', 'value', 'xtype', 'namespace'];
        $this->assertSame(['key', 'value'], QueryGuard::select('modSystemSetting', $fields, ['key', 'value']));
        $this->assertSame(['key:LIKE' => '%pass%'], QueryGuard::where('modSystemSetting', $fields, ['key:LIKE' => '%pass%']));
        foreach (['modSystemSetting', 'modContextSetting', 'cgSetting', 'cgContextValue'] as $class) {
            foreach ([['value:LIKE' => 'a%'], ['OR:value' => 'x'], [['value:>' => 'm']]] as $where) {
                try {
                    QueryGuard::where($class, $fields, $where);
                    $this->fail("{$class}: value в where");
                } catch (Refusal $e) {
                    $this->assertStringContainsString('value', $e->getMessage());
                }
            }
            try {
                QueryGuard::sort($class, $fields, ['value' => 'ASC']);
                $this->fail("{$class}: value в sort");
            } catch (Refusal $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(['value' => 'x'], QueryGuard::where('modResource', ['value'], ['value' => 'x']));
    }

    public function testQueryReadsCodeFields(): void
    {
        $fields = ['id', 'name', 'snippet', 'description'];
        $this->assertSame($fields, QueryGuard::select('modChunk', $fields, []));
        $this->assertSame(['snippet'], QueryGuard::select('modSnippet', $fields, ['snippet']));
    }

    public function testQueryWhereSearchesCode(): void
    {
        $this->assertSame(['plugincode:LIKE' => '%OnLoadWebDocument%'],
            QueryGuard::where('modPlugin', ['id', 'plugincode'], ['plugincode:LIKE' => '%OnLoadWebDocument%']));
    }

    /** @dataProvider badWhere */
    public function testQueryWhereRejectsInjection(array $where): void
    {
        $this->expectException(Refusal::class);
        QueryGuard::where('modResource', ['id', 'pagetitle', 'parent'], $where);
    }

    public function badWhere(): array
    {
        return [
            [['1=1) OR (1' => 1]],
            [['id:= 1 OR 1=1 --' => 1]],
            [['unknown' => 1]],
            [['id:REGEXP' => '.*']],
            [['id:IN' => 5]],
            [['id' => [1, 2]]],
            [['id' => ['x' => 1]]],
            [['pagetitle = 1']],
            [[[[[['id' => 1]]]]]],
        ];
    }

    public function testQueryWhereAcceptsXpdoForms(): void
    {
        $where = ['parent' => 5, 'OR:pagetitle:LIKE' => '%a%', 'id:IN' => [1, 2], 'id:>=' => 3,
            [['pagetitle:NOT LIKE' => 'x', 'OR:parent:IS' => null]]];
        $this->assertSame($where, QueryGuard::where('modResource', ['id', 'pagetitle', 'parent'], $where));
    }

    public function testQuerySortAndLimit(): void
    {
        $this->assertSame([['id', 'DESC']], QueryGuard::sort('modResource', ['id'], ['id' => 'desc']));
        $this->assertSame(50, QueryGuard::limit(null));
        $this->assertSame(200, QueryGuard::limit(200));
        foreach ([0, 201] as $bad) {
            try {
                QueryGuard::limit($bad);
                $this->fail("limit {$bad}");
            } catch (Refusal $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(Refusal::class);
        QueryGuard::sort('modResource', ['id'], ['id' => 'RAND()']);
    }

    public function testSiteDeniedClass(): void
    {
        QueryGuard::checkClass('SomeSiteClass');
        QueryGuard::deny(['SomeSiteClass']);
        $this->expectException(Refusal::class);
        QueryGuard::checkClass('somesiteclass');
    }
}
