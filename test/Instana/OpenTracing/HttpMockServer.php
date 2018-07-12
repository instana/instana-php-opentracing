<?php

namespace Instana\OpenTracing;

/**
 * HttpMockServer for testing
 *
 * Adapted from https://github.com/php/php-src/blob/master/ext/curl/tests/server.inc
 *
 * @license http://www.php.net/license/
 */
class HttpMockServer
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $router;

    /**
     * @var resource
     */
    private $handle;

    /**
     * @param $host
     * @param $port
     * @param $router
     */
    public function __construct($host, $port, $router)
    {
        $this->host = $host;
        $this->port = $port;
        $this->router = $router;
    }

    /**
     * @return string
     */
    public function start()
    {
        $serverAddress = "{$this->host}:{$this->port}";
        $doc_root = __DIR__;

        $this->handle = self::runsOnWindows()
            ? self::startOnWindows($doc_root, $serverAddress, $this->router)
            : self::startOnNix($doc_root, $serverAddress, $this->router);

        $error = "Unable to connect to server\n";
        for ($i = 0; $i < 60; $i++) {
            usleep(50000); // 50ms per try
            $status = proc_get_status($this->handle);
            $fp = @fsockopen($this->host, $this->port);
            // Failure, the server is no longer running
            if (!($status && $status['running'])) {
                $error = "Server is not running\n";
                break;
            }

            if ($fp) {
                // Success, Connected to servers
                $error = '';
                break;
            }
        }

        if ($fp) {
            fclose($fp);
        }

        if ($error) {
            echo $error;
            proc_terminate($this->handle);
            exit(1);
        }

        $this->registerShutdownFunction();

        return $serverAddress;
    }

    /**
     * @return bool
     */
    private static function runsOnWindows(): bool
    {
        return substr(PHP_OS, 0, 3) == 'WIN';
    }

    /**
     * @param string $docRoot
     * @param string $serverAddress
     * @param string $router
     * @return bool|resource
     */
    private static function startOnWindows($docRoot, $serverAddress, $router)
    {
        $descriptorspec = array(
            0 => STDIN,
            1 => STDOUT,
            2 => array("pipe", "w"),
        );
        $cmd = "php -t {$docRoot} -n -S " . $serverAddress;
        $cmd .= " {$router}";
        $handle = proc_open(
            addslashes($cmd),
            $descriptorspec,
            $pipes,
            $docRoot,
            NULL,
            array(
                "bypass_shell" => true,
                "suppress_errors" => true
            )
        );
        return $handle;
    }

    /**
     * @param string $docRoot
     * @param string $serverAddress
     * @param string $router
     * @return bool|resource
     */
    private static function startOnNix($docRoot, $serverAddress, $router)
    {
        $descriptorspec = array(
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        );

        $cmd = "exec php -t {$docRoot} -n -S " . $serverAddress;
        $cmd .= " {$router}";
        $cmd .= " 2>/dev/null";
        $handle = proc_open($cmd, $descriptorspec, $pipes, $docRoot);

        return $handle;
    }

    private function registerShutdownFunction()
    {
        register_shutdown_function(
            function() {
                $this->stop();
            }
        );
    }

    public function stop()
    {
        if (!is_resource($this->handle)) {
            echo 'No open file handle';
            return;
        }

        proc_terminate($this->handle);
        /* Wait for server to shutdown */
        for ($i = 0; $i < 60; $i++) {
            $status = proc_get_status($this->handle);
            if (!($status && $status['running'])) {
                break;
            }
            usleep(50000);
        }
    }
}