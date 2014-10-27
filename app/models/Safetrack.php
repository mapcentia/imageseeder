<?php
namespace app\models;

class Safetrack extends \app\inc\Model
{
    private $token;

    private function getJSON($endPoint, $q)
    {
        $ch = curl_init("https://api.trackunit.com{$endPoint}?" . $q);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if ($info["http_code"] == 200) {
            $response['success'] = true;
            $response['output'] = json_decode($output);
        } else {
            $response['success'] = false;
            $response['output'] = json_decode($output);
            $response['code'] = $info["http_code"];
        }
        return $response;
    }

    private function getJSONAsync($urls, $callback)
    {
        $requests = new \app\inc\Requests();
        $requests->process($urls, $callback);

    }

    private function makeUnitSummarySql($list, $schema, $table)
    {
        $sqls = array();
        foreach ($list as $obj) {
            $format = "INSERT INTO {$schema}.{$table}(unitid,run1,date,the_geom) VALUES(%s,%s,'%s',ST_geomfromtext('POINT(%s %s)',4326));";
            if (isset($obj->stopLocation)) {
                $sqls[] = sprintf($format,
                    $obj->unitId,
                    $obj->run1,
                    $obj->stopTime,
                    $obj->stopLocation->longitude, $obj->stopLocation->latitude);
            }
        }
        return $sqls;
    }

    private function makeZoneSql($list, $schema, $table)
    {
        $sqls = array();
        foreach ($list as $obj) {
            $format = "INSERT into {$schema}.{$table}(id,name,the_geom) VALUES(%s,'%s',ST_geomfromtext(ST_astext(ST_FlipCoordinates(ST_geomfromtext('%s',4326))),4326));";
            $sqls[] = sprintf($format, $obj->id, $obj->name, $obj->polygon);
        }
        return $sqls;
    }

    public function makeName()
    {
        return "_" . md5(rand(1, 999999999) . microtime());
    }

    public static function getToken($u, $p)
    {
        $e = "/private/Login";
        $q = "format=json&username={$u}&password={$p}&appName=Heatmap";
        $obj = self::getJSON($e, $q);
        if (!$obj["success"]) {
            $response['success'] = false;
            $response['message'] = "Could not fetch token from SafeTrack";
            $response['code'] = 200;
            return $response;
        }
        return $obj["output"]->token;

    }

    private function getUnit($groupId = null, $clientId = null, $categoryId = null)
    {
        $req = "token={$this->token}&GroupId={$groupId}&ClientId={$clientId}&CategoryId={$categoryId}&format=json";
        error_log("getUnit " . $req);
        $obj = $this->getJSON("/public/Unit", $req);
        if (!$obj["success"]) {
            $response['success'] = false;
            $response['message'] = "Could not fetch units from SafeTrack";
            $response['code'] = 200;
            return $response;
        }
        return $obj;
    }

