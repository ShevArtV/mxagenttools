<?php

namespace MxAgentTools\Core;

/**
 * Ссылка на объект сайта: вид (тип сущности) и id. Ключ «type:id» — имя снимка в before/after и в журнале.
 * Id — число (ресурс, TV, модификация, товар галереи) или строка (ключ настройки).
 */
final class EntityRef
{
    /** @var string */
    private $type;
    /** @var int|string */
    private $id;

    /** @param int|string $id */
    public function __construct(string $type, $id)
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $type)) {
            throw new \InvalidArgumentException("недопустимый вид объекта: {$type}");
        }
        $this->type = $type;
        $this->id = is_string($id) && ctype_digit($id) ? (int)$id : $id;
    }

    public static function fromKey(string $key): self
    {
        $pos = strpos($key, ':');
        if ($pos === false) {
            throw new \InvalidArgumentException("недопустимый ключ объекта: {$key}");
        }
        return new self(substr($key, 0, $pos), substr($key, $pos + 1));
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return int|string */
    public function id()
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->type . ':' . $this->id;
    }
}
