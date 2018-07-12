<?php
/**
 * Created by PhpStorm.
 * User: gooh
 * Date: 09.07.18
 * Time: 15:54
 */

namespace Instana\OpenTracing;

interface InstanaSpanFlusher
{
    /**
     * Flushes all given Spans to the given endpoint
     *
     * @param array $unflushedSpans
     */
    public function flushAll(array $unflushedSpans);
}