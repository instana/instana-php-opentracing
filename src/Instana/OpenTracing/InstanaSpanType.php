<?php

namespace Instana\OpenTracing;

class InstanaSpanType
{
    /**
     * @var InstanaSpanType
     */
    private static $entry;

    /**
     * @var InstanaSpanType
     */
    private static $exit;

    /**
     * @var InstanaSpanType
     */
    private static $local;

    /**
     * @var int
     */
    private $kind;

    /**
     * @var string
     */
    private $type;

    /**
     * @param int $kind
     * @param string $type
     */
    private function __construct($kind, $type)
    {
        $this->kind = $kind;
        $this->type = $type;
    }

    /**
     * @return InstanaSpanType
     */
    public static function entry() {
        if (self::$entry === null) {
            self::$entry = new InstanaSpanType(1, 'entry');
        }
        return self::$entry;
    }

    /**
     * @return InstanaSpanType
     */
    public static function exit() {
        if (self::$exit === null) {
            self::$exit = new InstanaSpanType(2, 'exit');
        }
        return self::$exit;
    }

    /**
     * @return InstanaSpanType
     */
    public static function local() {
        if (self::$local === null) {
            self::$local = new InstanaSpanType(3, 'local');
        }
        return self::$local;
    }

    /**
     * @return int
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}