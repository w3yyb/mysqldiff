<?php

class MysqlDiff
{
    public $conn = [];
    public $error_add_tables = [];
    public $success_add_tables = [];
    public $create_table_sqls = [];
    public $error_repair_tables = [];
    public $success_repair_tables = [];
    public $repair_fields = [];


    public function __construct(array $conf)
    {
        $dbms = 'mysql';
        $master = $conf['master'];
        $slave = $conf['slave'];
        $onlycheck = $conf['onlycheck'];
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $master['host'], $master["port"], $master['db']);
        $dbh_conn_master = new PDO($dsn, $master['user'], $master['pwd']);
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $slave['host'], $slave["port"], $slave['db']);
        $dbh_conn_slave = new PDO($dsn, $slave['user'], $slave['pwd']);
        $this->conn['master'] = $dbh_conn_master;
        $this->conn['slave'] = $dbh_conn_slave;
        $this->conf['onlycheck'] = $onlycheck;
        $this->conf['master'] = $master;
        $this->conf['slave'] = $slave;
    }

    public function listTables()
    {
        $sql = 'SHOW TABLES;';
        $query_master = $this->conn['master']->query($sql);
        $query_slave = $this->conn['slave']->query($sql);
        $query_master = $query_master->fetchAll(PDO::FETCH_COLUMN);
        $query_slave = $query_slave->fetchAll(PDO::FETCH_COLUMN);
        return [$query_master, $query_slave];
    }

    public function getCreateTableSql($table)
    {
        $sql = 'SHOW CREATE TABLE `' . $table . '`;';
        $query = $this->conn['master']->query($sql);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $this->create_table_sqls[$table] = $row['Create Table'];
        return $this->create_table_sqls[$table];
    }

    public function addTables($table)
    {
        $_sql = $this->getCreateTableSql($table);
        print '<br>'.$_sql.'<br>';
        if ($this->conf['onlycheck']) {
            $ret='no';
        } else {
            $ret = $this->conn['slave']->exec($_sql);
        }
        if ($ret !== 0) {
            $this->error_add_tables[] = $table;
        }
        if ($ret!='no') {
            $this->success_add_tables[] = $table;
        }
    }

    public function repairTable($table)
    {
        $this->getCreateTableSql($table);
        $fieldarr=['Field','Type','Null','Key','Default','Extra'];
        $_sql = 'DESC ' . $table;
        $stmt = $this->conn['master']->prepare($_sql);
        $stmt->execute();
        $master_table_fields = $stmt->fetchAll();
        $master_table_fields_simgle = array_column($master_table_fields, 'Field');
        $master_table_fields = $this->arrayColumnMulti($master_table_fields, $fieldarr);
        $stmt = $this->conn['slave']->prepare($_sql);
        $stmt->execute();
        $slave_table_fields = $stmt->fetchAll();
        $slave_table_fields_simgle = array_column($slave_table_fields, 'Field');
        $slave_table_fields = $this->arrayColumnMulti($slave_table_fields, $fieldarr);

        foreach ($master_table_fields as $field) {
            if (!in_array($field, $slave_table_fields)) {
                $_str = $this->create_table_sqls[$table];
                $field = $field['Field'];
                $pattern = sprintf('/`%s`.*?(?=,\s|\s\))/s', $field);
                preg_match($pattern, $_str, $matchs);

                $tmp = $matchs[0] . ';';
                if (!in_array($field, $slave_table_fields_simgle)) {
                    $repair_sql = sprintf('ALTER TABLE `%s` ADD %s', $table, $tmp);
                } else {
                    $tmp = "`$field` ".$tmp;
                    $repair_sql = sprintf('ALTER TABLE `%s` CHANGE COLUMN %s', $table, $tmp);
                }
              
                if ($this->conf['onlycheck']) {
                    print($repair_sql).'<br>';
                    $ret='no';
                } else {
                    print($repair_sql).'<br>';
                    $ret = $this->conn['slave']->exec($repair_sql);
                }
                if ($ret !== 0) {
                    $this->error_repair_tables[] = $table;
                }
                if ($ret!='no') {
                    $this->success_repair_tables[] = $table;
                }
            }
        }

        $_sql = "SHOW CREATE TABLE `$table`";
        $stmt = $this->conn["slave"]->query($_sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $slave_create_sqls = $row["Create Table"];
        $pattern = "/(UNIQUE|PRIMARY|) KEY(.*?)\)/m";
        preg_match_all($pattern, $this->create_table_sqls[$table], $match_master);
        preg_match_all($pattern, $slave_create_sqls, $match_slave);
        $exist_key_slave = array_flip($match_slave[0]);
        foreach ($match_master[0] as $item) {
            if (!isset($exist_key_slave[$item])) {
                $repair_sql = sprintf("ALTER TABLE `%s` ADD %s", $table, $item.';');
                if ($this->conf['onlycheck']) {
                    print($repair_sql).'<br>';
                    $ret='no';
                } else {
                    print $repair_sql.'<br>';
                    $ret = $this->conn['slave']->exec($repair_sql);
                }
                if (isset($ret) && ($ret !== 0)) {
                    $this->error_repair_tables[] = $table;
                }
                if ($ret!='no') {
                    $this->success_repair_tables[] = $table;
                }
            }
        }
    }

    public function run()
    {
        if ($this->conf['onlycheck']) {
            echo '对比数据库<br>';
        } else {
            echo    '修复数据库<br>';
        }
        echo $this->conf['master']['host'].':'.$this->conf['master']['db'].'==>';
        echo $this->conf['slave']['host'].':'.$this->conf['slave']['db'].' <br><br>';
        list($master_tables, $slave_tables) = $this->listTables();

        foreach ($master_tables as $table) {
            if (!in_array($table, $slave_tables)) {
                $this->addTables($table);
            } else {
                $this->repairTable($table);
            }
        }
    }

    public function getAddTables()
    {
        return [array_unique($this->success_add_tables), array_unique($this->error_add_tables)];
    }

    public function getRepairTables()
    {
        return [array_unique($this->success_repair_tables), array_unique($this->error_repair_tables)];
    }

    public function arrayColumnMulti(array $input, array $column_keys)
    {
        $result = array();
        $column_keys = array_flip($column_keys);
        foreach ($input as $key => $el) {
            $result[$key] = array_intersect_key($el, $column_keys);
        }
        return $result;
    }
}

$conf = require dirname(__FILE__) . '/config.php';
$md = new MysqlDiff($conf);
$md->run();


list($success_add_tables, $error_add_tables) = $md->getAddTables();
list($success_repair_tables, $error_repair_tables) = $md->getRepairTables();
echo '==================================================<br>';
echo sprintf("Success add tables:\t%s\n", implode(',', $success_add_tables)).'<br>';
echo sprintf("Error add tables:\t%s\n", implode(',', $error_add_tables)).'<br>';

echo sprintf("Success repair tables:\t%s\n", implode(',', $success_repair_tables)).'<br>';
echo sprintf("Error repair tables:\t%s\n", implode(',', $error_repair_tables)).'<br>';
