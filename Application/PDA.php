<?php
/**
 * PDA.php
 *
 * @category Intermedia class database PostgresSQL Data Access (PDA)
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Backend
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 24/07/2017
 * @status beta
 * @version 0.1.2
 * @revision $Id: PDA.php 0004 2017-07-24 23:44:01Z $
 *
 */
namespace Application;

class PDA
{
    const FILTER_DEFAULT = ['page'=>0,'limit'=>100];

    const OBJECT_STRINGIFY = 4;

    public $status = false;

    protected $pdo = null;
    protected $opt = [
        //\PDO::ATTR_PERSISTENT => true,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    ];

    /**
     * PDA constructor
     *
     * @param 'dsn' = 'dbengin:dbname=...;host=...;port=...;user=...;password=...']
     * @param array $opt
     * @param boolean|null $attach if true \Application\PDA obeject will be attated to paretn object.
     */
    public function __construct($dsn, array $opt = [])
    {
        if (empty($dsn)) throw new \Exception('\Application\PDA - необходимо указать параметры подключения!');

        try {
            $this->pdo = new \PDO($dsn, $opt['username'] ?? null, $opt['passwd'] ?? null, $opt['PDO'] ?? $this->opt);
           // $this->pdo->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING);
        } catch (\Exception $e) {
            throw new \Exception(__CLASS__.": ".$e->getMessage());
        }
    }

    /**
     * PDO Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ($name)
    {
        if ($this->pdo instanceof \PDO && property_exists($this->pdo, $name)) {
            return $this->pdo->{$name};
        }
        throw new \Exception(__CLASS__."->$name property not foudnd!");
    }

    /**
     * PDO Native method
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($this->pdo instanceof \PDO && method_exists($this->pdo, $name)) return call_user_func_array(array($this->pdo, $name), $arguments);
        throw new \Exception(__CLASS__."->$name(...) method not foudnd");
    }

    /**
     * PDO Native static method
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        if (method_exists(\PDO, $name)) return call_user_func_array(array(\PDO, $name), $arguments);
        throw new \Exception(__CLASS__."::$name(...) method not foudnd");
    }

    /**
     * @function field
     *
     * @param $key
     * @return string
     */
    final static function field(string $key): string
    {
        preg_match('/([a-zA-Z0-9\._-]+)/', $key, $v);
        if ($v) return $v[1];
        return $key;
    }

    /**
     * @function queryParams
     *
     * @param string $query
     * @return array
     */
    final static function queryParams(string $query): array
    {
        preg_match_all('/:([a-zA-Z0-9\._]+)/', $query, $v);
        if (isset($v[1])) return array_flip($v[1]);
        return [];
    }

    /**
     * @function addPrefix
     *
     * @param  string | array $query
     * @param string $prefix
     * @return array [oldKey => newKey, .....] | []
     */
    final static function addPrefix(&$query, string $prefix): array
    {
        $keys = [];
        if (is_array($query)) {
            foreach($query as $k=>$v){
                $query[$prefix.$k] = $v;
                $keys[$k] = $prefix.$k;
                unset($query[$k]);
            }
        } elseif ($query) {
            preg_match_all('/:([a-zA-Z0-9\._]+)/', $query, $v);
            if (isset($v[1])) {
                foreach ($v[1] as $k => $v) {
                    str_replace($v, $prefix . $v, $query);
                    $keys[$v] = $prefix . $v;
                }
            }
        }
        return $keys;
    }

    /**
     * @function parameterize
     *
     * @param $param
     * @return float|int|null|string
     */
    public static function parameterize ($param, $opt = \PDO::NULL_NATURAL)
    {
        switch (gettype($param)) {
            case 'array':
//                $a = implode(',', array_map(function ($v) { return $this->parameterize($v); }, $param));
//                $val = json_encode($a,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                $val = array_map(function ($v) { return self::parameterize($v, \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY); }, $param);
                break;
            case 'NULL':
                $val = null;
                break;
            case 'boolean':
                $val = $param ? 1 : 0;
                break;
            case 'double':
                $val = floatval($param);
                break;
            case 'integer':
                $val = intval($param);
                break;
            case 'object':
                if ($opt & \Application\PDA::OBJECT_STRINGIFY) {
                    $val = strval($param);
                    if ($opt & \PDO::NULL_EMPTY_STRING) $val = ($val === '' ? null : $val);
                } else {
//                    $val = json_encode($param, JSON_FORCE_OBJECT | JSON_NUMERIC_CHECK);
                    $val = json_encode($param, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                }
                break;
            case 'string':
                if ( is_numeric($param) ) {
                    $folat = floatval($param); $val =  $folat != intval($folat) ? floatval($param) : intval($param);
                } else {
                    $val = strval($param);
                    if ($opt & \PDO::NULL_EMPTY_STRING) $val = ($val === '' ? null : $val);
                }
                break;
            default:
                $val = strval($param);
                if ($opt & \PDO::NULL_EMPTY_STRING) $val = ($val === '' ? null : $val);
        }

        return $val;
    }

    /**
     * Helper - where
     * @function where
     *
     * @param $where
     * @param array $params
     * @param array $source
     * @return string
     */
    static function where(&$where, array &$params=null, array $source=null):string
    {
        if (is_array($where)) {
            if ($is_assoc = \Application\PHPRoll::is_assoc($where)) {
                $keys = array_keys($where);
                $vals = $where;
            } else {
                $keys = $where;
                $vals = [];
            }

            return array_reduce($keys, function ($c, $k) use (&$where, $vals, $source, &$params) {
                    $key = self::field($k);
                    $exp = explode($key, $k);
                    $glue = !empty($c) ? 'AND' : '';
                    $key = str_replace('.','_', $key);

                    if ($params == null) {
                        $where[$key] =  $vals[$k] ?? $source = [$k] ?? null;
                        if ($key != $k) unset($where[$k]);
                    } else {
                        while (isset($params[$key])) { $key .= '_1'; }
                        $params[$key] = $vals[$k] ?? $source = [$k] ?? null;
                    }

                    switch ( trim($exp[1]) ) {
    //                    case '{}': ;  //TODO: JSON filter
    //                        return "$c $glue $key IN ($val)";
                        case '[]': ;
                            return "$c $glue $key IN (:$key)";
                        case '!^': ;
                            if ($params == null) { unset( $where[$key]); } else { unset($params[$key]); }
                            return "$c $glue $key IS NOT NULL";
                        case '$^': ; // если пусто подставить <параметр> is null а если есть значение то значение
                            $val = isset($vals[$k]) ? self::parameterize($vals[$k], \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY) : null;
                            if (!empty($val)) break;
                        case '^': ;
                            if ($params == null) { unset( $where[$key]); } else { unset($params[$key]); }
                            return "$c $glue $key IS NULL";
                        case '!~': ;
                            return "$c $glue $key NOT ILIKE :$key";
                        case '~': ;
                            return "$c $glue $key ILIKE :$key";
                        case '==': ;
                            return "$c $glue LOWER($key) = LOWER(:$key)";
                        case '++': ;
                            $val = isset($vals[$k]) ? self::parameterize($vals[$k], \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY) : ":$key";
                            if ($params == null) { unset( $where[$key]); } else { unset($params[$key]); }
                            return "$c $glue $val";
                        case '@@': ;
                            return "$c $glue to_tsvector('english', $key::text) @@ to_tsquery(:$key)";
                        case '>': case '>=': case '<': case '=<': case '=': case '!=':
    //                        $val = is_numeric($val) ? $val : "'$val'";
                            return "$c $glue $key {$exp[1]} :$key";
                        default:
    //                        $val = is_numeric($val) ? $val : "'$val'";
                            ;
                    }

                    return "$c $glue $key = :$key";
                }, ''
            );
        }

        $keys = self::queryParams($where);
        foreach ($keys as $k=>$v) {
            $key = str_replace('.','_', $k);
            while (isset($params[$key])) { $key .= '_1'; }
            $params[$key] = $source=[$k] ?? $params[$k] ?? null;
        }

        return $where;
    }

    /**
     * PDO stmt helper
     * @function stmt
     *
     * @param string $sql
     * @param array $params {is_assoc === TRUE for sing rowset, is_assoc === FALSE for BULK dataset }
     * @param array $opt
     * @return \PDOStatement
     */
    public function stmt(string $sql, array $params=[], array $opt = []): \PDOStatement
    {
        $stmt = $this->prepare($sql, $opt['PDO'] ?? $this->opt);
        if ($keys = self::queryParams($sql)) {
            if (\Application\PHPRoll::is_assoc($params)) {
                foreach (array_intersect_key($params, $keys) as $k => $v) {
                    $stmt->bindValue(":" . $k, $this->parameterize($v, \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY));
                }
            } else {
                $this->status = true;
                foreach ($params as $i=>$row) {
                    foreach (array_intersect_key($row, $keys) as $k => $v) {
                        $stmt->bindValue(":" . $k, $this->parameterize($v, \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY));
                    }
                    $this->status = $this->status && $stmt->execute();
                }
                return $stmt;
            }
        }

        $this->status = $stmt->execute();

        return $stmt;
    }

    /**
     * SQL builder - return complite SQL query string
     * @function query
     *
     * @param string $sql
     * @param array|null $params
     * @param array $opt
     * @return string
     */
    public function query(string $sql, array &$params, array $opt = []): string
    {
        $query = $sql;

        if (($fields = self::queryParams($sql)) && \Application\PHPRoll::is_assoc($params) ) {
            foreach (array_intersect_key($params, $fields) as $k=>$v) {
                $value = $this->parameterize($v, \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY);
                $query = str_replace(":$k", is_numeric($value) ? $value : "'$value'", $query);
            }
        }

        return $query;
    }

    /**
     * Prepare filter SQL query string
     * @function filtration
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return string
     */
    private function filtration(string $sql, array &$params, array $opt = []): string
    {
        $opt = array_merge(['wrap'=> false, 'paginator'=>true], $opt);

        if ($opt['wrap']) {
            $f = is_string($opt['wrap']) ? $opt['wrap'] : '*';
            $sql = "WITH raw_query_sql as ($sql) SELECT $f FROM raw_query_sql";
        }

        $offset = '';
        $limit = '';
        $swap = [];
        if ($opt['paginator']) {
            $swap = array_intersect_key($params, ['limit'=>0, 'page'=>1, 'offset'=>2]);
            $params = array_merge(\Application\PDA::FILTER_DEFAULT, $params);
            $ltd = 0;
            if (isset($params['limit'])) {
//                $ltd = intval(strval($params['limit']));
                $ltd = $this->parameterize($params['limit'], \Application\PDA::OBJECT_STRINGIFY);
//        $limit = " limit $ltd";
                $limit = "FETCH NEXT $ltd ROWS ONLY";
                unset($params['limit']);
            }

            if (isset($params['offset'])) {
                $offset = ' OFFSET ' . (strval($params['offset'])) . ' ROWS ';
                unset($params['offset']);
                if (isset($params['page'])) unset($params['page']);
            } elseif (isset($params['page'])) {
                $offset = ' OFFSET ' . (strval($params['page']) * $ltd) . ' ROWS ';
//        $offset = ' offset ' . (strval($params['page']) * $ltd);
                unset($params['page']);
            }
        } else {
            if (isset($params['offset'])) unset($params['offset']);
            if (isset($params['limit'])) unset($params['limit']);
        }

        $w = $this->where($params);
        $where = empty($w)  ? '' : " WHERE $w";
        if (isset($opt['where'])) $where .= empty($w) ? " WHERE {$opt['where']}": " AND ({$opt['where']}) ";
        $params = $params + $swap;
        return $sql . $where . (isset($opt['group']) ? ' '.$opt['group'].' ':'') . (isset($opt['having']) ? ' '.$opt['having'].' ':'') .(isset($opt['order']) ? ' '.$opt['order'].' ':'') . $offset . $limit;
    }

    /**
     * SQL filter builder - return complite SQL query string
     * @function filter_query
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return string
     */
    public function filter_query(string $sql, array $params, array $opt = []): string
    {
        return $this->query( $this->filtration( $sql, $params, $opt ), $params, $opt );
    }

    /**
     * PDO select helper with paggination, limit and etc
     * @function filter
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return \PDOStatement
     */
    public function filter(string $sql, array $params, array $opt = []): \PDOStatement
    {
        return $this->stmt( $this->filtration( $sql, $params, $opt ), $params, $opt );
    }

    /**
     * PDO insert helper
     * @function insert
     *
     * @param string $table
     * @param array $fields {is_assoc === TRUE for sing rowset, is_assoc === FALSE for $opt['params'] dataset }
     * @param array $opt,  $opt['params'] {is_assoc === TRUE for sing rowset, is_assoc === FALSE for BULK dataset }
     * @return bool
     */
    public function insert(string $table, array $fields, array $opt = []): bool
    {
        $self = $this;
        $prepare = function (array $keys, array $opt) use(&$self, $table): \PDOStatement
        {
            return $self->prepare("INSERT INTO $table (".implode(',', $keys)
                .') VALUES ('.implode(',', array_map(function($v){return ':'.str_replace('.','_', $v); }, $keys)).')',
                $opt['PDO'] ?? $self->opt);
        };

        if (\Application\PHPRoll::is_assoc($fields)) {
            $keys = array_keys($fields);
            $stmt = $prepare($keys, $opt);
            foreach ($keys as $v) {
                $stmt->bindValue(':'.str_replace('.','_', $v), $this->parameterize($params[$v], \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY));
            }
            return $this->status = $stmt->execute();

        } elseif (isset($opt['params'])) {
            $keys = $fields ;
            $stmt = $prepare($keys, $opt);
            if (\Application\PHPRoll::is_assoc($opt['params'])) {
                $params = array_intersect_key($opt['params'], array_flip($keys));
                foreach ($keys as $v) {
                    $stmt->bindValue(':'.str_replace('.','_', $v), $this->parameterize($params[$v], \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY));
                }
                return $this->status = $stmt->execute();
            }
        } else  {
            trigger_error("Application\PDA::insert(table=$table) нет данных!", E_USER_WARNING);
            return false;
        }

        $this->status = true;
        foreach ($opt['params'] as $k=>$v){
            $params = array_intersect_key($v, array_flip($keys));
            foreach ($keys as $v) {
                $stmt->bindValue(':'.str_replace('.','_', $v), $this->parameterize($params[$v], \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY));
            }
            $this->status = $this->status && $stmt->execute();
        }

        return $this->status;
    }

    /**
     * PDO update helper
     * @function update
     *
     * @param string $table
     * @param array $fields
     * @param {string | array} $where
     * @param array $opt
     * @return bool
     */
    public function update(string $table, array $fields, $where = null, array $opt = []): bool
    {
        $params = []; $keys = $fields; $exta = isset($opt['params']) ? $opt['params'] : [];
        if (\Application\PHPRoll::is_assoc($fields)) {
            $keys = array_keys($fields);
            $params = $fields;
        } elseif (isset($opt['params'])) {
            foreach ($keys as $k=>$v) { $params[$v] = isset($exta[$v]) ? $exta[$v] : null; }
        } else {
            trigger_error("Application\PDA::update(table=$table) нет данных!", E_USER_WARNING);
            return false;
        }
        $f = implode(',', array_map(function ($v) { return $v .' = :'. str_replace('.','_', $v); }, $keys));

        $w = '';
        if (is_string($where)) {
            $w = $this->where($where, $params, $exta);
        } elseif (\Application\PHPRoll::is_assoc($where)) {
            $w = $this->where($where,$params);
        } elseif ($where) {
            $a = []; foreach ($where as $k=>$v) { $a[$v] = isset($exta[$v]) ? $exta[$v] : (isset($params[$v]) ? $params[$v] : null); }
            $w = $this->where($a,$params);
        }
        if (!empty($w)) $w = " WHERE $w";
        if (isset($opt['where'])) $w .= empty($w) ? " WHERE {$opt['where']}": " AND ({$opt['where']}) ";

        $stmt = $this->prepare("UPDATE $table SET $f $w", $opt['PDO'] ?? $this->opt);
        foreach ($params as $k=>$v) {
            $stmt->bindValue(':'.$k, $this->parameterize($v, \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY));
        }

        return $this->status = $stmt->execute();
    }

}

?>