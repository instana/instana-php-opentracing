<?php

namespace Instana\OpenTracing;

use Instana\OpenTracing\InstanaSpanFlusher;

class NoopSpanFlusher implements InstanaSpanFlusher {
    private $spans;

    public function __construct()
    {
        $this->spans = [];
    }

    /**
     * Flush the given spans to an internal array.
     *
     * @param array $unflushedSpans
     * @return void
     */
    public function flushAll(array $unflushedSpans) {
        foreach ($unflushedSpans as $span) {
            $this->spans[] = $span;
        }
    }

    /**
     * Get all the spans created.
     *
     * @return array
     */
    public function getSpans()
    {
        return $this->spans;
    }
}
