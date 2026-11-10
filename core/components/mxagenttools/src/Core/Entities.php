<?php

namespace MxAgentTools\Core;

use MxAgentTools\Entity\EntityInterface;

/** Вид объекта → сущность; снимки набора ссылок */
class Entities
{
    /** @var EntityInterface[] */
    private $entities = [];

    public function add(EntityInterface $entity): void
    {
        $this->entities[$entity->type()] = $entity;
    }

    public function get(string $type): EntityInterface
    {
        if (!isset($this->entities[$type])) {
            throw new Refusal("вид объекта «{$type}» на этом сайте не поддерживается (пакет не установлен)");
        }
        return $this->entities[$type];
    }

    /**
     * @param EntityRef[] $refs
     * @return array «type:id» => снимок|null
     */
    public function snapshots(array $refs): array
    {
        $out = [];
        foreach ($refs as $ref) {
            $out[$ref->key()] = $this->get($ref->type())->snapshot($ref->id());
        }
        return $out;
    }
}
