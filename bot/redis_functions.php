<?PHP
    class RedisProxy
    {
        public static function sanitise($change)
        {
            $sanitised_change = $change;

            // Get rid of the page text
            unset($sanitised_change['all']['current']['text']);
            unset($sanitised_change['all']['previous']['text']);

            // Get rid of misc stuff
            unset($sanitised_change['rawline']);
            unset($sanitised_change['namespaceid']);

            return $sanitised_change;
        }

        public static function send($change)
        {
            $data = json_encode(self::sanitise($change));
            $udp = fsockopen('udp://'.trim(file_get_contents(getenv('HOME').'/.current_redis_relay_node')), 1345);
            fwrite($udp, $data);
            fclose($udp);
        }
    }
?>	
