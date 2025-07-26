<?php

namespace Pluto\Core;

if (! extension_loaded('pdo')) {
    throw new \Exception("MySQL requires the pdo extension for PHP");
}


class DB
{
    // initial connection
    public static $socket = null;
    public static $encoding = 'utf8';
    public static $connect_options = array(\PDO::ATTR_TIMEOUT => 30, \PDO::ATTR_STRINGIFY_FETCHES => true);

    // configure workings
    public static $param_char = '%';
    public static $named_param_seperator = '_';
    public static $nested_transactions = false;
    public static $reconnect_after = 14400;
    public static $logfile;

    // internal
    protected static $mdb = null;
    public static $variables_to_sync = array(
        // connection variables
        'socket',
        'encoding',
        'connect_options',
        // usage variables
        'param_char',
        'named_param_seperator',
        'nested_transactions',
        'reconnect_after',
        'logfile'
    );

    public static function getMDB()
    {
        if (DB::$mdb === null) {
            DB::$mdb = new MySQL();
        }

        // Sync everytime because settings might have changed. It's fast.
        DB::$mdb->sync_config();
        return DB::$mdb;
    }

    public static function __callStatic($name, $args)
    {
        $fn = array(DB::getMDB(), $name);
        if (! is_callable($fn)) {
            throw new MySQLException("MySQL does not have a method called $name");
        }

        return call_user_func_array($fn, $args);
    }

    /**
     * @deprecated
     */
    static function debugMode($enable = true)
    {
        if ($enable) self::$logfile = fopen('php://output', 'w');
        else self::$logfile = null;
    }
}


class MySQL
{
    // initial connection
    public $dbName = '';
    public $user = '';
    public $password = '';
    public $host = 'localhost';
    public $port = 3306;
    public $socket = null;
    public $encoding = 'latin1';
    public $connect_options = array(\PDO::ATTR_TIMEOUT => 30, \PDO::ATTR_STRINGIFY_FETCHES => true);
    public $dsn = '';

    // configure workings
    public $param_char = '%';
    public $named_param_seperator = '_';
    public $nested_transactions = false;
    public $reconnect_after = 14400;
    public $logfile;

    // internal
    public $internal_pdo = null;
    public $db_type = 'mysql';
    public $affected_rows = 0;
    public $nested_transactions_count = 0;
    public $last_query;
    public $last_query_at = 0;

    protected $hooks = array(
        'pre_run' => array(),
        'post_run' => array(),
        'run_success' => array(),
        'run_failed' => array(),
    );

    public function __construct(array $opts = array())
    {
        $this->sync_config();
        $this->host = getenv('DB_IP');
        $this->user = getenv('DB_USER');
        $this->password = getenv('DB_PASS');
        $this->dbName = getenv('DB_NAME');
        if ($opts) $this->connect_options = $opts;
    }

    /**
     * @internal 
     * suck in config settings from static class
     */
    public function sync_config()
    {
        foreach (DB::$variables_to_sync as $variable) {
            $this->$variable = DB::$$variable;
        }
    }

    public function dbType()
    {
        // $this->db_type var is only set after we connect, so we have to
        // make sure we've connected before returning the info
        $this->get();
        return $this->db_type;
    }

    public function get()
    {
        $pdo = $this->internal_pdo;

        if (!($pdo instanceof \PDO)) {
            if (! $this->dsn) {
                $dsn = array('host' => $this->host ?: 'localhost');
                if ($this->dbName) $dsn['dbname'] = $this->dbName;
                if ($this->port) $dsn['port'] = $this->port;
                if ($this->socket) $dsn['unix_socket'] = $this->socket;
                if ($this->encoding) $dsn['charset'] = $this->encoding;
                $dsn_parts = array();
                foreach ($dsn as $key => $value) {
                    $dsn_parts[] = $key . '=' . $value;
                }
                $this->dsn = 'mysql:' . implode(';', $dsn_parts);
            }

            list($this->db_type) = explode(':', $this->dsn);
            $this->db_type = strtolower($this->db_type);
            if (!in_array($this->db_type, ['mysql', 'sqlite', 'pgsql'])) {
                throw new MySQLException("Invalid DSN: " . $this->dsn);
            }

            try {
                $pdo = new \PDO($this->dsn, $this->user, $this->password, $this->connect_options);
                $this->internal_pdo = $pdo;
            } catch (\PDOException $e) {
                throw new MySQLException($e->getMessage());
            }
        }

        return $pdo;
    }

    public function disconnect()
    {
        $this->internal_pdo = null;
    }

    public function serverVersion()
    {
        return $this->get()->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }
    public function transactionDepth()
    {
        return $this->nested_transactions_count;
    }
    public function insertId()
    {
        return $this->get()->lastInsertId();
    }
    public function affectedRows()
    {
        return $this->affected_rows;
    }

    public function lastQuery()
    {
        return $this->last_query;
    }

    public function setDB()
    {
        return $this->useDB(...func_get_args());
    }
    public function useDB($dbName)
    {
        if (in_array($this->dbType(), array('pgsql', 'sqlite'))) {
            throw new MySQLException(sprintf('Database switching not supported by %s', $this->dbType()));
        }

        $this->_query('useDB', "USE :c", $dbName);
    }

    public function startTransaction()
    {
        $start_transaction = 'START TRANSACTION';
        if ($this->dbType() == 'sqlite') {
            $start_transaction = 'BEGIN TRANSACTION';
        }

        if ($this->nested_transactions && $this->nested_transactions_count > 0) {
            $this->_query('startTransaction', "SAVEPOINT LEVEL{$this->nested_transactions_count}");
            $this->nested_transactions_count++;
        } else {
            $this->_query('startTransaction', $start_transaction);
            $this->nested_transactions_count = 1;
        }

        return $this->nested_transactions_count;
    }

    public function commit($all = false)
    {
        $this->nested_transactions_count--;
        if ($all || $this->nested_transactions_count < 0) {
            $this->nested_transactions_count = 0;
        }

        if ($this->nested_transactions_count > 0) {
            $this->_query('commit', "RELEASE SAVEPOINT LEVEL{$this->nested_transactions_count}");
        } else {
            $this->_query('commit', 'COMMIT');
        }

        return $this->nested_transactions_count;
    }

    public function rollback($all = false)
    {
        $this->nested_transactions_count--;
        if ($all || $this->nested_transactions_count < 0) {
            $this->nested_transactions_count = 0;
        }

        if ($this->nested_transactions_count > 0) {
            $this->_query('rollback', "ROLLBACK TO SAVEPOINT LEVEL{$this->nested_transactions_count}");
        } else {
            $this->_query('rollback', 'ROLLBACK');
        }

        return $this->nested_transactions_count;
    }

    public function query()
    {
        return $this->queryHelper(['assoc' => true, 'name' => 'query'], func_get_args());
    }

    public function queryFullColumns()
    {
        return $this->queryHelper(['fullcols' => true, 'name' => 'queryFullColumns'], func_get_args());
    }

    public function queryWalk()
    {
        return $this->queryHelper(['walk' => true, 'name' => 'queryWalk'], func_get_args());
    }

    public function queryFirstRow()
    {
        $result = $this->queryHelper(['assoc' => true, 'name' => 'queryFirstRow'], func_get_args());
        if (!$result || !is_array($result)) return null;
        return reset($result);
    }

    public function queryAllLists()
    {
        return $this->queryHelper(['name' => 'queryAllLists'], func_get_args());
    }

    public function queryFirstList()
    {
        $result = $this->queryHelper(['name' => 'queryFirstList'], func_get_args());
        if (!$result || !is_array($result)) return null;
        return reset($result);
    }

    public function queryFirstColumn()
    {
        $results = $this->queryHelper(['name' => 'queryFirstColumn'], func_get_args());
        $ret = [];
        if (!count($results) || !count($results[0])) return $ret;

        foreach ($results as $row) {
            $ret[] = $row[0];
        }

        return $ret;
    }

    public function queryFirstField()
    {
        $results = $this->queryHelper(['name' => 'queryFirstField'], func_get_args());
        if (!is_array($results) || !$results) return null;
        $row = $results[0];
        if (!is_array($row) || !$row) return null;
        return $row[0];
    }

