<?php
// This file is included in index.php and will set the host names in the class App
\app\conf\App::$param['host'] =
\app\conf\App::$param['userHostName'] =
\app\conf\App::$param['esHost'] =
    "http://" . $_SERVER['SERVER_NAME'];
