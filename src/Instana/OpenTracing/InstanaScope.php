<?php

namespace Instana\OpenTracing;

use OpenTracing\Scope;

class InstanaScope implements Scope
{
    /**
     * @var InstanaSpan
     */
    private $span;

    /**
     * @var bool
     */
    private $finishOnClose;

    /**
     * @var bool
     */
    private $closed = false;

    /**
     * @param InstanaSpan $span
     * @param bool $finishOnClose
     */
    public function __construct(InstanaSpan $span, bool $finishOnClose)
    {
        $this->span = $span;
        $this->finishOnClose = $finishOnClose;
    }

    /**
     * Mark the end of the active period for the current thread and {@link Scope},
     * updating the {@link ScopeManager#active()} in the process.
     *
     * NOTE: Calling {@link #close} more than once on a single {@link Scope} instance leads to undefined
     * behavior.
     */
    public function close()
    {
        $this->closed = true;

        if ($this->finishOnClose) {
            $this->span->finish();
        }
    }

    /**
     * @return Span the {@link Span} that's been scoped by this {@link Scope}
     */
    public function getSpan()
    {
        return $this->span;
    }

    /**
     * @private
     */
    public function isClosed()
    {
        return $this->closed;
    }
}
