#!/usr/bin/php
<?php
header("Content-type: text/plain");
require_once("../App.php");
new \app\conf\App();

$obj = new \app\models\Safetrack();
$dateStart = "2014-02-10";
$dateEnd = "2014-02-17";
$u = "riwalgroup";
$p = "riwal3317";
$schema = "temp";
$table = "test";
$res = $obj->getCreateDataSource($dateStart, $dateEnd, $u, $p, $schema, $table);
print_r($res);
