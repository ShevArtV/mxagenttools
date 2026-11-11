<?php

namespace MxAgentTools\Tests\Fakes;

use MxAgentTools\Modx\ExternalIds;

/** Плагин сайта: отдаёт заданные коды, запоминает, что спросили */
class FakeExternalIds extends ExternalIds
{
    /** @var array */
    private $codes;
    /** @var array|null */
    public $asked;

    public function __construct(array $codes = [])
    {
        $this->codes = $codes;
    }

    public function codes(array $modifications): array
    {
        $this->asked = $modifications;
        return $this->codes;
    }
}
