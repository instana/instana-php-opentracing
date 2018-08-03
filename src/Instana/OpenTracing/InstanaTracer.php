<?php

namespace Instana\OpenTracing;

use OpenTracing\Exceptions\InvalidReferencesSet;
use OpenTracing\Exceptions\InvalidSpanOption;
use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing\Reference;
use OpenTracing\Scope;
use OpenTracing\ScopeManager;
use OpenTracing\Span;
use OpenTracing\SpanContext;
use OpenTracing\StartSpanOptions;
use OpenTracing\Tracer;

final class InstanaTracer implements Tracer
{
    /**
     * @var InstanaScopeManager
     */
    private $scopeManager;

    /**
     * @var InstanaSpanFlusher
     */
    private $spanFlusher;

    /**
     * @var InstanaSpanFactory
     */
    private $spanFactory;

    /**
     * @var array
     */
    private $unflushedSpans = [];

    /**
     * @param ScopeManager $scopeManager
     * @param InstanaSpanFlusher $spanFlusher
     * @param InstanaSpanFactory $spanFactory
     */
    public function __construct(ScopeManager $scopeManager, InstanaSpanFlusher $spanFlusher, InstanaSpanFactory $spanFactory)
    {
        $this->scopeManager = $scopeManager;
        $this->spanFlusher = $spanFlusher;
        $this->spanFactory = $spanFactory;
    }

    /**
     * @return InstanaTracer
     */
    public static function getDefault()
    {
        return InstanaTracer::phpSensor();
    }

    /**
     * Returns a new InstanaTracer that will send traces to the PHP Sensor in the agent
     *
     * This is equivant to just doing <code>new InstanaTracer()</code>
     *
     * @param string $endpointUri Defaults to InstanaTcpSpanFlusher::PHP_SENSOR_ENDPOINT
     * @return InstanaTracer
     */
    public static function phpSensor($endpointUri = InstanaTcpSpanFlusher::PHP_SENSOR_ENDPOINT)
    {
        return new InstanaTracer(
            new InstanaScopeManager(),
            new InstanaTcpSpanFlusher($endpointUri),
            new InstanaSpanFactory(InstanaSdkSpan::class)
        );
    }

    /**
     * Returns a new InstanaTracer that will send traces to the REST SDK endpoint in the agent
     *
     * @param string $endpointUri Defaults to InstanaHttpSpanFlusher::REST_SDK_ENDPOINT
     * @return InstanaTracer
     */
    public static function restSdk($endpointUri = InstanaHttpSpanFlusher::REST_SDK_ENDPOINT)
    {
        return new InstanaTracer(
            new InstanaScopeManager(),
            new InstanaHttpSpanFlusher($endpointUri),
            new InstanaSpanFactory(InstanaRestSdkSpan::class)
        );
    }

    /**
     * Returns the current {@link ScopeManager}, which may be a noop but may not be null.
     *
     * @return ScopeManager
     */
    public function getScopeManager()
    {
        return $this->scopeManager;
    }

    /**
     * Returns the active {@link Span}. This is a shorthand for
     * Tracer::getScopeManager()->getActive()->getSpan(),
     * and null will be returned if {@link Scope#active()} is null.
     *
     * @return Span|null
     */
    public function getActiveSpan()
    {
        $activeScope = $this->getScopeManager()->getActive();
        return $activeScope ? $activeScope->getSpan() : null;
    }

    /**
     * Starts and returns a new `Span` representing a unit of work.
     *
     * This method differs from `startSpan` because it uses in-process
     * context propagation to keep track of the current active `Span` (if
     * available).
     *
     * Starting a root `Span` with no casual references and a child `Span`
     * in a different function, is possible without passing the parent
     * reference around:
     *
     *  function handleRequest(Request $request, $userId)
     *  {
     *      $rootSpan = $this->tracer->startActiveSpan('request.handler');
     *      $user = $this->repository->getUser($userId);
     *  }
     *
     *  function getUser($userId)
     *  {
     *      // `$childSpan` has `$rootSpan` as parent.
     *      $childSpan = $this->tracer->startActiveSpan('db.query');
     *  }
     *
     * @param string $operationName
     * @param array|StartSpanOptions $options A set of optional parameters:
     *   - Zero or more references to related SpanContexts, including a shorthand for ChildOf and
     *     FollowsFrom reference types if possible.
     *   - An optional explicit start timestamp; if omitted, the current walltime is used by default
     *     The default value should be set by the vendor.
     *   - Zero or more tags
     *   - FinishSpanOnClose option which defaults to true.
     *
     * @return Scope
     */
    public function startActiveSpan($operationName, $options = [])
    {
        if (!($options instanceof StartSpanOptions)) {
            $options = StartSpanOptions::create($options);
        }

        if (($activeSpan = $this->getActiveSpan()) !== null) {
            $options = $options->withParent($activeSpan);
        }

        $span = $this->startSpan($operationName, $options);

        return $this->scopeManager->activate($span, $options->shouldFinishSpanOnClose());
    }

