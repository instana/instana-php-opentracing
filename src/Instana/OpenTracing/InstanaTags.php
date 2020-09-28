<?php

namespace Instana\OpenTracing\InstanaTags;

/**
 * Sets the service name for this Span (applied to entry spans only)
 */
const SERVICE = 'service';

/**
 * Sets the endpoint for this Span
 */
const ENDPOINT = 'endpoint';

/**
 * Batch Job tags
 * 
 * @see https://www.instana.com/docs/tracing/custom-best-practices/#batch
 */

/**
 * The name of the job being executed.
 */
const BATCH_JOB = 'batch.job';

/**
 * Database Tags
 * 
 * @see https://www.instana.com/docs/tracing/custom-best-practices/#database
 */

/**
 * Connection string, e.g. jdbc:mysql://127.0.0.1:3306/customers
 */
const DATABASE_CONNECTION_STRING = 'db.connection_string';

/**
 * Error Tags
 * 
 * @see https://www.instana.com/docs/tracing/custom-best-practices/#errors
 */

/**
 * A message associated with the error
 */
const ERROR_MESSAGE = 'error.message';

/**
 * HTTP Tags
 * 
 * @see https://www.instana.com/docs/tracing/custom-best-practices/#http
 */

/**
 * In the case of an error, an error message associated with this span. 
 * 
 * Example: 'Internal Server Error'
 */
const HTTP_ERROR = 'http.error';

/**
 * Used to report custom headers in relation so this span such as "X-My-Custom-Header=afd812cab"
 */
const HTTP_HEADER = 'http.header';

/**
 * The remote host if a client request or the host handling an incoming HTTP request.
 */
const HTTP_HOST = 'http.host';

/**
 * The query parameters of the HTTP request
 *  
 * Example: 'foo=bar&baz=qux'
 */
const HTTP_PARAMS = 'http.params';

/**
 * The HTTP path of the request.
 */
const HTTP_PATH = 'http.path';

/**
 * Allows for visual grouping of endpoints. See the Path Templates documentation for details
 * 
 * @see https://www.instana.com/docs/ecosystem/opentracing/#path-templates-visual-grouping-of-http-endpoints
 */
const HTTP_PATH_TPL = 'http.path_tpl';

/**
 * A unique identifier for your route such as blog.show; useful with frameworks where a distinct endpoint is referenced by id
 */
const HTTP_ROUTE_ID = 'http.route_id';

/**
 * RPC Tags
 * 
 * @see https://www.instana.com/docs/tracing/custom-best-practices/#rpc
 */

/**
 * Parameters for the RPC call
*/
const RPC_PARAMS = 'rpc.params';

/**
 * Port for the RPC call
 */
const RPC_PORT = 'rpc.port';

/**
 * Flavor of the RPC library in use such as XMLRPC, GRPCIO etc...
 */
const RPC_FLAVOR = 'rpc.flavor';

/**
 * Error message associated with the RPC call
 */
const RPC_ERROR = 'rpc.error';