    public function getCreateDataSource($dateStart, $dateEnd, $u, $p)
    {
        $schema = "datasources";
        $table = "_" . md5($u);
        $this->connect();
        $sql = "SELECT 1 FROM {$schema}.{$table} LIMIT 1";
        $this->execQuery($sql);
        $this->token = self::getToken($u, $p);
        $period = new \DatePeriod(
            new \DateTime($dateStart),
            new \DateInterval('P1D'),
            new \DateTime($dateEnd . "+1 day")
        );
        $dates = iterator_to_array($period);
        $sql = "CREATE TABLE IF NOT EXISTS {$schema}.{$table} (
                gid serial unique,
                unitid int,
                run1 int,
                date date,
                the_geom geometry(Point,4326));";
        $this->execQuery($sql);
        if ($this->PDOerror) {
            $this->execQuery("ALTER TABLE {$schema}.{$table} ADD PRIMARY KEY (date,unitid)");
            $this->execQuery("CREATE INDEX ON {$schema}.{$table} USING gist (the_geom)");
            $this->execQuery("COMMENT ON TABLE {$schema}.{$table} IS '{$u}'");
            $this->PDOerror = null;
        }
        $numOfInserts = 0;
        $numOfNotInserts = 0;
        // Iterate over dates
        foreach ($dates as $date) {
            $reqs[] = "https://api.trackunit.com/public/Report/UnitSummary?" .
                "token=" . $this->token . "&format=json&date=" . urlencode($date->format("Y-m-d"));
        }
        $this->getJSONAsync($reqs, function ($data, $info) {
            global $output;
            $output[] = $data;

        });
        global $output;
        foreach ($output as $json) {
            $obj = json_decode($json);
            $sqls = $this->makeUnitSummarySql($obj->list, $schema, $table);
            foreach ($sqls as $sql) {
                $this->execQuery($sql);
                if (!$this->PDOerror) {
                    $numOfInserts++;
                } else {
                    $this->PDOerror = null;
                    $numOfNotInserts++;
                }

            }
        }
        if ($this->PDOerror) {
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            return $response;
        }
        $response['success'] = true;
        $response['num_of_inserts'] = $numOfInserts;
        $response['num_of_not_inserts'] = $numOfNotInserts;
        $response['relation'] = $schema . "." . $table;
        $response['num_of_reqs'] = sizeof($reqs);
        $response['num_of_reqs_com'] = sizeof($output);
        return $response;
    }

    public function getUnitSummary($dateStart, $dateEnd, $groupId = null, $clientId = null, $categoryId = null, $u, $p, $schema = null, $table = null, $dataSource = false)
    {
        $this->connect();
        // Is there a datasource
        $dataSource = "datasources._" . md5($dataSource);
        $sql = "SELECT 1 FROM {$dataSource} LIMIT 1";
        $this->execQuery($sql);
        if ($this->PDOerror) {
            $dataSource = false;
            $this->PDOerror = null;
        }
        $sql = "SELECT 1 FROM {$schema}.{$table} LIMIT 1";
        $this->execQuery($sql);
        if ($this->PDOerror) {
            $this->PDOerror = null;
            $this->token = self::getToken($u, $p);
            if (!$dataSource) {
                $period = new \DatePeriod(
                    new \DateTime($dateStart),
                    new \DateInterval('P1D'),
                    new \DateTime($dateEnd . "+1 day")
                );
            } else {
                $period = new \DatePeriod(
                    new \DateTime($dateStart . "-1 day"),
                    new \DateInterval('P1D'),
                    new \DateTime($dateEnd)
                );
            }
            $dates = iterator_to_array($period);
            $units = $this->getUnit($groupId, $clientId, $categoryId);
            $table = ($table) ? : $this->makeName();
            $schema = ($schema) ? : "temp";
            if (!$units["success"]) {
                $response['success'] = false;
                $response['message'] = "Could not fetch unit summary from SafeTrack";
                $response['code'] = 200;
                return $response;
            }
            $this->begin();
            if (!$dataSource) {
                $sql = "CREATE TABLE IF NOT EXISTS {$schema}.{$table} (gid serial unique, unitid int, run1 int, date date, the_geom geometry(Point,4326));";
                $this->execQuery($sql);
                $this->execQuery("ALTER TABLE {$schema}.{$table} ADD PRIMARY KEY (date,unitid)");
                $this->execQuery("CREATE INDEX ON {$schema}.{$table} USING gist (the_geom)");
            }
            $count = 0;
            $numOfInserts = 0;
            $unitReport = array();
            $numOfUnits = sizeof($units["output"]->list);
            // Iterate over units
            if (!$dataSource) {
                foreach ($units["output"]->list as $unit) {
                    array_push($unitReport, (int)$unit->id);
                    foreach ($dates as $date) {
                        $reqs[] = "https://api.trackunit.com/public/Report/UnitSummary?" .
                            "token=" . $this->token . "&UnitId=" . $unit->id . "&format=json&date=" . urlencode($date->format("Y-m-d"));
                    }
                    $count++;
                }
                $this->getJSONAsync($reqs, function ($data, $info) {
                    global $output;
                    $output[] = $data;

                });
                global $output;
                foreach ($output as $json) {
                    $obj = json_decode($json);
                    $sqls = $this->makeUnitSummarySql($obj->list, $schema, $table);
                    foreach ($sqls as $sql) {
                        $this->execQuery($sql);
                        $numOfInserts++;
                    }
                }
                $response['localdatasource'] = false;
            } else { //*************//
                foreach ($units["output"]->list as $unit) {
                    $wheres[] = "unitid = " . $unit->id;
                    array_push($unitReport, (int)$unit->id);
                    $count++;
                }
                if (sizeof($wheres) > 0) {
                    $where = implode(" OR ", $wheres);
                    foreach ($dates as $date) {
                        $wheres2[] =  "date = '".$date->format("Y-m-d")."'";
                    }
                    $where2 = implode(" OR ", $wheres2);
                    $sql = "CREATE VIEW {$schema}.{$table} as SELECT * FROM {$dataSource} WHERE ({$where}) AND ({$where2})";
                    $this->execQuery($sql);
                    //$sql = "SELECT count(*) FROM {$schema}.{$table}";
                    //$res = $this->execQuery($sql);
                    //$row = $this->fetchRow($res);
                    $numOfInserts = "Na";
                }
                $response['localdatasource'] = true;
            }
            if ($this->PDOerror) {
                $response['success'] = false;
                $response['message'] = $this->PDOerror[0];
                $response['code'] = 500;
                $this->rollback();
                return $response;
            }
            $this->commit();
            $response['success'] = true;
            $response['message'] = $numOfInserts . " inserted rows";
            $response['relation'] = $schema . "." . $table;
            $response['num_of_units'] = $numOfUnits;
            $response['num_of_reqs'] = sizeof($reqs);
            $response['num_of_reqs_com'] = sizeof($output);
            $response['units'] = $unitReport;
            $response['cached'] = false;

            return $response;
        } // Table already exist
        else {
            $response['success'] = true;
            $response['relation'] = $schema . "." . $table;
            $response['cached'] = true;
            return $response;
        }
    }

    public function getZone($u, $p)
    {
        $this->token = self::getToken($u, $p);
        $table = $this->makeName();
        $schema = "temp";
        $obj = $this->getJSON("/public/Zone", "token=" . $this->token . "&format=json");
        if (!$obj["success"]) {
            $response['success'] = false;
            $response['message'] = "Could not fetch zones from SafeTrack";
            $response['code'] = 200;
            return $response;
        }
        $this->connect();
        $this->begin();
        $sql = "CREATE TABLE {$schema}.{$table} (gid serial unique, id int, name varchar(255));";
        $this->execQuery($sql);
        $sql = "SELECT AddGeometryColumn('{$schema}','{$table}','the_geom',4326,'POLYGON',2);";
        $this->execQuery($sql);
        $sql = "CREATE INDEX ON {$schema}.{$table} USING gist (the_geom);";
        $this->execQuery($sql);

        if ($this->PDOerror) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $this->PDOerror[0];
            $response['code'] = 405;
            return $response;
        }

        $sqls = $this->makeZoneSql($obj["output"]->list, $schema, $table);
        foreach ($sqls as $sql) {
            $this->execQuery($sql);
            if ($this->PDOerror) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $this->PDOerror[0];
                $response['code'] = 406;
                return $response;
            }
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = sizeof($sqls) . " inserted rows";
        $response['relation'] = $schema . "." . $table;
        return $response;

    }

    public static function getCategory($token)
    {
        $e = "/public/Category";
        $q = "token={$token}";
        $obj = self::getJSON($e, $q);
        if (!$obj["success"]) {
            $response['success'] = false;
            $response['message'] = "Could not fetch categories from SafeTrack";
            $response['code'] = 401;
            return $response;
        }
        $response['categories'] = $obj["output"];
        $response['success'] = true;
        return $response;

    }

    public static function getGroup($token)
    {
        $e = "/public/Group";
        $q = "token={$token}";
        $obj = self::getJSON($e, $q);
        if (!$obj["success"]) {
            $response['success'] = false;
            $response['message'] = "Could not fetch groups from SafeTrack";
            $response['code'] = 401;
            return $response;
        }
        $response['groups'] = $obj["output"];
        $response['success'] = true;
        return $response;

    }

    public static function getClient($token)
    {
        $e = "/public/Client";
        $q = "token={$token}";
        $obj = self::getJSON($e, $q);
        if (!$obj["success"]) {
            $response['success'] = false;
            $response['message'] = "Could not fetch clients from SafeTrack";
            $response['code'] = 401;
            return $response;
        }
        $response['clients'] = $obj["output"];
        $response['success'] = true;
        return $response;

    }
}