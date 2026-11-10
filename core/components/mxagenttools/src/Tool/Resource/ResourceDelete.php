<?php

namespace MxAgentTools\Tool\Resource;

class ResourceDelete extends ResourceTrash
{
    const NAME = 'resource.delete';
    const TITLE = 'Ресурс в корзину';
    const PATH = '/agent/resources/{id:\\d+}/delete';
    const DESCRIPTION = 'Ресурс в корзину MODX (resource/delete) — вместе с дочерними. Откат и resource.undelete возвращают из корзины.';
    const PROCESSOR = 'resource/delete';
    const DELETED = '1';
}