    public function update()
    {
        $args = func_get_args();
        if (count($args) < 3) {
            throw new MySQLException("update(): at least 3 arguments expected");
        }

        $table = array_shift($args);
        $params = array_shift($args);
        if (! is_array($params)) {
            throw new MySQLException("update(): second argument must be assoc array");
        }
        $ParsedQuery = $this->_parse('UPDATE :b SET :hc WHERE ', $table, $params);

        if (is_array($args[0])) {
            $Where = $this->_parse(':ha', $args[0]);
        } else {
            // we don't know if they used named or numbered args, so the where clause
            // must be run through the parser separately
            $Where = $this->parse(...$args);
        }

        $ParsedQuery->add($Where);
        return $this->_query('update', $ParsedQuery);
    }

    public function delete()
    {
        $args = func_get_args();
        if (count($args) < 2) {
            throw new MySQLException("delete(): at least 2 arguments expected");
        }

        $table = array_shift($args);
        $ParsedQuery = $this->_parse('DELETE FROM :b WHERE ', $table);

        if (is_array($args[0])) {
            $Where = $this->_parse(':ha', $args[0]);
        } else {
            $Where = $this->parse(...$args);
        }

        $ParsedQuery->add($Where);
        return $this->_query('delete', $ParsedQuery);
    }

    protected function insertOrReplace($mode, $table, $datas, $options = array())
    {
        $db_type = $this->dbType();
        $fn_name = 'insert';

        if ($mode == 'insert') {
            $action = 'INSERT';
        } else if ($mode == 'ignore') {
            $fn_name = 'insertIgnore';
            if ($db_type == 'sqlite') {
                $action = 'INSERT';
            } else if ($db_type == 'pgsql') {
                throw new MySQLException("postgres does not support insertIgnore()");
            } else {
                $action = 'INSERT IGNORE';
            }
        } else if ($mode == 'replace') {
            $fn_name = 'replace';
            $action = 'REPLACE';
        } else {
            throw new MySQLException("insertOrReplace() mode must be: insert, ignore, replace");
        }

        $datas = unserialize(serialize($datas)); // break references within array
        $keys = $values = array();
        $array_is_list = ($datas === [] || $datas === array_values($datas));

        if ($array_is_list && count($datas) > 0 && is_array($datas[0])) {
            foreach ($datas as $datum) {
                ksort($datum);
                if (! $keys) {
                    $keys = array_keys($datum);
                } else if ($keys !== array_keys($datum)) {
                    throw new MySQLException("when inserting multiple rows at once, they must have the same keys");
                }

                $values[] = array_values($datum);
            }

            $ParsedQuery = $this->_parse(':l INTO :b :lc VALUES :ll?', $action, $table, $keys, $values);
        } else {
            $keys = array_keys($datas);
            $values = array_values($datas);

            if ($db_type == 'mysql' || count($values) > 0) {
                $ParsedQuery = $this->_parse(':l INTO :b :lc VALUES :l?', $action, $table, $keys, $values);
            } else {
                $ParsedQuery = $this->_parse(':l INTO :b DEFAULT VALUES', $action, $table);
            }
        }

        $do_update = $mode == 'insert' && isset($options['update'])
            && is_array($options['update']) && $options['update'];

        if ($mode == 'ignore' && $db_type == 'sqlite') {
            $ParsedQuery->add(' ON CONFLICT DO NOTHING');
        } else if ($do_update) {
            $fn_name = 'insertUpdate';
            if ($db_type == 'sqlite') {
                $on_duplicate = 'ON CONFLICT DO UPDATE SET';

                $sqlite_version = $this->serverVersion();
                if ($sqlite_version < '3.35') {
                    throw new MySQLException("sqlite {$sqlite_version} does not support insertUpdate(), please upgrade");
                }
            } else if ($db_type == 'pgsql') {
                throw new MySQLException("postgres does not support insertUpdate()");
            } else {
                $on_duplicate = 'ON DUPLICATE KEY UPDATE';
            }
            $ParsedQuery->add(" {$on_duplicate} ");

            if (array_values($options['update']) !== $options['update']) {
                $Update = $this->_parse(':hc', $options['update']);
            } else {
                $update_str = array_shift($options['update']);
                $Update = $this->parse($update_str, ...$options['update']);
            }

            $ParsedQuery->add($Update);
        }

        return $this->_query($fn_name, $ParsedQuery);
    }

    public function insert($table, $data)
    {
        return $this->insertOrReplace('insert', $table, $data);
    }
    public function insertIgnore($table, $data)
    {
        return $this->insertOrReplace('ignore', $table, $data);
    }
    public function replace($table, $data)
    {
        return $this->insertOrReplace('replace', $table, $data);
    }

    public function insertUpdate()
    {
        $args = func_get_args();
        $table = array_shift($args);
        $data = array_shift($args);

        if (! isset($args[0])) { // update will have all the data of the insert
            if (isset($data[0]) && is_array($data[0])) { //multiple insert rows specified -- failing!
                throw new MySQLException("Badly formatted insertUpdate() query -- you didn't specify the update component!");
            }

            $args[0] = $data;
        }

        if (is_array($args[0])) $update = $args[0];
        else $update = $args;

        return $this->insertOrReplace('insert', $table, $data, array('update' => $update));
    }

    public function sqleval()
    {
        return $this->parse(...func_get_args());
    }

    public function columnList($table)
    {
        $db_type = $this->dbType();

        if ($db_type == 'sqlite') {
            $query = 'PRAGMA table_info(:b)';
            $primary = 'name';
        } else if ($db_type == 'pgsql') {
            $query = 'SELECT column_name, data_type, is_nullable, column_default 
        FROM information_schema.columns WHERE table_name=:s
        ORDER BY ordinal_position';
            $primary = 'column_name';
        } else {
            $query = 'SHOW COLUMNS FROM :b';
            $primary = 'Field';
        }

        $data = $this->_query('columnList', $query, $table);
        $columns = array();
        foreach ($data as $row) {
            $key = $row[$primary];
            $row2 = array();
            foreach ($row as $name => $value) {
                $row2[strtolower($name)] = $value;
            }

            $columns[$key] = $row2;
        }

        return $columns;
    }

