<?php

namespace Instana\OpenTracing;

class InstanaSdkSpan extends InstanaSpan
{

    /**
     * @var int
     */
    protected $processId;

    /**
     * @var string
     */
    protected $containerId;


    /**
     * InstanaSdkSpan constructor.
     *
     * @param string $operationName
     * @param Microtime|null $startTime
     * @param InstanaSpanContext|null $spanContext
     * @param int|null $processId
     * @param string|null $containerId
     */
    public function __construct(
        $operationName, Microtime $startTime = null, InstanaSpanContext $spanContext = null,
        $processId = null, $containerId = null)
    {
        parent::__construct($operationName, $startTime, $spanContext);
        $this->processId = $processId;
        $this->containerId = $containerId;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $json = [
            's' => $this->spanContext->getSpanId(),
            't' => $this->spanContext->getTraceId(),
            'ta' => 'php',
            'n' => 'sdk',
            'ts' => (int) $this->startTime->getMilliseconds(),
            'd' => $this->finishTime
                ? (int) ($this->finishTime->durationFrom($this->startTime)->getMilliseconds())
                : 0,
            'k' => $this->spanType->getKind(),
            'data' => [
                'sdk' => [
                    'name' => $this->getOperationName(),
                    'type' => $this->spanType->getType(),
                    'custom' => [
                        'tags' => $this->annotations
                    ]
                ]
            ]
        ];

        if ($this->spanContext->getParentId() !== null) {
            $json['p'] = $this->spanContext->getParentId();
        }

        if ($this->spanType === InstanaSpanType::entryType()) {
            if ($this->processId != null) {
                $json['ppid'] = $this->processId;
            }
            if (!empty($this->containerId)) {
                $json['cid'] = $this->containerId;
            }
            if (!empty($this->serviceName)) {
                $json['data']['service'] = $this->serviceName;
            }
        }

        if ($this->hasError) {
            $json['error'] = true;
            $json['ec'] = 1;
        }

        return $json;
    }
}