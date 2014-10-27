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
define('PI2', 2 * M_PI);

class HeatMapPoint
{
    public $x, $y;

    function __construct($x, $y, $w)
    {
        $this->x = $x;
        $this->y = $y;
        $this->w = $w;
    }

    function __toString()
    {
        return "({$this->x},{$this->y})";
    }
}

//Point

class HeatMap
{
    //TRANSPARENCY
    public static $WITH_ALPHA = 0;
    public static $WITH_TRANSPARENCY = 1;
    //GRADIENT STYLE
    public static $GRADIENT_CLASSIC = 'classic';
    public static $GRADIENT_FIRE = 'fire';
    public static $GRADIENT_PGAITCH = 'pgaitch';
    //GRADIENT MODE (for heatImage)
    public static $GRADIENT_NO_NEGATE_NO_INTERPOLATE = 0;
    public static $GRADIENT_NO_NEGATE_INTERPOLATE = 1;
    public static $GRADIENT_NEGATE_NO_INTERPOLATE = 2;
    public static $GRADIENT_NEGATE_INTERPOLATE = 3;
    //NOT PROCESSED PIXEL (for heatImage)
    public static $KEEP_VALUE = 0;
    public static $NO_KEEP_VALUE = 1;
    //CONSTRAINTS
    private static $MIN_RADIUS = 2; //in px
    private static $MAX_RADIUS = 50; //in px
    private static $MAX_IMAGE_SIZE = 10000; //in px

    //generate an $image_width by $image_height pixels heatmap image of $points
    public static function createImage($data, $image_width, $image_height, $mode = 0, $spot_radius = 30, $dimming = 75, $gradient_name = 'classic')
    {
        $_gradient_name = $gradient_name;
        if (($_gradient_name != self::$GRADIENT_CLASSIC) && ($_gradient_name != self::$GRADIENT_FIRE) && ($_gradient_name != self::$GRADIENT_PGAITCH)) {
            $_gradient_name = self::$GRADIENT_CLASSIC;
        }
        $_image_width = min(self::$MAX_IMAGE_SIZE, max(0, intval($image_width)));
        $_image_height = min(self::$MAX_IMAGE_SIZE, max(0, intval($image_height)));
        $_spot_radius = min(self::$MAX_RADIUS, max(self::$MIN_RADIUS, intval($spot_radius)));
        if (!is_array($data)) {
            return false;
        }
        $im = imagecreatetruecolor($_image_width, $_image_height);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $white);
        if (self::$WITH_ALPHA == $mode) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
        }
        foreach ($data as $d) {
            $w[] = $d->w;
        }
        $min = 0;
        $max = 40000;
        $new_min = 0;
        $new_max = 255;
        foreach ($w as $i => $v) {
            $w[$i] = ((($new_max - $new_min) * ($v - $min)) / ($max - $min)) + $new_min;
        }
        foreach ($data as $i => $d) {
            $data[$i]->w = $w[$i];
        }
        //Step 1: create grayscale image
        foreach ($data as $datum) {
            if ((is_array($datum) && (count($datum) == 1)) || (!is_array($datum) && ('HeatMapPoint' == get_class($datum)))) { //Plot points
                if ('HeatMapPoint' != get_class($datum)) {
                    $datum = $datum[0];
                }
                if ($datum->w == 0) $datum->w = 1;
                $_dimming = 255-$datum->w;
                //error_log($_dimming);
                self::_drawCircularGradient($im, $datum->x, $datum->y, $_spot_radius, $_dimming);
            }
        }
        //Gaussian filter
        if ($_spot_radius >= 30) {
            imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
        }
        //Step 2: create colored image
        if (FALSE === ($grad_rgba = self::_createGradient($im, $mode, $_gradient_name))) {
            return FALSE;
        }
        $grad_size = count($grad_rgba);
        for ($x = 0; $x < $_image_width; ++$x) {
            for ($y = 0; $y < $_image_height; ++$y) {
                $level = imagecolorat($im, $x, $y) & 0xFF;
                if (($level >= 0) && ($level < $grad_size)) {
                    imagesetpixel($im, $x, $y, $grad_rgba[imagecolorat($im, $x, $y) & 0xFF]);
                }
            }
        }
        if (self::$WITH_TRANSPARENCY == $mode) {
            imagecolortransparent($im, $grad_rgba[count($grad_rgba) - 1]);
        }
        return $im;
    }

    //createImage

    private static function _drawCircularGradient($im, $center_x, $center_y, $spot_radius, $dimming)
    {
        $dirty = array();
        $ratio = (255 - $dimming) / $spot_radius;
        for ($r = $spot_radius; $r > 0; --$r) {
            $channel = ($dimming) + $r * $ratio;
            $angle_step = 0.45 / $r; //0.01;
            //Process pixel by pixel to draw a radial grayscale radient
            for ($angle = 0; $angle <= PI2; $angle += $angle_step) {
                $x = floor($center_x + $r * cos($angle));
                $y = floor($center_y + $r * sin($angle));
                if (!isset($dirty[$x][$y])) {
                    $previous_channel = imagecolorat($im, $x, $y) & 0xFF; //grayscale so same value
                    $new_channel = Max(0, Min(255, ($previous_channel * $channel) / 255));
                    imagesetpixel($im, $x, $y, imagecolorallocate($im, $new_channel, $new_channel, $new_channel));
                    $dirty[$x][$y] = 0;
                }
            }
        }
    }

    private static function _createGradient($im, $mode, $gradient_name)
    {
        //create the gradient from an image
        if (FALSE === ($grad_im = imagecreatefrompng('gradient/' . $gradient_name . '.png'))) {
            echo "Hier gaat het fout\n";
            return FALSE;
        }
        $width_g = imagesx($grad_im);
        $height_g = imagesy($grad_im);
        //Get colors along the longest dimension
        //Max density is for lower channel value
        for ($y = $height_g - 1; $y >= 0; --$y) {
            $rgb = imagecolorat($grad_im, 1, $y);
            //Linear function
            $alpha = Min(127, Max(0, floor(127 - $y / 2)));
            if (self::$WITH_ALPHA == $mode) {
                $grad_rgba[] = imagecolorallocatealpha($im, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF, $alpha);
            } else {
                $grad_rgba[] = imagecolorallocate($im, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
            }
        }
        imagedestroy($grad_im);
        unset($grad_im);
        return ($grad_rgba);
    }
    //_createGradient
}

//Heatmap