    public function tableList($db = null)
    {
        if ($this->dbType() == 'sqlite') {
            if ($db) $tbl = "{$db}.sqlite_master";
            else $tbl = "sqlite_master";

            $result = $this->_query('tableList', "SELECT name FROM :b 
        WHERE type='table' AND name NOT LIKE 'sqlite_%'", $tbl);
        } else if ($this->dbType() == 'pgsql') {
            $result = $this->_query('tableList', "SELECT table_name
        FROM information_schema.tables
        WHERE table_schema='public'
        ORDER BY table_name");
        } else {
            if ($db) {
                $result = $this->_query('tableList', 'SHOW TABLES FROM :c', $db);
            } else {
                $result = $this->_query('tableList', 'SHOW TABLES');
            }
        }

        $column = array();
        foreach ($result as $row) {
            $column[] = reset($row);
        }
        return $column;
    }


    // *************** PARSER AND QUERY RUNNER
    protected function paramsMap()
    {
        $t = $this;

        $placeholders = function ($count, $batches = 1) {
            $question_marks = '(' . implode(',', array_fill(0, $count, '?')) . ')';
            return implode(',', array_fill(0, $batches, $question_marks));
        };

        $join = function (array $Queries, $glue = ',', $start = '', $end = '') {
            $Master = new MySQLParsedQuery();
            $parts = array();
            foreach ($Queries as $Query) {
                $parts[] = $Query->query;
                $Master->add('', $Query->params);
            }

            $Master->add($start . implode($glue, $parts) . $end);
            return $Master;
        };

        return array(
            's' => function ($arg) use ($t) {
                return new MySQLParsedQuery('?', array(strval($arg)));
            },
            'i' => function ($arg) use ($t) {
                return new MySQLParsedQuery('?', array($t->intval($arg)));
            },
            'd' => function ($arg) use ($t) {
                return new MySQLParsedQuery('?', array(doubleval($arg)));
            },
            'b' => function ($arg) use ($t) {
                return new MySQLParsedQuery($t->formatName($arg, true));
            },
            'c' => function ($arg) use ($t) {
                return new MySQLParsedQuery($t->formatName($arg));
            },
            'l' => function ($arg) use ($t) {
                return new MySQLParsedQuery(strval($arg));
            },
            't' => function ($arg) use ($t) {
                return new MySQLParsedQuery('?', array($t->sanitizeTS($arg)));
            },
            'ss' => function ($arg) use ($t) {
                $str = '%' . str_replace(array('%', '_'), array('\%', '\_'), $arg) . '%';
                return new MySQLParsedQuery('?', array($str));
            },
            'ls' => function ($arg) use ($t, $placeholders) {
                $arg = array_map('strval', $arg);
                return new MySQLParsedQuery($placeholders(count($arg)), $arg);
            },
            'li' => function ($arg) use ($t, $placeholders) {
                $arg = array_map(array($t, 'intval'), $arg);
                return new MySQLParsedQuery($placeholders(count($arg)), $arg);
            },
            'ld' => function ($arg) use ($t, $placeholders) {
                $arg = array_map('doubleval', $arg);
                return new MySQLParsedQuery($placeholders(count($arg)), $arg);
            },
            'lb' => function ($arg) use ($t) {
                $str = '(' . implode(',', array_map(array($t, 'formatName'), $arg)) . ')';
                return new MySQLParsedQuery($str);
            },
            'lc' => function ($arg) use ($t) {
                $str = '(' . implode(',', array_map(array($t, 'formatName'), $arg)) . ')';
                return new MySQLParsedQuery($str);
            },
            'lt' => function ($arg) use ($t, $placeholders) {
                $arg = array_map(array($t, 'sanitizeTS'), $arg);
                return new MySQLParsedQuery($placeholders(count($arg)), $arg);
            },
            '?' => function ($arg) use ($t) {
                return $t->sanitize($arg);
            },
            'l?' => function ($arg) use ($t, $join) {
                $Queries = array_map(array($t, 'sanitize'), $arg);
                return $join($Queries, ',', '(', ')');
            },
            'll?' => function ($arg) use ($t, $join) {
                $arg = array_values($arg);

                $count = count($arg); // number of entries to insret
                $length = null; // length of entry
                $Master = array(); // list of queries

                foreach ($arg as $entry) {
                    if (! is_array($entry)) {
                        throw new MySQLException("ll? must be used with a list of assoc arrays");
                    }
                    if (is_null($length)) {
                        $length = count($entry);
                    }
                    if (count($entry) != $length) {
                        throw new MySQLException("ll?: all entries must be the same length");
                    }

                    $Queries = array_map(array($t, 'sanitize'), $entry);
                    $Master[] = $join($Queries, ',', '(', ')');
                }

                return $join($Master, ',');
            },
            'hc' => function ($arg) use ($t, $join) {
                $Queries = array();
                foreach ($arg as $key => $value) {
                    $key = $t->formatName($key);
                    $Query = $t->sanitize($value);
                    $Queries[] = new MySQLParsedQuery($key . '=' . $Query->query, $Query->params);
                }
                return $join($Queries, ',');
            },
            'ha' => function ($arg) use ($t, $join) {
                $Queries = array();
                foreach ($arg as $key => $value) {
                    $key = $t->formatName($key);
                    $Query = $t->sanitize($value);
                    $Queries[] = new MySQLParsedQuery($key . '=' . $Query->query, $Query->params);
                }
                return $join($Queries, ' AND ');
            },
            'ho' => function ($arg) use ($t, $join) {
                $Queries = array();
                foreach ($arg as $key => $value) {
                    $key = $t->formatName($key);
                    $Query = $t->sanitize($value);
                    $Queries[] = new MySQLParsedQuery($key . '=' . $Query->query, $Query->params);
                }
                return $join($Queries, ' OR ');
            },

            $this->param_char => function ($arg) use ($t) {
                return new MySQLParsedQuery($t->param_char);
            },
        );
    }

    protected function paramsMapArrayTypes()
    {
        return array('ls', 'li', 'ld', 'lb', 'lc', 'll', 'lt', 'l?', 'll?', 'hc', 'ha', 'ho');
    }
    protected function paramsMapOptArrayTypes()
    {
        return array('b', 'c');
    }

    protected function sanitizeTS($ts)
    {
        if (is_string($ts)) {
            return date('Y-m-d H:i:s', strtotime($ts));
        } else if ($ts instanceof \DateTime) {
            return $ts->format('Y-m-d H:i:s');
        }
        return '';
    }

    protected function sanitize($input)
    {
        if (is_object($input)) {
            if ($input instanceof \DateTime) {
                return new MySQLParsedQuery('?', array($input->format('Y-m-d H:i:s')));
            }
            if ($input instanceof MySQLParsedQuery) {
                return $input;
            }
            return new MySQLParsedQuery('?', array(strval($input)));
        }

        if (is_null($input) || is_array($input)) return new MySQLParsedQuery('NULL');
        else if (is_bool($input)) return new MySQLParsedQuery('?', array($input ? 1 : 0));
        return new MySQLParsedQuery('?', array($input));
    }

    protected function formatName($name, $can_join = false)
    {
        if (is_array($name)) {
            if ($can_join) return implode('.', array_map(array($this, 'formatName'), $name));
            throw new MySQLException("Invalid column/table name");
        }
        $char = '`';
        if ($this->dbType() == 'pgsql') $char = '"';
        return $char . str_replace($char, $char . $char, $name) . $char;
    }

    protected function intval($var)
    {
        if (PHP_INT_SIZE == 8) return intval($var);
        return floor(doubleval($var));
    }

    protected function nextQueryParam($query)
    {
        $keys = array_keys($this->paramsMap());

        $first_position = PHP_INT_MAX;
        $first_param = null;
        $first_type = null;
        $arg = null;
        $named_arg = null;
        foreach ($keys as $key) {
            $fullkey = $this->param_char . $key;
            $pos = strpos($query, $fullkey);
            if ($pos === false) continue;

            if ($pos <= $first_position) {
                $first_position = $pos;
                $first_param = $fullkey;
                $first_type = $key;
            }
        }

        if (is_null($first_param)) return;

        $first_position_end = $first_position + strlen($first_param);
        $named_seperator_length = strlen($this->named_param_seperator);
        $arg_mask = '0123456789';
        $named_arg_mask = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

        if ($arg_number_length = strspn($query, $arg_mask, $first_position_end)) {
            $arg = intval(substr($query, $first_position_end, $arg_number_length));
            $first_param = substr($query, $first_position, strlen($first_param) + $arg_number_length);
        } else if (substr($query, $first_position_end, $named_seperator_length) == $this->named_param_seperator) {
            $named_arg_length = strspn($query, $named_arg_mask, $first_position_end + $named_seperator_length);

            if ($named_arg_length > 0) {
                $named_arg = substr($query, $first_position_end + $named_seperator_length, $named_arg_length);
                $first_param = substr($query, $first_position, strlen($first_param) + $named_seperator_length + $named_arg_length);
            }
        }

        return array(
            'param' => $first_param,
            'type' => $first_type,
            'pos' => $first_position,
            'arg' => $arg,
            'named_arg' => $named_arg,
            'val' => '',
        );
    }

    protected function preParse($query, $args)
    {
        $arg_ct = 0;
        $max_numbered_arg = 0;
        $use_numbered_args = false;
        $use_named_args = false;

        $queryParts = array();
        while ($Param = $this->nextQueryParam($query)) {
            if ($Param['pos'] > 0) {
                $queryParts[] = substr($query, 0, $Param['pos']);
            }

            if ($Param['type'] != $this->param_char && is_null($Param['arg']) && is_null($Param['named_arg'])) {
                $Param['arg'] = $arg_ct++;
            }

            if (! is_null($Param['arg'])) {
                $use_numbered_args = true;
                $max_numbered_arg = max($max_numbered_arg, $Param['arg']);
            }
            if (! is_null($Param['named_arg'])) {
                $use_named_args = true;
            }

            $queryParts[] = $Param;
            $query = substr($query, $Param['pos'] + strlen($Param['param']));
        }

        if (strlen($query) > 0) {
            $queryParts[] = $query;
        }

        if ($use_named_args) {
            if ($use_numbered_args) {
                throw new MySQLException("You can't mix named and numbered args!");
            }

            if (count($args) != 1 || !is_array($args[0])) {
                throw new MySQLException("If you use named args, you must pass an assoc array of args!");
            }
        }

        if ($use_numbered_args) {
            if ($max_numbered_arg + 1 != count($args)) {
                throw new MySQLException(sprintf('Expected %d args, but got %d!', $max_numbered_arg + 1, count($args)));
            }
        }

        foreach ($queryParts as &$Part) {
            if (is_string($Part)) continue;

            if (!is_null($Part['named_arg'])) {
                $key = $Part['named_arg'];
                if (! array_key_exists($key, $args[0])) {
                    throw new MySQLException("Couldn't find named arg {$key}!");
                }

                $Part['val'] = $args[0][$key];
            } else if (!is_null($Part['arg'])) {
                $key = $Part['arg'];
                $Part['val'] = $args[$key];
            }
        }

        return $queryParts;
    }

    function parse($query)
    {
        $args = func_get_args();
        array_shift($args);

        $ParsedQuery = new MySQLParsedQuery();
        if (! $args) {
            $ParsedQuery->add($query);
            return $ParsedQuery;
        }

        $Map = $this->paramsMap();
        $array_types = $this->paramsMapArrayTypes();
        $opt_array_types = $this->paramsMapOptArrayTypes();
        foreach ($this->preParse($query, $args) as $Part) {
            if (is_string($Part)) {
                $ParsedQuery->add($Part);
                continue;
            }

            $fn = $Map[$Part['type']];
            $is_array_type = in_array($Part['type'], $array_types, true);
            $is_opt_array_type = in_array($Part['type'], $opt_array_types, true);

            $key = is_null($Part['named_arg']) ? $Part['arg'] : $Part['named_arg'];
            $val = $Part['val'];

            if ($is_array_type && !is_array($val)) {
                throw new MySQLException("Expected an array for arg $key but didn't get one!");
            }
            if (!$is_array_type && !$is_opt_array_type && is_array($val)) {
                $val = '';
            }

            if ($val instanceof WhereClause) {
                if ($Part['type'] != 'l' && $Part['type'] != '?') {
                    throw new MySQLException("WhereClause must be used with l or ?, you used {$Part['type']} instead!");
                }

                list($clause_sql, $clause_args) = $val->textAndArgs();
                $ParsedSubQuery = $this->parse($clause_sql, ...$clause_args);
                $ParsedQuery->add($ParsedSubQuery);
            } else if ($val instanceof MySQLParsedQuery) {
                if ($Part['type'] != 'l' && $Part['type'] != '?') {
                    throw new MySQLException("a ParsedQuery must be used with l or ?, you used {$Part['type']} instead!");
                }

                $ParsedQuery->add($val);
            } else {
                $ParsedSubQuery = $fn($val);
                if (! ($ParsedSubQuery instanceof MySQLParsedQuery)) {
                    throw new MySQLException("Unable to parse query");
                }

                $ParsedQuery->add($ParsedSubQuery);
            }
        }

        return $ParsedQuery;
    }

    protected function _query()
    {
        $param_char = $this->param_char;
        $this->param_char = ':';

        $args = func_get_args();
        $func_name = array_shift($args);

        try {
            return $this->queryHelper(array('assoc' => true, 'name' => $func_name), $args);
        } finally {
            $this->param_char = $param_char;
        }
    }
    protected function _parse()
    {
        $param_char = $this->param_char;
        $this->param_char = ':';

        try {
            return $this->parse(...func_get_args());
        } finally {
            $this->param_char = $param_char;
        }
    }

    protected function queryHelper($opts, $args)
    {
        if (!isset($opts['name'])) {
            throw new MySQLException("queryHelper() must get source function name");
        }
        $opts_fullcols = (isset($opts['fullcols']) && $opts['fullcols']);
        $opts_raw = (isset($opts['raw']) && $opts['raw']);
        $opts_unbuf = (isset($opts['unbuf']) && $opts['unbuf']);
        $opts_assoc = (isset($opts['assoc']) && $opts['assoc']);
        $opts_walk = (isset($opts['walk']) && $opts['walk']);
        $func_name = $opts['name'];
        $is_buffered = !($opts_unbuf || $opts_walk);

        if ($this->reconnect_after > 0 && time() - $this->last_query_at >= $this->reconnect_after) {
            $this->disconnect();
        }

        $query = array_shift($args);
        if ($query instanceof MySQLParsedQuery) {
            $ParsedQuery = $query;
        } else {
            $ParsedQuery = $this->parse($query, ...$args);
        }
        $query = $ParsedQuery->query;
        $params = $ParsedQuery->params;

        list($query, $params) = $this->runHook('pre_run', array(
            'query' => $query,
            'params' => $params,
            'func_name' => $func_name,
        ));
        $query = trim($query);
        $this->last_query = $query;
        $this->last_query_at = time();

        $starttime = microtime(true);
        $pdo = $this->get();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $db_type = $this->dbType();
        if ($db_type == 'mysql') {
            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $is_buffered);
        } else if ($db_type == 'pgsql') {
            $pdo->setAttribute(\PDO::PGSQL_ATTR_DISABLE_PREPARES, 1);
        }

        $result = $Exception = null;
        try {
            if ($params) {
                $result = $pdo->prepare($query);
                foreach ($params as $i => $param) {
                    if (is_int($param)) {
                        $data_type = \PDO::PARAM_INT;
                    } else if (is_string($param) && $db_type == 'pgsql' && !mb_check_encoding($param)) {
                        $data_type = \PDO::PARAM_LOB;
                    } else {
                        $data_type = \PDO::PARAM_STR;
                    }
                    $result->bindValue($i + 1, $param, $data_type);
                }

                $result->execute();
            } else {
                $result = $pdo->query($query);
            }
        } catch (\PDOException $e) {
            $Exception = new MySQLException(
                $e->getMessage(),
                $query,
                $params,
                $e->getCode()
            );
        }

        $runtime = microtime(true) - $starttime;
        $runtime = sprintf('%f', $runtime * 1000);

        $got_result_set = ($result && $result->columnCount() > 0);
        if ($result && !$got_result_set) $this->affected_rows = $result->rowCount();
        else $this->affected_rows = false;

        $hookHash = array(
            'query' => $query,
            'params' => $params,
            'runtime' => $runtime,
            'func_name' => $func_name,
            'exception' => null,
            'error' => null,
            'rows' => null,
            'affected' => null
        );
        if ($Exception) {
            $hookHash['exception'] = $Exception;
            $hookHash['error'] = $Exception->getMessage();
        } else {
            $hookHash['affected'] = $this->affected_rows;
        }

        $return = false;
        $skip_result_fetch = ($opts_walk || $opts_raw);

        if (!$skip_result_fetch && $got_result_set) {
            $return = array();

            $infos = null;
            if ($opts_fullcols) {
                $infos = array();
                for ($i = 0; $i < $result->columnCount(); $i++) {
                    $info = $result->getColumnMeta($i);
                    if (isset($info['table']) && strlen($info['table'])) {
                        $infos[$i] = $info['table'] . '.' . $info['name'];
                    } else {
                        $infos[$i] = $info['name'];
                    }
                }
            }

            while ($row = $result->fetch($opts_assoc ? \PDO::FETCH_ASSOC : \PDO::FETCH_NUM)) {
                if ($infos) $row = array_combine($infos, $row);
                $return[] = $row;
            }
        }

        if (is_array($return)) {
            $hookHash['rows'] = count($return);
        }

        $this->defaultRunHook($hookHash);
        $this->runHook('post_run', $hookHash);
        if ($Exception) {
            if ($this->runHook('run_failed', $hookHash) !== false) {
                throw $Exception;
            }
        } else {
            $this->runHook('run_success', $hookHash);
        }

        if ($opts_walk) return new MySQLWalk($result);
        else if ($opts_raw) return $result;
        else if ($result) $result->closeCursor();

        if (is_array($return)) return $return;
        return $this->affected_rows;
    }

    // *************** HOOKS
    function addHook($type, $fn)
    {
        if (! array_key_exists($type, $this->hooks)) {
            throw new MySQLException("Hook type $type is not recognized");
        }

        if (! is_callable($fn)) {
            throw new MySQLException("Second arg to addHook() must be callable");
        }

        $this->hooks[$type][] = $fn;
        end($this->hooks[$type]);
        return key($this->hooks[$type]);
    }

    function removeHook($type, $index)
    {
        if (! array_key_exists($type, $this->hooks)) {
            throw new MySQLException("Hook type $type is not recognized");
        }

        if (! array_key_exists($index, $this->hooks[$type])) {
            throw new MySQLException("That hook does not exist");
        }

        unset($this->hooks[$type][$index]);
    }

    function removeHooks($type)
    {
        if (! array_key_exists($type, $this->hooks)) {
            throw new MySQLException("Hook type $type is not recognized");
        }

        $this->hooks[$type] = array();
    }

    protected function runHook($type, $args = array())
    {
        if (! array_key_exists($type, $this->hooks)) {
            throw new MySQLException("Hook type $type is not recognized");
        }

        if ($type == 'pre_run') {
            foreach ($this->hooks[$type] as $hook) {
                $result = call_user_func($hook, $args);
                if (is_string($result)) {
                    $args['query'] = $result;
                } else if (is_array($result) && count($result) == 2) {
                    list($args['query'], $args['params']) = $result;
                } else if (!is_null($result)) {
                    throw new MySQLException("pre_run hook must return a query string or [query, params] array");
                }
            }

            return array($args['query'], $args['params']);
        } else if ($type == 'post_run') {

            foreach ($this->hooks[$type] as $hook) {
                call_user_func($hook, $args);
            }
        } else if ($type == 'run_success') {

            foreach ($this->hooks[$type] as $hook) {
                call_user_func($hook, $args);
            }
        } else if ($type == 'run_failed') {

            foreach ($this->hooks[$type] as $hook) {
                $result = call_user_func($hook, $args);
                if ($result === false) return false;
            }
        } else {
            throw new MySQLException("runHook() type $type not recognized");
        }
    }

    protected function defaultRunHook($args)
    {
        if (! $this->logfile) return;

        $query = $args['query'];
        $query = preg_replace('/\s+/', ' ', $query);

        $results[] = sprintf('[%s]', date('Y-m-d H:i:s'));
        $results[] = sprintf('QUERY: %s', $query);

        if ($params = $args['params']) {
            $results[] = sprintf('PARAMS: %s', implode(', ', $params));
        }

        $results[] = sprintf('RUNTIME: %s ms', $args['runtime']);

        if ($args['affected']) {
            $results[] = sprintf('AFFECTED ROWS: %s', $args['affected']);
        }
        if ($args['rows']) {
            $results[] = sprintf('RETURNED ROWS: %s', $args['rows']);
        }
        if ($args['error']) {
            $results[] = 'ERROR: ' . $args['error'];
        }

        $results = implode("\n", $results) . "\n\n";

        if (is_resource($this->logfile)) {
            fwrite($this->logfile, $results);
        } else {
            file_put_contents($this->logfile, $results, FILE_APPEND);
        }
    }

    // *************** DEPRECATED METHODS
    /**
     * @deprecated
     */
    public function debugMode($enable = true)
    {
        if ($enable) $this->logfile = fopen('php://output', 'w');
        else $this->logfile = null;
    }

    /**
     * @deprecated
     */
    public function queryRaw()
    {
        return $this->queryHelper(array('raw' => true, 'name' => 'queryRaw'), func_get_args());
    }

    /**
     * @deprecated
     */
    public function queryRawUnbuf()
    {
        return $this->queryHelper(array('raw' => true, 'unbuf' => true, 'name' => 'queryRawUnbuf'), func_get_args());
    }
}

class MySQLWalk
{
    protected $result;

