<?php

namespace Instana\OpenTracing\Support;

use Aws\Sqs\SqsClient;
use Exception;
use OpenTracing\Exceptions\UnsupportedFormat;
use OpenTracing\SpanContext;
use OpenTracing\Tags;
use OpenTracing\Tracer;
use OpenTracing\Formats;
use OpenTracing\GlobalTracer;

/**
 * Facilitates tracing support for Amazon Simple Queue Service.
 *
 * We need to inject the context into our message before sending it to SQS
 * and after receiving the message, we need to extract the context from the message.
 */
class SQS {
    /**
     * Create a span for the interaction with SQS.
     *
     * @param array $message The payload for SQS
     *
     * @return void
     */
    public static function sendMessage(SqsClient $sqsClient, array &$message) {
        if (!array_key_exists('QueueUrl', $message) || !is_string($message['QueueUrl'])) {
            // message malformed according to expectations
            $queueUrl = 'queue_url_not_set';
        } else {
            $queueUrl = $message['QueueUrl'];
        }

        $tracer = GlobalTracer::get();
        $scope = $tracer->startActiveSpan('sqs');
        $span = $scope->getSpan();

        $span->setTag(Tags\SPAN_KIND, Tags\SPAN_KIND_MESSAGE_BUS_PRODUCER);

        $span->setTag('message_bus.destination', $queueUrl);

        $span->setTag('messaging.type', 'sqs');
        $span->setTag('messaging.address', $queueUrl);
        $span->setTag('messaging.destination', $queueUrl);
        $span->setTag('messaging.exchangeType', 'SQS');
        $span->setTag('messaging.routingKey', $queueUrl);

        // inject ourselves into the message
        self::injectContext($tracer, $span->getContext(), $message);

        // do the actual dispatching
        try {
            $result = $sqsClient->sendMessage($message);
        } catch (Exception $e) {
            $span->setTag('error.message', $e->getMessage());

            throw $e;
        } finally {
            $scope->close();
        }

        return $result;
    }

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
