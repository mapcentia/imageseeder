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
        $this->urls = json_decode(Input::get(null, true));
    }

    public function post_url()
    {
        $seeds = array();
        for ($i = 0; $i < sizeof($this->urls); $i++) {
            $url = $this->urls[$i];
            $url = $url . "&lifetime=0";
            $seeds[$i] = $this->seed($url);
        }
        return array("succes"=>true, "result"=>$seeds);
    }

    public function post_object()
    {
        for ($i = 0; $i < sizeof($this->urls); $i++) {
            //print_r($this->urls[$i]);
            $o = $this->urls[$i];
            #http://54.171.150.242/api/v1/staticmap/png/nyborg?baselayer=DTKSKAERMKORTDAEMPET&layers=public.nyborg_nabokommuner,public.kommunegraense_streg,kommuneplan13.cykelstier_og_baner&size=500x500&bbox=1169113.8051039393,7394876.782495037,1225447.9239758018,7453351.100295125&lifetime=10
            $url = "{$this->host}/api/v1/staticmap/png/{$o->db}?baselayer={$o->baseLayers}&layers=kommuneplan13.bevaringsvaerdige_bygninger_i_by&size={$o->size}&bbox={$o->bbox}lifetime=0";
            echo $url."\n";
            $seeds[$i] = $this->seed($url);
        }
        return $seeds;

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