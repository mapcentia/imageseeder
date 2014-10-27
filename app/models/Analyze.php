<?php

namespace app\models;

use app\inc\Util;

class Analyze extends \app\inc\Model
{

    /*private function getJSON($db, $sql)
    {
        $ch = curl_init("http://local2.mapcentia.com/api/v1/sql/{$db}?srs=4326&q=" . $sql);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if ($info["http_code"] == 200) {
            $response['success'] = true;
            $response['json'] = $output;
        } else {
            $response['success'] = false;
            $response['output'] = json_decode($output);
            $response['code'] = $info["http_code"];
        }
        return $response;
    }*/

    public function avgRunOnAdmin($table)
    {
        $view = \app\models\Safetrack::makeName()."_view";
        $schema = "temp";

        $admTable = "trackunit.states_provinces_split";
        $sql = "CREATE view {$schema}.{$view} AS SELECT avg({$table}.run1)::integer AS run1, count(*) as count, sum({$table}.run1)::integer AS activity, TO_CHAR((avg({$table}.run1)::integer || ' second')::interval, 'HH24:MI:SS') as rtime, TO_CHAR((sum({$table}.run1)::integer || ' second')::interval, 'HH24:MI:SS') as atime, {$admTable}.the_geom, {$admTable}.gid, {$admTable}.name ".
                "FROM {$table}, {$admTable} ".
                "WHERE {$table}.the_geom && st_transform({$admTable}.the_geom, 4326) AND st_intersects({$table}.the_geom, st_transform({$admTable}.the_geom, 4326)) ".
                "GROUP BY {$admTable}.the_geom, {$admTable}.gid, {$admTable}.name";

        /*$sql = "CREATE view {$schema}.{$view} AS SELECT avg({$table}.run1)::integer AS run1, count(*) as count, sum({$table}.run1)::integer AS activity, TO_CHAR((avg({$table}.run1)::integer || ' second')::interval, 'HH24:MI:SS') as rtime, TO_CHAR((sum({$table}.run1)::integer || ' second')::interval, 'HH24:MI:SS') as atime, kommune.komkode, kommune.the_geom, kommune.gid, kommune.komnavn ".
            "FROM {$table}, trackunit.kommune ".
            "WHERE {$table}.the_geom && st_transform(kommune.the_geom, 4326) AND st_intersects({$table}.the_geom, st_transform(kommune.the_geom, 4326)) ".
            "GROUP BY kommune.komkode, kommune.the_geom, kommune.gid, kommune.komnavn";*/
        $this->connect();
        $this->begin();
        $this->execQuery($sql);
        if ($this->PDOerror) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 405;
            return $response;
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "View created";
        $response['relation'] = $schema.".".$view;
        return $response;
    }
    public function avgRunOnZone($table,$u,$p){
        $safeTrack = new \app\models\Safetrack();
        $obj = $safeTrack->getZone($u,$p);

        if (!$obj["success"]) {
            $response['success'] = false;
            $response['message'] = $obj["message"];
            $response['code'] = $obj["code"];
            return $response;
        }

        $view = \app\models\Safetrack::makeName()."_view";
        $schema = "temp";
        $sql = "CREATE view {$schema}.{$view} AS SELECT avg({$table}.run1)::integer AS run1, count(*) as count, sum({$table}.run1)::integer AS activity, TO_CHAR((avg({$table}.run1)::integer || ' second')::interval, 'HH24:MI:SS') as rtime, TO_CHAR((sum({$table}.run1)::integer || ' second')::interval, 'HH24:MI:SS') as atime, ".$obj["relation"].".id, ".$obj["relation"].".the_geom, ".$obj["relation"].".gid, ".$obj["relation"].".name ".
            "FROM {$table}, ".$obj["relation"]." ".
            "WHERE {$table}.the_geom && st_transform(".$obj["relation"].".the_geom, 4326) AND st_intersects({$table}.the_geom, st_transform(".$obj["relation"].".the_geom, 4326)) ".
            "GROUP BY ".$obj["relation"].".id, ".$obj["relation"].".the_geom, ".$obj["relation"].".gid, ".$obj["relation"].".name";
        //echo $sql."\n";
        $this->connect();
        $this->begin();
        $this->execQuery($sql);
        if ($this->PDOerror) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 405;
            return $response;
        }

        $this->commit();
        $response['success'] = true;
        $response['message'] = "View created";
        $response['relation'] = $schema.".".$view;
        return $response;
    }

}
