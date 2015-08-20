<?php



    class api
    {
        public static $a;
        public static $q;
        public static $i;
        public static function init()
        {
            self::$a = new wikipediaapi();
            self::$q = new wikipediaquery();
            self::$i = new wikipediaindex();
        }
    }
