<?php

namespace Instana\OpenTracing;

class InstanaRestSdkSpan extends InstanaSpan
{

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
            'spanId' => $this->spanContext->getSpanId(),
            'traceId' => $this->spanContext->getTraceId(),
            'name' => $this->getOperationName(),
            'timestamp' => $this->startTime->getMilliseconds(),
            'duration' => $this->finishTime ? $this->finishTime->durationFrom($this->startTime)->getMilliseconds() : 0,
            'type' => strtoupper($this->spanType->getType()),
            'data' => $this->annotations
        ];

        if ($this->spanContext->getParentId() !== null) {
            $json['parentId'] = $this->spanContext->getParentId();
        }

        if ($this->hasError) {
            $json['error'] = true;
        }

        return $json;
    }
}