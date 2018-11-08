<?php

namespace Instana\OpenTracing;

final class UserAgent
{
    /**
     * @return string
     */
    public static function get()
    {
       return 'Instana PHP OpenTracing/2.0.0';
    }
}