    function __construct(\PDOStatement $result)
    {
        $this->result = $result;
    }

    function next()
    {
        if (!$this->result) return;
        $row = $this->result->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) $this->free();
        return $row;
    }

    function free()
    {
        if (!$this->result) return;
        $this->result->closeCursor();
        $this->result = null;
    }

    function __destruct()
    {
        $this->free();
    }
}

class WhereClause implements \Countable
{
    public $type = 'and'; //AND or OR
    public $negate = false;
    public $clauses = array();

    function __construct($type)
    {
        $type = strtolower($type);
        if ($type !== 'or' && $type !== 'and') throw new MySQLException('you must use either WhereClause(and) or WhereClause(or)');
        $this->type = $type;
    }

    function add()
    {
        $args = func_get_args();
        $sql = array_shift($args);

        if ($sql instanceof WhereClause) {
            $this->clauses[] = $sql;
        } else if ($sql instanceof MySQLParsedQuery) {
            $this->clauses[] = array('sql' => '%?', 'args' => [$sql]);
        } else {
            $this->clauses[] = array('sql' => $sql, 'args' => $args);
        }
    }

    function negateLast()
    {
        $i = count($this->clauses) - 1;
        if (!isset($this->clauses[$i])) return;

        if ($this->clauses[$i] instanceof WhereClause) {
            $this->clauses[$i]->negate();
        } else {
            $this->clauses[$i]['sql'] = 'NOT (' . $this->clauses[$i]['sql'] . ')';
        }
    }

