# Instana OpenTracing for PHP

A PHP implementation of the OpenTracing interfaces for usage with Instana.

## Requirements

Requires an Instana Agent running at the configured  InstanaSpanFlusher  endpoint. By default,
traces will be sent to the PHP sensor's trace acceptor listening on port 16816 on any network
interface on the host machine. If no PHP sensor is running on the machine receiving the traces,
consider using the alternative REST SDK endpoint. To do so, set the global tracers as follows:

```php
use Instana\OpenTracing\InstanaTracer;

\OpenTracing\GlobalTracer::set(InstanaTracer::restSdk());
```

Using the restSdk tracer will send traces to the endpoint in the agent listening on port 42699.

Starting with v2.0.0 the minimum PHP version required is PHP 5.4.
The 1.x branch will only work on PHP 7+.

## BC breaks between the 1.x version and the 2.x version

The methods `InstanaSpanType::entry()`, `InstanaSpanType::local()` and `InstanaSpanType::exit()`
have been renamed to `InstanaSpanType::entryType()`, `InstanaSpanType::localType()` and
`InstanaSpanType::exitType()` to provide compatibility with PHP < 7. Direct invocations of these
methods in your code will need to be renamed.

## Installation

This library is available on Packagist.

```bash
composer req "instana/instana-php-opentracing:^2.0"
```

You can include it manually in your composer.json like this:

```json
{
    "require": {
        "instana/instana-php-opentracing": "^2.0"
    }
}
```

Because OpenTracing v1.0.0 is still in beta, you will also need to set

```json
{
    "config": {
        "prefer-stable": true,
        "minimum-stability": "beta",
    }
}
```

Otherwise, Composer will refuse to install the package.

## Example usage

```php
use Instana\OpenTracing\InstanaTracer;

\OpenTracing\GlobalTracer::set(InstanaTracer::getDefault());

$parentScope = \OpenTracing\GlobalTracer::get()->startActiveSpan('one');
$parentSpan = $parentScope->getSpan();
$parentSpan->setTag(\Instana\OpenTracing\InstanaTags\SERVICE, "example-service");
$parentSpan->setTag(\OpenTracing\Tags\COMPONENT, 'PHP simple example app');
$parentSpan->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_SERVER);
$parentSpan->setTag(\OpenTracing\Tags\PEER_HOSTNAME, 'localhost');
$parentSpan->setTag(\OpenTracing\Tags\HTTP_URL, '/php/simple/one');
$parentSpan->setTag(\Instana\OpenTracing\InstanaTags\HTTP_PATH_TPL, '/php/simple/{id}');
$parentSpan->setTag(\OpenTracing\Tags\HTTP_METHOD, 'GET');
$parentSpan->setTag(\OpenTracing\Tags\HTTP_STATUS_CODE, 200);
$parentSpan->log(['event' => 'bootstrap', 'type' => 'kernel.load', 'waiter.millis' => 1500]);

$childScope = \OpenTracing\GlobalTracer::get()->startActiveSpan('two');
$childSpan = $childScope->getSpan();
$childSpan->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_CLIENT);
$childSpan->setTag(\OpenTracing\Tags\PEER_HOSTNAME, 'localhost');
$childSpan->setTag(\OpenTracing\Tags\HTTP_URL, '/php/simple/two');
$childSpan->setTag(\OpenTracing\Tags\HTTP_METHOD, 'POST');
$childSpan->setTag(\OpenTracing\Tags\HTTP_STATUS_CODE, 204);

$childScope->close();
$parentScope->close();

\OpenTracing\GlobalTracer::get()->flush();
```

## Containerized applications

When instrumenting a containerized app, you will need to provide the endpointURI to point to the
agent running the container, e.g. for sending traces to the PHP Sensor you instantiate the tracer with

```php
use Instana\OpenTracing\InstanaTracer;

InstanaTracer::phpSensor('tcp://172.17.0.1:16816');
```

For sending traces to the REST SDK, you instantiate the tracer with

```php
use Instana\OpenTracing\InstanaTracer;

InstanaTracer::restSdk('http://172.17.0.1:42699/com.instana.plugin.generic.trace');
```

Adjust the URI to whatever URI allows communication from the container to the host.

## Support for 3rd party libraries

### AWS SQS (Simple Queue Service) context injection and extraction

When using `aws/aws-sdk-php` in your application, you will probably want to connect the dots.

The shipped class `\Instana\OpenTracing\Support\SQS` enables you to inject the current context
into the raw message passed to `\Aws\Sqs\SqsClient::sendMessage()` and extract it from messages
received via `\Aws\Sqs\SqsClient::receiveMessage()`.

## License

This library is licensed under the [MIT License](https://opensource.org/licenses/MIT)

> Copyright 2018 Instana, Inc
>
>  Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
>
>  The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
>
>  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
