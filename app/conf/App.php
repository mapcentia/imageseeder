<?php
namespace app\conf;

class App
{
    static $param = array(
        "host" => "",
        "userHostName" => "",
        "path" => "/var/www/imageseeder/",
    );

    function __construct(){
        spl_autoload_register(function ($className) {
            $ds = DIRECTORY_SEPARATOR;
            $dir = App::$param['path'];
            $className = strtr($className, '\\', $ds);
            $file = "{$dir}{$className}.php";
            //die($file);
            if (is_readable($file)) {
                require_once $file;
            }
        });
        set_include_path(get_include_path() . PATH_SEPARATOR . App::$param['path'] . PATH_SEPARATOR . App::$param['path'] . "app" . PATH_SEPARATOR . App::$param['path'] . "app/libs/PEAR/");
    }
}