<?php
namespace Instana\OpenTracing;

class InstanaSpanFactory
{
    /**
     * @var int
     */
    private $processId;

    /**
     * @var string
     */
    private $containerId;

    /**
     * @var string
     */
    private $spanType;

    /**
     * InstanaSpanFactory constructor.
     * @param string $instanaSpanType
     */
    public function __construct($instanaSpanType)
    {
        if (!in_array(InstanaSpan::class, class_parents($instanaSpanType))) {
            throw new \DomainException("$instanaSpanType must inherit from " . InstanaSpan::class);
        }
        $this->spanType = $instanaSpanType;
        $this->initProcessId();
        $this->initContainerId();
    }

    /**
     * Sets the (parent) Process ID for the PHP process
     *
     * This is needed in order to connect spans to Instana's Dynamic graph feature
     */
    private function initProcessId()
    {
        if ($this->processId === null) {
            if (1 === preg_match('#(fpm-fcgi|apache)#i', php_sapi_name())) {
                if (function_exists('posix_getppid')) {
                    $this->processId = posix_getppid();
                }
            } else {
                $pid = getmypid();
                if ($pid != false) {
                    $this->processId = $pid;
                }
            }
        }
    }

    /**
     * Sets the Container ID for the PHP process when running in Docker
     *
     * This is needed in order to connect spans to Instana's Dynamic graph feature
     */
    private function initContainerId()
    {
        if ($this->containerId === null) {
            $procFile = sprintf('/proc/%d/cpuset', $this->processId);
            if (is_readable($procFile)) {
                if (preg_match("#([a-f0-9]{64})#", trim(file_get_contents($procFile)), $cid)) {
                    $this->containerId = $cid[0];
                    return;
                }
            }
            $this->containerId = '';
        }
    }

    /**
     * Creates a new InstanaSpan
     *
     * @param $operationName
     * @param Microtime|null $startTime
     * @param InstanaSpanContext|null $spanContext
     * @return InstanaRestSdkSpan|InstanaSdkSpan
     */
    public function createSpan($operationName, Microtime $startTime = null, InstanaSpanContext $spanContext = null)
    {
        switch ($this->spanType) {
            case InstanaSdkSpan::class:
                return new InstanaSdkSpan($operationName, $startTime, $spanContext, $this->processId, $this->containerId);
            case InstanaRestSdkSpan::class:
                return new InstanaRestSdkSpan($operationName, $startTime, $spanContext);
            default:
                throw new \LogicException("Unknown InstanaSpanType");
        }
    }
}