<?php

namespace Instana\OpenTracing;

use JsonSerializable;
use OpenTracing\Span;
use OpenTracing\SpanContext;

abstract class InstanaSpan implements Span, JsonSerializable
{
    /**
     * @var string
     */
    protected $operationName;

    /**
     * @var SpanContext
     */
    protected $spanContext;

    /**
     * @var Microtime
     */
    protected $startTime;

    /**
     * @var Microtime
     */
    protected $finishTime;

    /**
     * @var array
     */
    protected $annotations = [];

    /**
     * @var InstanaSpanType
     */
    protected $spanType;

    /**
     * @var boolean
     */
    protected $hasError = false;

    /**
     * @var string
     */
    protected $serviceName;

    /**
     * InstanaSpan constructor.
     */
    public function __construct($operationName, Microtime $startTime = null, InstanaSpanContext $spanContext = null)
    {
        $this->startTime = $startTime === null ? Microtime::create() : $startTime;
        $this->overwriteOperationName($operationName);

        $this->spanContext = $spanContext === null
            ? InstanaSpanContext::createRoot()
            : $spanContext;

        $this->spanType = $this->spanContext->getParentId() === null
            ? InstanaSpanType::entry()
            : InstanaSpanType::local();
    }

    /**
     * @return string
     */
    public function getOperationName()
    {
        return $this->operationName;
    }

    /**
     * Yields the SpanContext for this Span. Note that the return value of
     * Span::getContext() is still valid after a call to Span::finish(), as is
     * a call to Span::getContext() after a call to Span::finish().
     *
     * @return SpanContext
     */
    public function getContext()
    {
        return $this->spanContext;
    }

    /**
     * Sets the end timestamp and finalizes Span state.
     *
     * With the exception of calls to getContext() (which are always allowed),
     * finish() must be the last call made to any span instance, and to do
     * otherwise leads to undefined behavior but not returning an exception.
     *
     * As an implementor, make sure you call {@see Tracer::deactivate()}
     * otherwise new spans might try to be child of this one.
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param float|int|\DateTimeInterface|null $finishTime if passing float or int
     * it should represent the timestamp (including as many decimal places as you need)
     * @return void
     */
    public function finish($finishTime = null)
    {
        $this->logWarningIfSpanIsAlreadyFinished();
        $this->finishTime = Microtime::create($finishTime);
    }

    /**
     * Returns the Finishing time or False when the span is not finished yet
     * @return Microtime|false
     */
    public function isFinished()
    {
        return $this->finishTime instanceof Microtime ? $this->finishTime : false;
    }

    /**
     * If the span is already finished, a warning should be logged.
     *
     * @param string $newOperationName
     */
    public function overwriteOperationName($newOperationName)
    {
        $this->logWarningIfSpanIsAlreadyFinished();
        $this->operationName = (string) $newOperationName;
    }

    /**
     * Adds a tag to the span.
     *
     * If there is a pre-existing tag set for key, it is overwritten.
     *
     * As an implementor, consider using "standard tags" listed in {@see \OpenTracing\Tags}
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param string $key
     * @param string|bool|int|float $value
     * @return void
     */
    public function setTag($key, $value)
    {
        $this->logWarningIfSpanIsAlreadyFinished();

        if ($value === null || !is_scalar($value)) {
            throw new \InvalidArgumentException("Tag values may be scalars only.");
        }

        switch ($key) {
            case InstanaTags\SERVICE:
                $this->serviceName = $value;
                break;
            case \OpenTracing\Tags\ERROR:
                $this->annotations[$key] = $value;
                $this->hasError = true;
                break;
            case \OpenTracing\Tags\SPAN_KIND:
                switch ($value) {
                    case \OpenTracing\Tags\SPAN_KIND_RPC_SERVER:
                    case \OpenTracing\Tags\SPAN_KIND_MESSAGE_BUS_CONSUMER:
                        $this->spanType = InstanaSpanType::entry();
                        break;
                    case \OpenTracing\Tags\SPAN_KIND_RPC_CLIENT:
                    case \OpenTracing\Tags\SPAN_KIND_MESSAGE_BUS_PRODUCER:
                        $this->spanType = InstanaSpanType::exit();
                        break;
                }
                break;
            default:
                $this->annotations[$key] = $value;
        }
    }

    /**
     * Adds a log record to the span in key => value format, key must be a string and tag must be either
     * a string, a boolean value, or a numeric type.
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param array $fields
     * @param int|float|\DateTimeInterface $timestamp
     * @return void
     */
    public function log(array $fields = [], $timestamp = null)
    {
        $this->logWarningIfSpanIsAlreadyFinished();
        $timestamp = Microtime::create($timestamp);
        foreach ($fields as $key => $val) {
            $this->setTag("log.$timestamp.$key", $val);
        }
    }

    /**
     * Adds a baggage item to the SpanContext which is immutable so it is required to use
     * SpanContext::withBaggageItem to get a new one.
     *
     * If the span is already finished, a warning should be logged.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function addBaggageItem($key, $value)
    {
        $this->spanContext = $this->getContext()->withBaggageItem($key, $value);
    }

    /**
     * @param string $key
     * @return string|null returns null when there is not a item under the provided key
     */
    public function getBaggageItem($key)
    {
        return $this->spanContext->getBaggageItem($key);
    }

    /**
     * Logs a warning if the Span is already finished
     */
    protected function logWarningIfSpanIsAlreadyFinished()
    {
        if ($this->isFinished()) {
            // @todo implement warning
        }
    }
}
