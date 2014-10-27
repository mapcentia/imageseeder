<?php
/*
*DISCLAIMER
* 
*THIS SOFTWARE IS PROVIDED BY THE AUTHOR 'AS IS' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES *OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, *INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF *USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT *(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*
*	@author: Olivier G. <olbibigo_AT_gmail_DOT_com>
*	@version: 1.0
*	@history:
*		1.0	creation
*/
//
set_time_limit(120); //2mn
ini_set('memory_limit', '256M');
error_reporting(E_ALL & ~E_NOTICE & ~E_USER_NOTICE);
ini_set('display_errors', 'on');

require_once("../../app/conf/App.php");
require_once("../../app/conf/Connection.php");
require_once("../../app/inc/Model.php");
require_once('googleMapUtility.php');
require_once('heatMap.php');

new \app\conf\App();


//Root folder to store generated tiles
define('TILE_DIR', \app\conf\App::$param['path']."app/tmp/tiles/");
//Covered geographic areas
define('MIN_LAT', 42.0);
define('MAX_LAT', 52.0);
define('MIN_LNG', -6.0);
define('MAX_LNG', 9.0);
define('TILE_SIZE_FACTOR', 0.3);
define('SPOT_RADIUS', 30);
define('SPOT_DIMMING_LEVEL', 50);

//Input parameters
if (isset($_GET['x']))
    $X = (int)$_GET['x'];
else
    exit("x missing");
if (isset($_GET['y']))
    $Y = (int)$_GET['y'];
else
    exit("y missing");
if (isset($_GET['zoom']))
    $zoom = (int)$_GET['zoom'];
else
    exit("zoom missing");
if (isset($_GET['relation']))
    $relation = $_GET['relation'];
else
    exit("relation missing");

$dir = TILE_DIR . $zoom;
$tilename = $dir . '/' . $X . '_' . $Y . '.png';
//HTTP headers  (data type and caching rule)
header("Cache-Control: must-revalidate");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");
if (1===1) {
//if (!file_exists($tilename)) {
    $rect = GoogleMapUtility::getTileRect($X, $Y, $zoom);
    //A tile can contain part of a spot with center in an adjacent tile (overlaps).
    //Knowing the spot radius (in pixels) and zoom level, a smart way to process tiles would be to compute the box (in decimal degrees) containing only spots that can be drawn on current tile. We choose a simpler solution by increeasing  geo bounds by 2*TILE_SIZE_FACTOR whatever the zoom level and spot radius.
    $extend_X = $rect->width * TILE_SIZE_FACTOR; //in decimal degrees
    $extend_Y = $rect->height * TILE_SIZE_FACTOR; //in decimal degrees
    $swlat = $rect->y - $extend_Y;
    $swlng = $rect->x - $extend_X;
    $nelat = $swlat + $rect->height + 2 * $extend_Y;
    $nelng = $swlng + $rect->width + 2 * $extend_X;

    /*if (($nelat <= MIN_LAT) || ($swlat >= MAX_LAT) || ($nelng <= MIN_LNG) || ($swlng >= MAX_LNG)) {
        //No geodata so return generic empty tile
        echo file_get_contents('empty.png');
        exit();
    }*/
    $sql = "SELECT run1, ST_X(the_geom) as lng, ST_Y(the_geom) as lat FROM {$relation}";
    $sql .= " WHERE the_geom && ST_SetSRID(
                    ST_MakeBox2D(
                        ST_Point({$swlng}, {$swlat}), ST_Point({$nelng},{$nelat})
                    ),
                    4326
                ) LIMIT 1000";
    /*$sql = "SELECT kmeans, count(*), ST_X(ST_Centroid(ST_Collect(the_geom))) AS lng,ST_Y(ST_Centroid(ST_Collect(the_geom))) AS lat
            FROM (
              SELECT kmeans(ARRAY[ST_X(the_geom), ST_Y(the_geom)], 10) OVER (), the_geom
              FROM unitsummary WHERE the_geom && ST_SetSRID(
                    ST_MakeBox2D(
                        ST_Point({$swlng}, {$swlat}), ST_Point({$nelng},{$nelat})
                    ),
                    4326
                )
            ) AS ksub
            GROUP BY kmeans
            ORDER BY kmeans";*/
    $spots = fGetPOI($sql, $im, $X, $Y, $zoom, SPOT_RADIUS);
    if (empty($spots)) {
        //No geodata so return generic empty tile
        header('Content-type: image/png');
        echo file_get_contents('empty.png');
    } else {
        if (!file_exists($dir)) {
           mkdir($dir, 0705);
        }
        //All the magics is in HeatMap class :)
        $im = HeatMap::createImage($spots, GoogleMapUtility::TILE_SIZE, GoogleMapUtility::TILE_SIZE, heatMap::$WITH_ALPHA, SPOT_RADIUS, SPOT_DIMMING_LEVEL, HeatMap::$GRADIENT_FIRE);
        //Store tile for reuse and output it
        header('content-type:image/png;');
        imagepng($im, $tilename);
        if (file_exists($tilename)) {
            $content = file_get_contents($tilename);
            if (strlen($content) > 0) {
                echo $content;
            } else {
                echo "Retrieved content is empty. Permissions might not be set correctly";
                exit;
            }
        } else {
            echo "Content not retrieved. Permissions might not be set correctly";
            exit;
        }
        imagepng($im);
        imagedestroy($im);
        unset($im);
    }
} else {
    //Output stored tile
    header('content-type:image/png;');
    echo file_get_contents($tilename);
}
/////////////
//Functions//
/////////////
function fGetPOI($query, &$im, $X, $Y, $zoom, $offset)
{
    $conn = new \app\inc\Model();
    try {
        $conn->connect();
    } catch (Exception $e) {
        echo $e->getMessage() . "\n";
        die();
    }
    $spots = array();
    $result = $conn->execQuery($query);
    //$count = $result->rowCount($result);
    $count = 1;
    $nbPOIInsideTile = 0;
    if ($count > 0) {
        while ($row = $conn->fetchRow($result)) {
            $point = GoogleMapUtility::getOffsetPixelCoords($row['lat'], $row['lng'], $zoom, $X, $Y);
            if (($point->x > -$offset) && ($point->x < (GoogleMapUtility::TILE_SIZE + $offset)) && ($point->y > -$offset) && ($point->y < (GoogleMapUtility::TILE_SIZE + $offset))) {
                $spots[] = new HeatMapPoint($point->x, $point->y, $row["run1"]);
            }
        }
    }
    $conn->close();
    return $spots;
}

function debug($string)
{
    //file_put_contents("log.txt", $string."\n", FILE_APPEND);
}