    function negate()
    {
        $this->negate = ! $this->negate;
    }

    function addClause($type)
    {
        $r = new WhereClause($type);
        $this->add($r);
        return $r;
    }

    #[\ReturnTypeWillChange]
    function count()
    {
        return count($this->clauses);
    }

    function textAndArgs()
    {
        $sql = array();
        $args = array();

        if (count($this) == 0) return array('(1=1)', $args);

        foreach ($this->clauses as $clause) {
            if ($clause instanceof WhereClause) {
                list($clause_sql, $clause_args) = $clause->textAndArgs();
            } else {
                $clause_sql = $clause['sql'];
                $clause_args = $clause['args'];
            }

            $sql[] = "($clause_sql)";
            $args = array_merge($args, $clause_args);
        }

        if (count($this) == 1) $sql = $sql[0];
        else if ($this->type == 'and') $sql = sprintf('(%s)', implode(' AND ', $sql));
        else $sql = sprintf('(%s)', implode(' OR ', $sql));

        if ($this->negate) $sql = '(NOT ' . $sql . ')';
        return array($sql, $args);
    }
}

class DBTransaction
{
    private $committed = false;

    function __construct()
    {
        DB::startTransaction();
    }
    function __destruct()
    {
        if (! $this->committed) DB::rollback();
    }
    function commit()
    {
        DB::commit();
        $this->committed = true;
    }
}

class MySQLException extends \Exception
{
    protected $query = '';
    protected $params = array();

    function __construct($message = '', $query = '', $params = array(), $code = 0)
    {
        parent::__construct($message);
        $this->query = $query;
        $this->params = $params;
        $this->code = $code;
    }

    public function getQuery()
    {
        return $this->query;
    }
    public function getParams()
    {
        return $this->params;
    }
}

class MySQLParsedQuery
{
    public $query = '';
    public $params = array();

    function __construct($query = '', $params = array())
    {
        $this->query = $query;
        $this->params = $params;
    }

    function add($query, $params = array())
    {
        if ($query instanceof MySQLParsedQuery) {
            return $this->add($query->query, $query->params);
        }

        $this->query .= $query;
        $this->params = array_merge($this->params, array_values($params));
    }

    function toArray()
    {
        return array_merge(array($this->query), $this->params);
    }
};


#[\AllowDynamicProperties]
abstract class Model
{
    // INTERNAL -- DO NOT TOUCH
    private $_orm_row = []; // processed hash
    private $_orm_row_orig = []; // original raw hash from database
    private $_orm_assoc_load = [];
    private $_orm_is_fresh = true;
    private static $_orm_struct = [];

    // (OPTIONAL) SET IN INHERITING CLASS
    protected static $table = null;
    protected static $assocations = [];
    protected static $columns = [];
    public $_use_transactions = null;

    // -------------- SIMPLE HELPER FUNCTIONS
    private static function _orm_struct()
    {
        $table_name = static::table();
        if (! array_key_exists($table_name, self::$_orm_struct)) {
            self::$_orm_struct[$table_name] = new ModelTable(get_called_class());
        }
        return self::$_orm_struct[$table_name];
    }

    public static function _orm_struct_reset()
    {
        self::$_orm_struct = [];
    }

    public static function table()
    {
        if (static::$table) return static::$table;

        $table = strtolower(get_called_class());
        $last_char = substr($table, strlen($table) - 1, 1);
        if ($last_char != 's') $table .= 's';
        return $table;
    }

    public static function db()
    {
        return DB::getMDB();
    }

    // use for internal queries, since we don't know what the user's param_char is
    public static function _orm_query($func_name, ...$args)
    {
        $mdb = static::db();
        $old_char = $mdb->param_char;
        $mdb->param_char = ':';
        try {
            return $mdb->$func_name(...$args);
        } finally {
            $mdb->param_char = $old_char;
        }
    }

    private function _tr_enabled()
    {
        if (is_bool($this->_use_transactions)) {
            return $this->_use_transactions;
        }
        return static::db()->nested_transactions;
    }
    private function _tr_start()
    {
        if (! $this->_tr_enabled()) return;
        static::db()->startTransaction();
    }
    private function _tr_commit()
    {
        if (! $this->_tr_enabled()) return;
        static::db()->commit();
    }
    private function _tr_rollback()
    {
        if (! $this->_tr_enabled()) return;
        static::db()->rollback();
    }

