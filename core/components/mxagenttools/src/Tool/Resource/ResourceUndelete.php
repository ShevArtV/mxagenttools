<?php

namespace MxAgentTools\Tool\Resource;

class ResourceUndelete extends ResourceTrash
{
    const NAME = 'resource.undelete';
    const TITLE = 'Ресурс из корзины';
    const PATH = '/agent/resources/{id:\\d+}/undelete';
    const DESCRIPTION = 'Вернуть ресурс из корзины MODX (resource/undelete). MODX 2.8 возвращает и всех удалённых дочерних, '
        . 'даже удалённых раньше отдельно.';
    const PROCESSOR = 'resource/undelete';
    const DELETED = '0';
}
