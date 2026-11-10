<?php

namespace MxAgentTools\Entity;

/**
 * Объект удаляется мягко (ресурс — в корзину MODX): после remove() снимок не null, но объекта для сайта нет.
 * Откат созданного считает такой снимок равным «объекта нет».
 */
interface SoftDeleteInterface
{
    public function isRemoved(array $snapshot): bool;
}
