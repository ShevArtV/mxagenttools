<?php

namespace MxAgentTools\Tests\Unit\Api;

use MxAgentTools\Api\Passport;
use MxAgentTools\Tool\ToolInterface;
use PHPUnit\Framework\TestCase;

/** Паспорта всех инструментов — то, что агент увидит в /meta/openapi */
class PassportTest extends TestCase
{
    const EXPECTED = ['clientconfig.get', 'clientconfig.update', 'context.list', 'element.get', 'element.list', 'file.list',
        'file.read', 'gallery.get', 'gallery.remove', 'gallery.sort', 'gallery.upload', 'modification.create',
        'modification.delete', 'modification.images', 'modification.list', 'modification.update', 'product.categories',
        'product.prices', 'product.select', 'query', 'resource.batch', 'resource.children', 'resource.create',
        'resource.delete', 'resource.find', 'resource.get', 'resource.select', 'resource.undelete', 'resource.update',
        'setting.get', 'setting.update', 'template.list', 'tv.create', 'tv.delete', 'tv.get', 'tv.list', 'tv.update'];

    /** @return ToolInterface[] все конкретные инструменты пакета (без конструктора — паспорт от зависимостей не зависит) */
    public static function tools(): array
    {
        $root = dirname(__DIR__, 3) . '/core/components/mxagenttools/src/';
        $out = [];
        foreach (glob($root . 'Tool/*/*.php') as $file) {
            $class = 'MxAgentTools\\' . str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($root)));
            $ref = new \ReflectionClass($class);
            if ($ref->isInstantiable() && $ref->implementsInterface(ToolInterface::class)) {
                $out[] = $ref->newInstanceWithoutConstructor();
            }
        }
        return $out;
    }

    public function testToolSetMatchesArchitecture(): void
    {
        $names = array_map(function (ToolInterface $t) {
            return $t->name();
        }, self::tools());
        sort($names);
        $this->assertSame(self::EXPECTED, $names);
    }

    public function testEveryPassportIsComplete(): void
    {
        $routes = [];
        foreach (self::tools() as $tool) {
            $spec = Passport::forTool($tool);
            $name = $tool->name();
            $this->assertSame('agent.' . $name, $spec['id']);
            $this->assertNotSame('', $spec['title'], $name);
            $this->assertGreaterThan(60, mb_strlen($spec['description']), "{$name}: описание для агента");
            $this->assertMatchesRegularExpression('#^/agent/[a-z{}:\\\\d+/_]+$#', $spec['path'], $name);
            $route = $spec['methods'][0] . ' ' . preg_replace('/\{([a-z_]+)[^}]*\}/', '{$1}', $spec['path']);
            $this->assertArrayNotHasKey($route, $routes, "{$name}: маршрут занят");
            $routes[$route] = $name;
            $write = $tool->kind() === ToolInterface::WRITE;
            $this->assertSame($write ? 'POST' : 'GET', $spec['methods'][0]);
            $this->assertSame($write ? 'agent.write' : 'agent.read', $spec['scope']);
            $this->assertSame($write ? 'mxapi_agent_write' : 'mxapi_agent_read', $spec['permission']);
            $this->assertSame('mgr', $spec['modx_context']);
            $params = array_column($spec['parameters'], null, 'name');
            $this->assertCount(count($spec['parameters']), $params, "{$name}: имя параметра повторяется (mode/expect/note — служебные записи)");
            preg_match_all('/\{([a-z_]+)/', $spec['path'], $m);
            foreach ($m[1] as $pathParam) {
                $this->assertArrayHasKey($pathParam, $params, "{$name}: параметр пути {$pathParam} не объявлен");
                $this->assertSame('path', $params[$pathParam]['in']);
            }
            foreach ($spec['parameters'] as $p) {
                $this->assertContains($p['type'], ['string', 'integer', 'number', 'boolean', 'array', 'object'], "{$name}.{$p['name']}");
                $this->assertNotSame('', $p['description'], "{$name}.{$p['name']}: описание параметра");
                $this->assertContains($p['in'], ['path', 'query', 'body']);
            }
            if ($write) {
                $this->assertSame(['plan', 'apply'], $params['mode']['enum']);
                $this->assertArrayHasKey('expect', $params);
                $this->assertArrayHasKey('note', $params);
                $this->assertStringContainsString('stale_plan', $spec['description']);
            }
        }
    }

    /** Если рядом лежит mxApi — паспорта принимает его EndpointMetadata */
    public function testMxApiAcceptsPassports(): void
    {
        if (!class_exists('MxApi\\Core\\Endpoint\\EndpointMetadata')) {
            $this->markTestSkipped('mxApi не найден (MXAPI_AUTOLOAD или ../mxapi2)');
        }
        foreach (self::tools() as $tool) {
            $meta = new \MxApi\Core\Endpoint\EndpointMetadata(Passport::forTool($tool));
            $this->assertSame('agent.' . $tool->name(), $meta->getId());
            $this->assertCount(count(Passport::forTool($tool)['parameters']), $meta->getParameters());
        }
    }

    /** Эндпоинты пакета целиком (инструменты, журнал, загрузка) — через getMetadata() mxApi */
    public function testEndpointsMetadata(): void
    {
        if (!class_exists('MxApi\\Core\\Endpoint\\AbstractEndpoint')) {
            $this->markTestSkipped('mxApi не найден');
        }
        $toolbox = (new \ReflectionClass(\MxAgentTools\Modx\Toolbox::class))->newInstanceWithoutConstructor();
        $endpoints = [];
        foreach (self::tools() as $tool) {
            $endpoints[] = new \MxAgentTools\Api\Endpoint\ToolEndpoint($tool, $toolbox);
        }
        foreach (['JournalListEndpoint', 'JournalGetEndpoint', 'JournalRollbackEndpoint', 'JournalBatchRollbackEndpoint',
            'UploadEndpoint'] as $class) {
            $class = 'MxAgentTools\\Api\\Endpoint\\' . $class;
            $endpoints[] = new $class($toolbox);
        }
        $ids = [];
        foreach ($endpoints as $endpoint) {
            $meta = $endpoint->getMetadata();
            $ids[] = $meta->getId();
            $this->assertSame('mxagenttools', $meta->toArray()['provider'] ?? 'mxagenttools');
        }
        $this->assertCount(42, array_unique($ids));
        $this->assertContains('agent.journal.batch.rollback', $ids);
        $this->assertContains('agent.journal.rollback', $ids);
    }
}