    public function dirtyhash()
    {
        $hash = [];
        foreach ($this->toRawHash() as $key => $value) {
            if (!array_key_exists($key, $this->_orm_row_orig) || $value !== $this->_orm_row_orig[$key]) {
                $hash[$key] = $value;
            }
        }

        return $hash;
    }

    public function dirtyfields()
    {
        return array_keys($this->dirtyhash());
    }

    private function _dirtyhash($fields)
    {
        if (! $fields) return $this->dirtyhash();
        return array_intersect_key($this->dirtyhash(), array_flip($fields));
    }
    private function _dirtyfields($fields)
    {
        return array_keys($this->_dirtyhash($fields));
    }

    protected function _whereHash()
    {
        $hash = [];
        $primary_keys = static::_orm_struct()->primary_keys();
        if (! $primary_keys) {
            throw new MySQLModelException("$this has no primary keys");
        }
        foreach ($primary_keys as $key) {
            $hash[$key] = $this->getraw($key);
        }
        return $hash;
    }

    private function _orm_run_callback($func_name, ...$args)
    {
        if (method_exists($this, $func_name)) {
            $result = $this->$func_name(...$args);
            if ($result === false) {
                throw new MySQLModelException("{$func_name} returned false");
            }
            return $result;
        }
    }


    public function isFresh()
    {
        return $this->_orm_is_fresh;
    }

    // -------------- GET/SET AND MARSHAL / UNMARSHAL
    public function __set($key, $value)
    {
        if (!$this->isFresh() && static::_orm_struct()->is_primary_key($key)) {
            throw new MySQLModelException("Can't update primary key!");
        } else if ($this->has($key)) {
            $this->set($key, $value);
        } else {
            $this->$key = $value;
        }
    }

    // return by ref on __get() lets $Obj->var[] = 'array_element' work properly
    public function &__get($key)
    {
        // return by reference requires temp var
        if (static::is_assoc($key)) {
            $result = $this->assoc($key);
            return $result;
        }
        if ($this->has($key)) {
            return $this->get($key);
        }

        return $this->$key;
    }

    public function has($key)
    {
        return !! static::_orm_coltype($key);
    }

    // return by ref on __get() lets $Obj->var[] = 'array_element' work properly
    public function &get($key)
    {
        if (! $this->has($key)) {
            throw new MySQLModelException("$this does not have key $key");
        }
        if (! array_key_exists($key, $this->_orm_row)) {
            // only variables can be returned by reference
            $null = null;
            return $null;
        }

        return $this->_orm_row[$key];
    }

    public function getraw($key)
    {
        if (! $this->has($key)) {
            throw new MySQLModelException("$this does not have key $key");
        }
        $value = $this->_orm_row[$key] ?? null;
        return $this->_marshal($key, $value);
    }

    public function set($key, $value)
    {
        if (! $this->has($key)) {
            throw new MySQLModelException("$this does not have key $key");
        }
        $this->_orm_row[$key] = $value;
    }

    public function setraw($key, $value)
    {
        if (! $this->has($key)) {
            throw new MySQLModelException("$this does not have key $key");
        }
        $this->_orm_row[$key] = $this->_unmarshal($key, $value);
    }

    public function _marshal($key, $value)
    {
        $type = static::_orm_coltype($key);
        $is_nullable = static::_orm_struct()->column_nullable($key);

        $fieldmarshal = "_marshal_field_{$key}";
        $typemarshal = "_marshal_type_{$type}";
        if (method_exists($this, $fieldmarshal)) {
            $value = $this->$fieldmarshal($key, $value, $is_nullable);
        } else if (method_exists($this, $typemarshal)) {
            $value = $this->$typemarshal($key, $value, $is_nullable);
        }

        return $value;
    }

    public function _unmarshal($key, $value)
    {
        $type = static::_orm_coltype($key);
        $is_nullable = static::_orm_struct()->column_nullable($key);

        $fieldmarshal = "_unmarshal_field_{$key}";
        $typemarshal = "_unmarshal_type_{$type}";
        if (method_exists($this, $fieldmarshal)) {
            $value = $this->$fieldmarshal($key, $value, $is_nullable);
        } else if (method_exists($this, $typemarshal)) {
            $value = $this->$typemarshal($key, $value, $is_nullable);
        }

        return $value;
    }

    public function _marshal_type_bool($key, $value, $is_nullable)
    {
        if ($is_nullable && is_null($value)) return null;
        return $value ? 1 : 0;
    }
    public function _unmarshal_type_bool($key, $value)
    {
        if (is_null($value)) return null;
        return !!$value;
    }

    public function _marshal_type_int($key, $value, $is_nullable)
    {
        if ($is_nullable && is_null($value)) return null;
        return intval($value);
    }
    public function _unmarshal_type_int($key, $value)
    {
        if (is_null($value)) return null;
        return intval($value);
    }

    public function _marshal_type_double($key, $value, $is_nullable)
    {
        if ($is_nullable && is_null($value)) return null;
        return doubleval($value);
    }
    public function _unmarshal_type_double($key, $value)
    {
        if (is_null($value)) return null;
        return doubleval($value);
    }

    public function _marshal_type_datetime($key, $value, $is_nullable)
    {
        // 0000-00-00 00:00:00 is technically not a valid date, and pgsql rejects it
        // we can't use 1970-01-01 00:00:00 because, depending on the local TIMESTAMP, it might
        // become a negative unixtime
        if (!$is_nullable && is_null($value)) $value = '1970-01-03 00:00:00';
        if ($value instanceof \DateTime) return $value->format('Y-m-d H:i:s');
        return $value;
    }
    public function _unmarshal_type_datetime($key, $value)
    {
        if (is_null($value)) return null;
        if ($value) return \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $value;
    }

    public function _marshal_type_string($key, $value, $is_nullable)
    {
        if ($is_nullable && is_null($value)) return null;
        return strval($value);
    }
    public function _unmarshal_type_string($key, $value)
    {
        if (is_null($value)) return null;
        return strval($value);
    }

    public function _marshal_type_json($key, $value, $is_nullable)
    {
        return json_encode($value);
    }
    public function _unmarshal_type_json($key, $value)
    {
        if (is_null($value)) return null;
        return json_decode($value, true);
    }

    private static function _orm_colinfo($column, $type)
    {
        if (! is_array(static::$columns)) return;
        if (! array_key_exists($column, static::$columns)) return;

        $info = static::$columns[$column];
        return $info[$type] ?? null;
    }

    private static function _orm_coltype($column)
    {
        if ($type = static::_orm_colinfo($column, 'type')) {
            return $type;
        }
        return static::_orm_struct()->column_type($column);
    }

    // -------------- ASSOCIATIONS
    public static function is_assoc($name)
    {
        return !! static::_orm_assoc($name);
    }
    private static function _orm_assoc($name)
    {
        if (! array_key_exists($name, static::$assocations)) return null;
        $assoc = static::$assocations[$name];

        if (! isset($assoc['foreign_key'])) {
            throw new MySQLModelException("assocation must have foreign_key");
        }

        $assoc['class_name'] = $assoc['class_name'] ?? $name;
        return $assoc;
    }

    public function assoc($name)
    {
        if (! static::is_assoc($name)) return null;
        if (! isset($this->_orm_assoc_load[$name])) {
            $this->_orm_assoc_load[$name] = $this->_load_assoc($name);
        }

        return $this->_orm_assoc_load[$name];
    }

    private function _load_assoc($name)
    {
        $assoc = static::_orm_assoc($name);
        if (! $assoc) {
            throw new MySQLModelException("Unknown assocation: $name");
        }

        $class_name = $assoc['class_name'];
        $foreign_key = $assoc['foreign_key'];
        $primary_key = $class_name::_orm_struct()->primary_key();
        $primary_value = $this->getraw($primary_key);

        if (! is_subclass_of($class_name, __CLASS__)) {
            throw new MySQLModelException(sprintf('%s is not a class that inherits from %s', $class_name, get_class()));
        }

        if ($assoc['type'] == 'belongs_to') {
            return $class_name::Load($this->$foreign_key);
        } else if ($assoc['type'] == 'has_one') {
            return $class_name::Search([
                $assoc['foreign_key'] => $primary_value,
            ]);
        } else if ($assoc['type'] == 'has_many') {
            return $class_name::Where([$assoc['foreign_key'] => $primary_value]);
        } else {
            throw new \Exception("Invalid type for $name association");
        }
    }

