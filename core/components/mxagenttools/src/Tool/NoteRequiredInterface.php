<?php

namespace MxAgentTools\Tool;

/**
 * Маркер инструмента записи: mode=apply без note (кто и по чьей просьбе) — отказ. Для инструментов, чья запись
 * чувствительна (цены, скидки): журнал без «по чьей просьбе» бесполезен.
 */
interface NoteRequiredInterface
{
}
