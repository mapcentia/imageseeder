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
            $url = $this->obj->urls[$i] . "&lifetime=0";
            $seeds[$i] = $this->seed($url);
        }
        return array("succes" => true, "jobId" => $this->obj->jobId, "result" => $seeds);
    }

    public function get_url()
    {
        $layers = (array)json_decode(urldecode(Input::get("layers")));

        $bbox = (Input::get("bbox")) ? explode(",", urldecode(Input::get("bbox"))) : explode(",", urldecode(Input::get("defaultbbox")));
        $db = urldecode(Input::get("db"));
        $baseLayer = urldecode(Input::get("baselayer"));
        $size = urldecode(Input::get("size"));
        $temp = array();
        $dbObj = new \app\inc\Model();
        $sql = "SELECT
            ST_X(ST_transform(ST_geomfromtext('POINT({$bbox[0]} {$bbox[1]})',4326),900913)) as x1,
            ST_Y(ST_transform(ST_geomfromtext('POINT({$bbox[0]} {$bbox[1]})',4326),900913)) as y1,
            ST_X(ST_transform(ST_geomfromtext('POINT({$bbox[2]} {$bbox[3]})',4326),900913)) as x2,
            ST_Y(ST_transform(ST_geomfromtext('POINT({$bbox[2]} {$bbox[3]})',4326),900913)) as y2
            ";
        $row = $dbObj->fetchRow($dbObj->execQuery($sql));
        //print_r($row);
        $bboxStr = implode(",",$row);

        for ($i = 0; $i < sizeof($layers); $i++) {
            $temp[] = $layers[$i]->name;
        }
        $layersStr = implode(",", $temp);
        $url = "{$this->host}/api/v1/staticmap/png/{$db}?baselayer={$baseLayer}&layers={$layersStr}&size={$size}&bbox={$bboxStr}&lifetime=9999999";

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