    // -------------- CONSTRUCTORS
    private function _load_hash(array $row)
    {
        $this->_orm_is_fresh = false;
        $this->_orm_row_orig = [];
        $this->_orm_row = [];
        $this->_orm_assoc_load = [];
        foreach ($row as $key => $value) {
            if ($this->has($key)) {
                $this->_orm_row[$key] = $this->_unmarshal($key, $value);
                $this->_orm_row_orig[$key] = $this->_marshal($key, $this->_orm_row[$key]);
            } else {
                $this->$key = $value;
            }
        }
    }

    public static function LoadFromHash(array $row = [])
    {
        $class_name = get_called_class();
        $Obj = new $class_name();
        $Obj->_load_hash($row);
        return $Obj;
    }

    public static function Load(...$values)
    {
        $keys = static::_orm_struct()->primary_keys();
        if (count($values) != count($keys)) {
            throw new \Exception(sprintf(
                "Load on %s must be called with %d parameters!",
                get_called_class(),
                count($keys)
            ));
        }

        return static::Search(array_combine($keys, $values));
    }

    private static function _Search($many, $query, ...$args)
    {
        // infer the table structure first in case we run FOUND_ROWS()
        static::_orm_struct();

        if (is_array($query)) {
            $table = static::table();
            $limiter = $many ? '' : 'LIMIT 1';

            if ($query) {
                $rows = static::_orm_query('query', 'SELECT * FROM :b WHERE :ha :l', $table, $query, $limiter);
            } else {
                $rows = static::_orm_query('query', 'SELECT * FROM :b :l', $table, $limiter);
            }
        } else {
            $rows = static::db()->query($query, ...$args);
        }

        if (! $rows) {
            return $many ? [] : null;
        }

        $rows = array_map(function ($row) {
            return static::LoadFromHash($row);
        }, $rows);

        return $many ? $rows : $rows[0];
    }

    public static function Search($query = [], ...$args)
    {
        return static::_Search(false, $query, ...$args);
    }

    public static function SearchMany($query = [], ...$args)
    {
        return static::_Search(true, $query, ...$args);
    }

    public static function _scopes()
    {
        return [];
    }

    public static function _orm_runscope($scope, ...$args)
    {
        $scopes = static::_scopes();
        if (! is_array($scopes)) {
            throw new MySQLModelException("No scopes available");
        }
        if (! array_key_exists($scope, $scopes)) {
            throw new MySQLModelException("Scope not available: $scope");
        }

        $scope = $scopes[$scope];
        if (! is_callable($scope)) {
            throw new MySQLModelException("Invalid scope: must be anonymous function");
        }

        $Scope = $scope(...$args);
        if (! ($Scope instanceof ModelScope)) {
            throw new MySQLModelException("Invalid scope: must use ClassName::Where()");
        }
        return $Scope;
    }

    public static function all()
    {
        return new ModelScope(get_called_class());
    }

    public static function where(...$args)
    {
        $Scope = new ModelScope(get_called_class());
        $Scope->where(...$args);
        return $Scope;
    }

    public static function scope(...$scopes)
    {
        $Scope = new ModelScope(get_called_class());
        $Scope->scope(...$scopes);
        return $Scope;
    }

    public function save($run_callbacks = null)
    {
        return $this->_save(null, $run_callbacks);
    }

    // if $savefields is set, only those fields will be saved
    private function _save($savefields, $run_callbacks = null)
    {
        if (! is_bool($run_callbacks)) $run_callbacks = true;

        $is_fresh = $this->isFresh();
        $have_committed = false;
        $table = static::table();
        $mdb = static::db();

        $this->_tr_start();
        try {
            if ($run_callbacks) {
                $fields = $this->_dirtyfields($savefields);
                foreach ($fields as $field) {
                    $this->_orm_run_callback("_validate_{$field}", $this->get($field));
                }

                $this->_orm_run_callback('_pre_save', $fields);
                if ($is_fresh) $this->_orm_run_callback('_pre_create', $fields);
                else $this->_orm_run_callback('_pre_update', $fields);
            }

            // dirty fields list might change while running the _pre callbacks
            $replace = $this->_dirtyhash($savefields);
            $fields = array_keys($replace);

            if ($is_fresh) {
                $mdb->insert($table, $replace);
                $this->_orm_is_fresh = false;

                // for reload() to work below, we need to know what our auto-increment value is
                if ($aifield = static::_orm_struct()->ai_field()) {
                    $this->set($aifield, $mdb->insertId());
                }
            } else if (count($replace) > 0) {
                $mdb->update($table, $replace, $this->_whereHash());
            }

            // don't reload if we did a partial save only
            if ($savefields) {
                $this->_orm_row_orig = array_merge($this->_orm_row_orig, $replace);
            } else {
                $this->reload();
            }

            if ($run_callbacks) {
                if ($is_fresh) $this->_orm_run_callback('_post_create', $fields);
                else $this->_orm_run_callback('_post_update', $fields);
                $this->_orm_run_callback('_post_save', $fields);
            }
            $this->_tr_commit();
            $have_committed = true;
        } finally {
            if (! $have_committed) $this->_tr_rollback();
        }

        if ($run_callbacks) {
            $this->_orm_run_callback('_post_commit', $fields);
        }
    }

    public function reload($lock = false)
    {
        if ($this->isFresh()) {
            throw new MySQLModelException("Can't reload unsaved record!");
        }

        $table = static::table();
        $row = static::_orm_query(
            'queryFirstRow',
            'SELECT * FROM :b WHERE :ha LIMIT 1 :l',
            $table,
            $this->_whereHash(),
            $lock ? 'FOR UPDATE' : ''
        );

        if (! $row) {
            throw new MySQLModelException("Unable to reload(): missing row");
        }
        $this->_load_hash($row);
    }

    public function lock()
    {
        $this->reload(true);
    }

    public function update($one, $two = null)
    {
        if ($this->isFresh()) {
            throw new MySQLModelException("Unable to update(): record is fresh");
        }
        if (is_array($one)) $hash = $one;
        else $hash = [$one => $two];

        foreach ($hash as $key => $value) {
            $this->set($key, $value);
        }
        \print_r($hash);
        return $this->_save(array_keys($hash));
    }

    public function destroy()
    {
        $this->_tr_start();
        $have_committed = false;

        try {
            $this->_orm_run_callback('_pre_destroy');
            static::_orm_query('query', 'DELETE FROM :b WHERE :ha', static::table(), $this->_whereHash());
            $this->_orm_run_callback('_post_destroy');
            $this->_tr_commit();
            $have_committed = true;
        } finally {
            if (! $have_committed) $this->_tr_rollback();
        }
    }

    public function toHash()
    {
        return $this->_orm_row;
    }

    public function toRawHash()
    {
        $hash = [];
        foreach ($this->_orm_row as $key => $value) {
            $hash[$key] = $this->_marshal($key, $value);
        }
        return $hash;
    }

    public function __toString()
    {
        return get_called_class();
    }
}

class ModelTable
{
    protected $struct = [];
    protected $table_name;
    protected $class_name;

    function __construct($class_name)
    {
        $this->class_name = $class_name;
        $this->table_name = $class_name::table();
        $this->struct = $this->table_struct();
    }

    function primary_keys()
    {
        return array_keys(array_filter(
            $this->struct,
            function ($x) {
                return $x->is_primary;
            }
        ));
    }

    function primary_key()
    {
        return count($this->primary_keys()) == 1 ? $this->primary_keys()[0] : null;
    }

    function is_primary_key($key)
    {
        return in_array($key, $this->primary_keys());
    }

    function ai_field()
    {
        $names = array_keys(array_filter($this->struct, function ($x) {
            return $x->is_autoincrement;
        }));
        return $names ? $names[0] : null;
    }

    function column_type($column)
    {
        if (! $this->has($column)) return;
        return $this->struct[$column]->simpletype;
    }

    function column_nullable($column)
    {
        if (! $this->has($column)) return;
        return $this->struct[$column]->is_nullable;
    }

    function has($column)
    {
        return array_key_exists($column, $this->struct);
    }

    function mdb()
    {
        return $this->class_name::db();
    }

    function query(...$args)
    {
        return $this->class_name::_orm_query(...$args);
    }

