<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Response;

class Seeder extends \app\inc\Controller
{
    protected $host;
    protected $obj;

    function __construct()
    {
        $this->host = "http://54.171.150.242";
    }

    public function post_url()
    {
        $this->obj = json_decode(Input::get(null, true));
        $seeds = array();
        for ($i = 0; $i < sizeof($this->obj->urls); $i++) {
            $urlObj = $this->obj->urls[$i];
            $url = $urlObj->url . "&lifetime=0";
            $seeds[$i] = $this->seed($url);
        }
        return array("succes" => true, "jobId" => $this->obj->jobId, "result" => $seeds);
    }

    public function get_url()
    {
        $layers = (array)json_decode(urldecode(Input::get("layers")));
        $bbox = urldecode(Input::get("bbox"));
        $db = urldecode(Input::get("db"));
        $baseLayer = urldecode(Input::get("baseLayer"));
        $size = urldecode(Input::get("size"));
        $temp = array();
        for ($i = 0; $i < sizeof($layers); $i++) {
            $temp[] = $layers[$i]->name;
        }
        $layersStr = implode(",", $temp);
        $url = "{$this->host}/api/v1/staticmap/png/{$db}?baselayer={$baseLayer}&layers={$layersStr}&size={$size}&bbox={$bbox}&lifetime=9999999";

        echo Response::passthru("<img class=\"imageseeder\"src=\"{$url}\"/>");
        exit();

    }

    private function seed($url)
    {

        if (exif_imagetype($url)) {
            $res = true;
        } else {
            $res = false;
        }
        return $res;
    }


    function get_nyborg()
    {
        return array("sdsd" => "nyborg");
    }
}