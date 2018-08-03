<?php

namespace Instana\OpenTracing;

final class UserAgent
{
    /**
     * @return string
     */
    public static function get()
    {
       return 'Instana PHP OpenTracing/1.0.2';
    }
}