<?php
namespace app\api\v1;

use \app\inc\Input;

class Seeder extends \app\inc\Controller
{
    private $urls;

    function __construct()
    {
        $this->urls = explode("\n", Input::get(null, true));
    }

    function post_index()
    {
        print_r($this->urls );
        return array("sdsd" => "ds");
    }

    function get_nyborg()
    {
        return array("sdsd" => "nyborg");
    }
}