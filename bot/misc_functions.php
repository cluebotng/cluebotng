<?php



    function myfnmatch($pattern, $string)
    {
        if (strlen($string) < 4000) {
            return fnmatch($pattern, $string);
        } else {
            $pattern = strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.', '\[' => '[', '\]' => ']'));
            if (preg_match('#^'.$pattern.'$#', $string)) {
                return true;
            }

            return false;
        }
    }

    function doInit()
    {
        if (Config::$pass == null) {
            Config::$pass = trim(file_get_contents(getenv('HOME').'/.cluebotng.password.only'));
        }

        API::init();
        API::$a->login(Config::$user, Config::$pass);

        Globals::$mysql = false;
        checkMySQL();

        Globals::$tfas = 0;
        Globals::$stdin = fopen('php://stdin', 'r');
        Globals::$run = API::$q->getpage('User:'.Config::$user.'/Run');
        Globals::$wl = API::$q->getpage('Wikipedia:Huggle/Whitelist');
        Globals::$optin = API::$q->getpage('User:'.Config::$user.'/Optin');
        Globals::$aoptin = API::$q->getpage('User:'.Config::$user.'/AngryOptin');

        Globals::$stalk = array();
        Globals::$edit = array();

        $tmp = explode("\n", API::$q->getpage('User:'.Config::$owner.'/CBAutostalk.js'));
        foreach ($tmp as $tmp2) {
            if (substr($tmp2, 0, 1) != '#') {
                $tmp3 = explode('|', $tmp2, 2);
                Globals::$stalk[ $tmp3[ 0 ] ] = trim($tmp3[ 1 ]);
            }
        }

        $tmp = explode("\n", API::$q->getpage('User:'.Config::$owner.'/CBAutoedit.js'));
        foreach ($tmp as $tmp2) {
            if (substr($tmp2, 0, 1) != '#') {
                $tmp3 = explode('|', $tmp2, 2);
                Globals::$edit[ $tmp3[ 0 ] ] = trim($tmp3[ 1 ]);
            }
        }
    }

    function getCurrentCoreNode()
    {
        checkMySQL();
        $res = mysql_query('SELECT `node` from `cluster_node` where type="core"', Globals::mysql);
        $d = mysql_fetch_assoc($res);

        return $d['node'];
    }

    function getCurrentRelayNode()
    {
        checkMySQL();
        $res = mysql_query('SELECT `node` from `cluster_node` where type="core"', Globals::mysql);
        $d = mysql_fetch_assoc($res);

        return $d['node'];
    }