    protected function table_struct()
    {
        $db_type = $this->mdb()->dbType();
        $data = $this->mdb()->columnList($this->table_name);

        if ($db_type == 'mysql') return $this->table_struct_mysql($data);
        else if ($db_type == 'sqlite') return $this->table_struct_sqlite($data);
        else if ($db_type == 'pgsql') return $this->table_struct_pgsql($data);
        else throw new MySQLModelException("Unsupported database type: {$db_type}");
    }

    protected function table_struct_mysql($data)
    {
        $struct = [];
        foreach ($data as $name => $hash) {
            $Column = new ModelColumn();
            $Column->name = $name;
            $Column->is_nullable = ($hash['null'] == 'YES');
            $Column->is_primary = ($hash['key'] == 'PRI');
            $Column->is_autoincrement = (($hash['extra'] ?? '') == 'auto_increment');
            $Column->type = $hash['type'];
            $Column->simpletype = $this->table_struct_simpletype($hash['type']);
            $struct[$name] = $Column;
        }

        return $struct;
    }

    protected function table_struct_sqlite($data)
    {
        $struct = [];

        $has_autoincrement = $this->query('queryFirstField', 'SELECT COUNT(*) FROM sqlite_master 
      WHERE tbl_name=:s AND sql LIKE "%AUTOINCREMENT%"', $this->table_name);

        foreach ($data as $name => $hash) {
            $Column = new ModelColumn();
            $Column->name = $name;
            $Column->is_nullable = ($hash['notnull'] == 0);
            $Column->is_primary = ($hash['pk'] > 0);
            $Column->type = $hash['type'];
            $Column->simpletype = $this->table_struct_simpletype($hash['type']);
            $Column->is_autoincrement = ($Column->is_primary && $has_autoincrement);
            $struct[$name] = $Column;
        }
        return $struct;
    }

    protected function table_struct_pgsql($data)
    {
        $struct = [];
        foreach ($data as $name => $hash) {
            $Column = new ModelColumn();
            $Column->name = $name;
            $Column->is_nullable = ($hash['is_nullable'] == 'YES');
            $Column->type = $hash['data_type'];
            $Column->simpletype = $this->table_struct_simpletype($Column->type);
            $Column->is_autoincrement = ($hash['column_default'] && substr($hash['column_default'], 0, 8) == 'nextval(');
            $Column->is_primary = false;
            $struct[$name] = $Column;
        }

        $primary_keys = $this->query('queryFirstColumn', "
      SELECT kcu.column_name
      FROM information_schema.table_constraints tc
      JOIN 
        information_schema.key_column_usage kcu
        ON tc.constraint_name = kcu.constraint_name
        AND tc.table_schema = kcu.table_schema
      WHERE
        tc.constraint_type = 'PRIMARY KEY'
        AND tc.table_name = :s
        AND tc.table_schema = 'public';
    ", $this->table_name);

        foreach ($primary_keys as $primary_key) {
            $struct[$primary_key]->is_primary = true;
        }

        return $struct;
    }

    protected function table_struct_simpletype($type)
    {
        static $typemap = [
            // mysql
            'tinyint' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'int' => 'int',
            'bigint' => 'int',
            'float' => 'double',
            'double' => 'double',
            'decimal' => 'double',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',

            // sqlite, pgsql
            'integer' => 'int',
        ];

        $type = strtolower($type);
        $parts = preg_split('/\W+/', $type, -1, PREG_SPLIT_NO_EMPTY);
        return $typemap[$parts[0]] ?? 'string';
    }
}

class ModelColumn
{
    public $name;
    public $type;
    public $simpletype;
    public $is_primary;
    public $is_nullable;
    public $is_autoincrement;
}

class ModelScope implements \ArrayAccess, \Iterator, \Countable
{
    protected $class_name;
    protected $Where;
    protected $order_by = [];
    protected $limit_offset;
    protected $limit_rowcount;
    protected $Objects;
    protected $position = 0;

    function __construct($class_name)
    {
        $this->class_name = $class_name;
        $this->Where = new WhereClause('and');
    }

    function where(...$args)
    {
        $this->Objects = null;
        $this->position = 0;

        if (is_array($args[0])) {
            $this->Where->add($this->query_cleanup(':ha'), $args[0]);
        } else {
            $this->Where->add(...$args);
        }

        return $this;
    }

    function order_by(...$items)
    {
        if (is_array($items[0])) {
            $this->order_by = $items[0];
        } else {
            $this->order_by = $items;
        }
        return $this;
    }

    function limit(int $one, ?int $two = null)
    {
        if (is_null($two)) {
            $this->limit_rowcount = $one;
        } else {
            $this->limit_offset = $one;
            $this->limit_rowcount = $two;
        }
        return $this;
    }

    function scope($scope, ...$args)
    {
        $this->Objects = null;
        $this->position = 0;

        $Scope = $this->class_name::_orm_runscope($scope, ...$args);

        if (count($this->Where) > 0) {
            $this->Where->add($Scope->Where);
        } else {
            $this->Where = $Scope->Where;
        }

        if (!is_null($Scope->limit_rowcount)) {
            $this->limit_rowcount = $Scope->limit_rowcount;
            $this->limit_offset = $Scope->limit_offset;
        }
        if ($Scope->order_by) {
            $this->order_by = $Scope->order_by;
        }

        return $this;
    }

    protected function run()
    {
        $table_name = $this->class_name::table();

        $query = 'SELECT * FROM :b WHERE :l';
        $args = [$table_name, $this->Where];

        if (count($this->order_by) > 0) {
            // array_is_list
            if ($this->order_by == array_values($this->order_by)) {
                $c_string = array_fill(0, count($this->order_by), ':c');
                $query .= ' ORDER BY ' . implode(',', $c_string);
                $args = array_merge($args, array_values($this->order_by));
            } else {
                $c_string = [];
                foreach ($this->order_by as $column => $order) {
                    $c_string[] = ':c ' . (strtolower($order) == 'desc' ? 'desc' : 'asc');
                }
                $query .= ' ORDER BY ' . implode(',', $c_string);
                $args = array_merge($args, array_keys($this->order_by));
            }
        }

        if (!is_null($this->limit_rowcount)) {
            if (!is_null($this->limit_offset)) {
                $query .= sprintf(' LIMIT %u, %u', $this->limit_offset, $this->limit_rowcount);
            } else {
                $query .= sprintf(' LIMIT %u', $this->limit_rowcount);
            }
        }

        $query = $this->query_cleanup($query);
        $this->Objects = $this->class_name::SearchMany($query, ...$args);
        return $this->Objects;
    }

    protected function run_if_missing()
    {
        if (is_array($this->Objects)) return;
        return $this->run();
    }

    protected function query_cleanup($query)
    {
        $param_char = $this->class_name::db()->param_char;
        return str_replace(':', $param_char, $query);
    }

    function first()
    {
        if (count($this) == 0) return null;
        return $this[0];
    }

    function last()
    {
        $count = count($this);
        if ($count == 0) return null;
        return $this[$count - 1];
    }

    function toArray()
    {
        return iterator_to_array($this);
    }

    #[\ReturnTypeWillChange]
    function count()
    {
        $this->run_if_missing();
        return count($this->Objects);
    }

    // ***** Iterator
    #[\ReturnTypeWillChange]
    function current()
    {
        $this->run_if_missing();
        return $this->valid() ? $this->Objects[$this->position] : null;
    }
    #[\ReturnTypeWillChange]
    function key()
    {
        $this->run_if_missing();
        return $this->position;
    }
    #[\ReturnTypeWillChange]
    function next()
    {
        $this->run_if_missing();
        $this->position++;
    }
    #[\ReturnTypeWillChange]
    function rewind()
    {
        $this->run_if_missing();
        $this->position = 0;
    }
    #[\ReturnTypeWillChange]
    function valid()
    {
        $this->run_if_missing();
        return array_key_exists($this->position, $this->Objects);
    }

    // ***** ArrayAccess
    #[\ReturnTypeWillChange]
    function offsetExists($offset)
    {
        $this->run_if_missing();
        return array_key_exists($offset, $this->Objects);
    }
    #[\ReturnTypeWillChange]
    function offsetGet($offset)
    {
        $this->run_if_missing();
        return $this->Objects[$offset];
    }
    #[\ReturnTypeWillChange]
    function offsetSet($offset, $value)
    {
        throw new MySQLModelException("Unable to edit scoped result set");
    }
    #[\ReturnTypeWillChange]
    function offsetUnset($offset)
    {
        throw new MySQLModelException("Unable to edit scoped result set");
    }
}

class MySQLModelException extends \Exception {}
