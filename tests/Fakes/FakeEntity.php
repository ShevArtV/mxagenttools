<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Entity\EntityInterface;
use MxAgentTools\Entity\FileKeeperInterface;
use MxAgentTools\Entity\SoftDeleteInterface;

/**
 * Объекты в памяти: id => снимок. soft — удаление в «корзину» (deleted=1), keeper — хранит «файлы» снимка.
 * calls — журнал вызовов restore/remove.
 */
class FakeEntity implements EntityInterface, SoftDeleteInterface, FileKeeperInterface
{
    /** @var string */
    private $type;
    /** @var bool */
    private $soft;
    /** @var array */
    public $store = [];
    /** @var array */
    public $calls = [];
    /** @var array|null последний снимок, переданный в restore */
    public $restored;

    public function __construct(string $type, bool $soft = false)
    {
        $this->type = $type;
        $this->soft = $soft;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function snapshot($id): ?array
    {
        return $this->store[$id] ?? null;
    }

    public function restore(array $snapshot): void
    {
        $this->calls[] = ['restore', $snapshot['id']];
        $this->restored = $snapshot;
        unset($snapshot[self::KEPT]);
        $this->store[$snapshot['id']] = $snapshot;
    }

    public function remove($id): void
    {
        $this->calls[] = ['remove', $id];
        if ($this->soft) {
            $this->store[$id]['deleted'] = '1';
        } else {
            unset($this->store[$id]);
        }
    }

    public function isRemoved(array $snapshot): bool
    {
        return $this->soft && ($snapshot['deleted'] ?? '0') === '1';
    }

    public function keep(array $before, ?array $after): array
    {
        $left = (array)($after['files'] ?? []);
        $out = [];
        foreach ((array)($before['files'] ?? []) as $file) {
            if (!in_array($file, $left, true)) {
                $out[$file] = ['name' => $file, 'data' => base64_encode("bytes of {$file}")];
            }
        }
        return $out;
    }
}
