<?php
namespace app\api\v1;

use \app\inc\Input;

class Seeder extends \app\inc\Controller
{
    protected $host;
    protected $urls;

    function __construct()
    {
        $this->host = "http://54.171.150.242";
        $this->urls = explode("\n", Input::get(null, true));
    }

    function post_index()
    {
        $seeds = array();
        for ($i = 0; $i < sizeof($this->urls); $i++) {
            $url = $this->urls[$i];
            $url = $url . "&lifetime=0";
            if (exif_imagetype($url)) {
                $seeds[$i] = true;
            } else {
                $seeds[$i] = false;
            }

        }
        return $seeds;
    }

    function get_nyborg()
    {
        return array("sdsd" => "nyborg");
    }
}