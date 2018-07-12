<?php

namespace Instana\OpenTracing;

class InstanaHttpSpanFlusher implements InstanaSpanFlusher
{
    const REST_SDK_ENDPOINT = 'http://127.0.0.1:42699/com.instana.plugin.generic.trace';

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @param string $endpoint
     */
    public function __construct($endpoint = self::REST_SDK_ENDPOINT)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Flushes all given Spans to the given endpoint
     *
     * @param array $unflushedSpans
     */
    public function flushAll(array $unflushedSpans)
    {
        $payload = json_encode($unflushedSpans);

        if (empty($payload)) {
            return;
        }

       $context = stream_context_create([
            'http' => [
                'protocol_version' => '1.1',
                'method' => 'POST',
                'user_agent' => UserAgent::get(),
                'header'=> [
                    'Content-type: application/json',
                    'Connection: close'
                ],
                'content' => $payload,
                'timeout' => 0.05,
                'ignore_errors'=> true
            ],
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ]);

        if (!is_resource($context)) {
            return;
        }

        file_get_contents($this->endpoint, false, $context);
    }
}