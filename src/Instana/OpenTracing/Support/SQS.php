<?php

namespace Instana\OpenTracing\Support;

use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing\SpanContext;
use OpenTracing\Tracer;
use OpenTracing\Formats;

/**
 * Facilitates tracing support for Amazon Simple Queue Service.
 *
 * We need to inject the context into our message before sending it to SQS
 * and after receiving the message, we need to extract the context from the message.
 */
class SQS {
    /**
     * Inject the current trace context into a message that will be queued on SQS.
     *
     * @param Tracer $tracer The tracer to do the extraction
     * @param SpanContext $context
     * @param array $message The raw message
     *
     * @return void
     */
    public static function injectContext(Tracer $tracer, SpanContext $context, array &$message)
    {
        if (!isset($message['MessageAttributes']['Instana'])) {
            $message['MessageAttributes']['Instana'] = [
                'DataType' => 'String',
                'StringValue' => '',
            ];
        }

        $carrier = [];
        $tracer->inject($context, Formats\TEXT_MAP, $carrier);
        $message['MessageAttributes']['Instana']['StringValue'] = \json_encode($carrier);
    }

    /**
     * Extract the span context from the message read from the queue.
     *
     * @param Tracer $tracer
     * @param array $message
     * @return null|SpanContext
     * @throws UnsupportedFormat
     */
    public static function extractContext(Tracer $tracer, array $message): ?SpanContext
    {
        if (!isset($message['MessageAttributes']['Instana'])) {
            return null;
        }

        if (!isset($message['MessageAttributes']['Instana']['StringValue'])) {
            return null;
        }

        $carrier = \json_decode($message['MessageAttributes']['Instana']['StringValue'], true);

        return $tracer->extract(Formats\TEXT_MAP, $carrier);
    }
}
