<?php

namespace Instana\OpenTracing;

class InstanaTcpSpanFlusher implements InstanaSpanFlusher
{
    const PHP_SENSOR_ENDPOINT = 'tcp://127.0.0.1:16816';

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @param string $endpoint
     */
    public function __construct($endpoint = self::PHP_SENSOR_ENDPOINT)
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

        $client = stream_socket_client($this->endpoint, $errno,$errstr, 0.01);
        stream_set_blocking($client, false);
        fwrite($client, $payload);
        fclose($client);
    }
}