    /**
     * Starts and returns a new `Span` representing a unit of work.
     *
     * @param string $operationName
     * @param array|StartSpanOptions $options
     * @return Span
     * @throws InvalidSpanOption for invalid option
     * @throws InvalidReferencesSet for invalid references set
     */
    public function startSpan($operationName, $options = [])
    {
        if (!($options instanceof StartSpanOptions)) {
            $options = StartSpanOptions::create($options);
        }

        if (empty($options->getReferences())) {
            $spanContext = InstanaSpanContext::createRoot();
        } else {
            /** @var $spanContext InstanaSpanContext */
            $spanContext = $options->getReferences()[0]->getContext();
            $spanContext = $spanContext->createChildContext();
        }

        $span = $this->spanFactory->createSpan(
            $operationName,
            Microtime::create($options->getStartTime()),
            $spanContext
        );

        foreach ($options->getTags() as $key => $value) {
            $span->setTag($key, $value);
        }

        $this->unflushedSpans[] = $span;

        return $span;
    }

    /**
     * @param SpanContext $spanContext
     * @param string $format
     * @param mixed $carrier
     *
     * @see Formats
     *
     * @throws UnsupportedFormat when the format is not recognized by the tracer
     * implementation
     */
    public function inject(SpanContext $spanContext, $format, &$carrier)
    {
        if (!($spanContext instanceof InstanaSpanContext)) {
            throw new \RuntimeException(sprintf("Can only inject from InstanaSpanContext, %s given.", get_class($spanContext)));
        }

        switch ($format) {
            case \OpenTracing\Formats\TEXT_MAP:
            case \OpenTracing\Formats\HTTP_HEADERS:
                $carrier["X-INSTANA-S"] = $spanContext->getSpanId();
                $carrier["X-INSTANA-T"] = $spanContext->getTraceId();
                $carrier["X-INSTANA-L"] = "1";
                break;

            default:
                throw new UnsupportedFormat();
        }
    }

    /**
     * @param string $format
     * @param mixed $carrier
     * @return SpanContext|null
     *
     * @see Formats
     *
     * @throws UnsupportedFormat when the format is not recognized by the tracer
     * implementation
     * @throws \Exception when the IDs do match the required format
     */
    public function extract($format, $carrier)
    {
        switch ($format) {
            case \OpenTracing\Formats\TEXT_MAP:
            case \OpenTracing\Formats\HTTP_HEADERS:
                $spanId = $traceId = null;

                if (isset($carrier['X-INSTANA-L']) && !$carrier['X-INSTANA-L']) {
                    return null;
                }

                if (isset($carrier['HTTP_X_INSTANA_L']) && !$carrier['HTTP_X_INSTANA_L']) {
                    return null;
                }

                if (isset($carrier['X-INSTANA-S'])) {
                    $spanId = $carrier['X-INSTANA-S'];
                }

                if (isset($carrier['X-INSTANA-T'])) {
                    $traceId = $carrier['X-INSTANA-T'];
                }

                if (isset($carrier['HTTP_X_INSTANA_S'])) {
                    $spanId = $carrier['HTTP_X_INSTANA_S'];
                }

                if (isset($carrier['HTTP_X_INSTANA_T'])) {
                    $traceId = $carrier['HTTP_X_INSTANA_T'];
                }

                if (!$spanId || !$traceId) {
                    return null;
                }

                return InstanaSpanContext::fromDistributed($traceId, $spanId);

            default:
                throw new UnsupportedFormat();
        }
    }

    /**
     * Allow tracer to send span data to be instrumented.
     *
     * This method might not be needed depending on the tracing implementation
     * but one should make sure this method is called after the request is delivered
     * to the client.
     *
     * As an implementor, a good idea would be to use {@see register_shutdown_function}
     * or {@see fastcgi_finish_request} in order to not to delay the end of the request
     * to the client.
     */
    public function flush()
    {
        $flushable = $unflushable = [];

        foreach ($this->unflushedSpans as $span) {
            if ($span->isFinished()) {
                $flushable[] = $span;
            } else {
                $unflushable[] = $span;
            }
        }

        if (count($flushable) > 0) {
            $this->spanFlusher->flushAll($flushable);
        }

        $this->unflushedSpans = $unflushable;
    }
}
