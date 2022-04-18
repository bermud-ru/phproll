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

    public const NULL_NATURAL = 0;
    public const NULL_EMPTY_STRING = 1;
    public const NULL_TO_STRING = 2;
    public const OBJECT_STRINGIFY = 4;
    public const ADDSLASHES = 8;
    public const QUERY_STRING_QUOTES = 16;
    public const QUERY_ARRAY_SEQUENCE = 32;
    public const ARRAY_STRINGIFY = 64;

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
           // $this->pdo->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING  | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY);
        } catch (\Exception $e) {
            throw new \Exception(__CLASS__.": ".$e->getMessage());
        }
    }

    /**
     * @function pg_escape_string
     * @param string $s
     * @return string|null
     *
     */
    public static function pg_escape_string(string $s): ?string
    {
       return (function_exists('pg_escape_string')) ? pg_escape_string($s) : str_replace( "'", "''" , $s);
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
        if ($this->pdo instanceof \PDO && method_exists($this->pdo, $name)) return call_user_func_array([$this->pdo, $name], $arguments);
        throw new \Exception(__CLASS__."->$name(...) method not foudnd");
    }

    /**
     * @function field
     *
     * @param $key
     * @return string
     */
    final static function field(string $key): string
    {
        preg_match('/([a-zA-Z]+[a-zA-Z0-9\._,]*)/', $key, $v);
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
        preg_match_all('/:([a-zA-Z]+[a-zA-Z0-9\._]*)/', $query, $v);
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
            preg_match_all('/:([a-zA-Z]+[a-zA-Z0-9\._]*)/', $query, $v);
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
            if ($is_assoc = \Application\Parameter::is_assoc($where)) {
                $keys = array_keys($where);
                $vals = $where;
            } else {
                $keys = $where;
                $vals = [];
            }

            return array_reduce($keys, function ($c, $k) use (&$where, $vals, $source, &$params) {
                    $key_original = self::field($k);
                    $exp = explode($key_original, $k);
                    $jsoned = FALSE;

                    if (in_array(trim($exp[0]), ['>>','#>','#2>','#4>','#8>'])) {
                        $jsoned = TRUE;
                        $f = explode(',', $key_original);
                        $i = array_pop($f);
                        $prefix = count($f) > 1 ? implode("->'", $f) . "'" : $f[0];
                        switch ($exp[0]) {
                            case '>>': $key_original = "$prefix->>'{$i}'"; break;
                            case '#>':  case '#4>': $key_original = "($prefix->>'{$i}')::int"; break;
                            case '#2>': $key_original = "($prefix->>'{$i}')::int2"; break;
                            case '#8>': $key_original = "($prefix->>'{$i}')::int8"; break;
                            default:
                        }
                    }

                    $glue = !empty($c) ? 'AND' : '';
                    $key = $jsoned ? $i : str_replace('.','_', $key_original);

                    if ($params == null) {
                        $where[$key] =  $vals[$k] ?? $source = [$k] ?? null;
                        if ($key != $k) unset($where[$k]);
                    } else {
                        while (array_key_exists($key, $params)) { $key .= '_1'; }
                        $params[$key] = $vals[$k] ?? $source = [$k] ?? null;
                    }

                    switch ( trim($exp[1]) ) {
    //                    case '{}': ;  //TODO: JSON filter
    //                        return "$c $glue $key_original IN ($val)";
                        case '[]': ;
                            return "$c $glue $key_original IN (:$key)";
                        case '!^': ;
                            if ($params == null) { unset( $where[$key]); } else { unset($params[$key]); }
                            return "$c $glue $key_original IS NOT NULL";
                        case '&^': ; // ( <параметр> == Parameter::ize(...) OR <параметр> is null )
                            $val = array_key_exists($k, $vals) ? \Application\Parameter::ize($vals[$k], \PDO::NULL_EMPTY_STRING  | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY) : 0;
                            return "$c $glue ($key_original = $val OR $key_original IS NULL)";
                        case '$^': ; // если пусто подставить <параметр> is null а если есть значение то значение
                            $val = array_key_exists($k, $vals) ? \Application\Parameter::ize($vals[$k], \PDO::NULL_EMPTY_STRING  | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY) : null;
                            if (!empty($val)) break;
                        case '^': ;
                            if ($params == null) { unset( $where[$key]); } else { unset($params[$key]); }
                            return "$c $glue $key_original IS NULL";
                        case '!~': ;
                            return "$c $glue $key_original NOT LIKE :$key";
                        case '~': ;
                            return "$c $glue $key_original LIKE :$key";
                        case '!~*': ;
                            return "$c $glue $key_original NOT ILIKE :$key";
                        case '~*': ;
                            return "$c $glue $key_original ILIKE :$key";
                        case '&': ;
                            $val = array_key_exists($k, $vals) ? \Application\Parameter::ize($vals[$k], \PDO::NULL_EMPTY_STRING | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY) : 0;
                            if ($params == null) { unset( $where[$key]); } else { unset($params[$key]); }
                            return "$c $glue $key_original & $val = $val";
                        case '==': ;
                            return "$c $glue LOWER($key_original) = LOWER(:$key)";
                        case '++': ;
                            $val = array_key_exists($k, $vals) ? \Application\Parameter::ize($vals[$k], \PDO::NULL_EMPTY_STRING | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY) : ":$key";
                            if ($params == null) { unset( $where[$key]); } else { unset($params[$key]); }
                            return "$c $glue $val";
                        case '@': ;
                            return "$c $glue similarity($key_original, :$key) > 0.5";
                        case '@@': ;
                            return "$c $glue to_tsvector('russian', $key_original::text) @@ to_tsquery(:$key)";
                        case '>': case '>=': case '<': case '=<': case '=': case '!=':
    //                        $val = is_numeric($val) ? $val : "'$val'";
                            return "$c $glue $key_original {$exp[1]} :$key";
                        default:
    //                        $val = is_numeric($val) ? $val : "'$val'";
                            ;
                    }

                    return "$c $glue $key_original = :$key";
                }, ''
            );
        }

        $keys = self::queryParams($where);
        foreach ($keys as $k=>$v) {
            $key = str_replace('.','_', $k);
            while (array_key_exists($key, $params)) { $key .= '_1'; }
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
            if (\Application\Parameter::is_assoc($params)) {
                foreach (array_intersect_key($params, $keys) as $k => $v) {
                    $stmt->bindValue(':' . str_replace('.','_', $k), \Application\Parameter::ize($v, \PDO::NULL_EMPTY_STRING | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY));
                }
            } else {
                $this->status = true;
                foreach ($params as $i=>$row) {
                    foreach (array_intersect_key($row, $keys) as $k => $v) {
                        $stmt->bindValue(':' . str_replace('.','_', $k), \Application\Parameter::ize($v, \PDO::NULL_EMPTY_STRING | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY));
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

        if (($fields = self::queryParams($sql)) && \Application\Parameter::is_assoc($params) ) {
            foreach (array_intersect_key($params, $fields) as $k=>$v) {
//                $query = str_replace(":$k", is_numeric($value) ? $value : ( $value === null ? 'null' :"'$value'"), $query);
                $query = str_replace(':' . str_replace('.','_', $k), \Application\Parameter::ize($v,  \Application\PDA::ADDSLASHES | \Application\PDA::QUERY_STRING_QUOTES), $query);
            }
        }
        return $query;
    }

    /**
     * @function query_paginator
     *
     * @param $params
     * @param bool $is_paginator
     * @return string
     */
    static function query_paginator (array &$params, $is_paginator = true): string
    {
        $offset = '';
        $limit = '';
        if ($is_paginator) {
            $params = array_merge(\Application\PDA::FILTER_DEFAULT, $params);
            $ltd = 0;
            if (isset($params['limit'])) {
                $ltd = \Application\Parameter::ize($params['limit']);
//        $limit = " limit $ltd";
//        $limit = "FETCH NEXT $ltd ROWS ONLY";
                $limit = "FETCH FIRST $ltd ROW ONLY";
                unset($params['limit']);
            }

            if (isset($params['offset'])) {
                $offset = ' OFFSET ' . (\Application\Parameter::ize($params['offset'])) . ' ROWS ';
                unset($params['offset']);
                if (isset($params['page'])) unset($params['page']);
            } elseif (isset($params['page'])) {
                $offset = ' OFFSET ' . (\Application\Parameter::ize($params['page']) * $ltd) . ' ROWS ';
//        $offset = ' offset ' . (\Application\Parameter::ize($params['page']) * $ltd);
                unset($params['page']);
            }
        } else {
            if (array_key_exists('page', $params)) unset($params['page']);
            if (array_key_exists('offset', $params)) unset($params['offset']);
            if (array_key_exists('limit', $params)) unset($params['limit']);
        }

        return $offset . $limit;
    }

    /**
     * Part of query for huge data pagination
     * @function huge_paginator
     *
     * @param array $paginator
     * @param array $filter
     * @return string
     */
    static function huge_paginator ( array $paginator, array &$filter, $opt=[4,5,10]): string
    {
        $page = intval("{$paginator['page']}");
        $limit = intval("{$paginator['limit']}");
        $total = isset($paginator['total']) ? intval("{$paginator['total']}") : ($limit * $opt[2] + 1);
        $offset = ($page > $opt[4]) ? ($page-$opt[1]) * $limit : 0;
        $filter['limit'] = $offset + $total;
        return ($page > $opt[1]) ? "(case when count(*) < {$filter['limit']} then count(*) else {$filter['limit']} end)" : 'count(*)';
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
    protected function filtration(string $sql, array &$params, array $opt = []): string
    {
        $opt = array_merge(['wrap'=> false, 'paginator'=>true], $opt);

        if ($opt['wrap']) {
            $f = is_string($opt['wrap']) ? $opt['wrap'] : '*';
            $sql = "WITH raw_query_sql as ($sql) SELECT $f FROM raw_query_sql";
        }

        $swap = array_intersect_key($params, ['limit'=>0, 'page'=>1, 'offset'=>2]);
        $offset = $this->query_paginator($params, $opt['paginator']);

        $w = $this->where($params);
        $where = empty($w)  ? '' : " WHERE $w";
        if (isset($opt['where'])) $where .= empty($w) ? " WHERE {$opt['where']}": " AND ({$opt['where']}) ";
        $params = $params + $swap;
        return $sql . $where . (isset($opt['group']) ? ' '.$opt['group'].' ':'') . (isset($opt['having']) ? ' '.$opt['having'].' ':'') .(isset($opt['order']) ? ' '.$opt['order'].' ':'') . $offset;
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
    public function insert(string $table, array $fields, $opt = [])
    {
        $self = $this;
        if ($opt instanceof \Application\Parameter) $opt = $opt->getValue();

        $prepare = function (array $keys, array $opt) use(&$self, $table): \PDOStatement
        {
            return $self->prepare("INSERT INTO $table (".implode(',', $keys)
                .') VALUES ('.implode(',', array_map(function($v){return ':'.str_replace('.','_', $v); }, $keys)).') RETURNING *',
                $opt['PDO'] ?? $self->opt);
        };

        if (\Application\Parameter::is_assoc($fields)) {
            $keys = array_keys($fields);
            $stmt = $prepare($keys, $opt);
            foreach ($keys as $v) {
                $stmt->bindValue(':'.str_replace('.','_', $v), \Application\Parameter::ize($fields[$v], \PDO::NULL_EMPTY_STRING | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY));
            }
            $this->status = $stmt->execute();
            return $this->status ? $stmt : null;
        } elseif (isset($opt)) {
            $keys = $fields ;
            $stmt = $prepare($keys, $opt);
            if (\Application\Parameter::is_assoc($opt)) {
                $params = array_intersect_key($opt, array_flip($keys));
                foreach ($keys as $v) {
                    $stmt->bindValue(':'.str_replace('.','_', $v), \Application\Parameter::ize($params[$v], \PDO::NULL_EMPTY_STRING | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY));
                }
                $this->status = $stmt->execute();
                return $this->status ? $stmt : null;
            }
        } else  {
            trigger_error("Application\PDA::insert(table=$table) нет данных!", E_USER_WARNING);
            return false;
        }

        $this->status = true;
        $returning = [];
        foreach ($opt as $k=>$v){
            $params = array_intersect_key($v, array_flip($keys));
            foreach ($keys as $v) {
                $stmt->bindValue(':'.str_replace('.','_', $v), \Application\Parameter::ize($params[$v], \PDO::NULL_EMPTY_STRING | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY));
            }
            $this->status = $this->status && $stmt->execute();
            if ($returning) $returning[] = $stmt;
        }

        return $this->status ? $returning : null;
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
    public function update(string $table, array $fields, $where = null, $opt = []): ?\PDOStatement
    {
        if ($opt instanceof \Application\Parameter) $opt = $opt->getValue();
        if (\Application\Parameter::is_assoc($fields)) {
            $keys = array_keys($fields);
            $params = $fields;
        } elseif ($opt) {
            $keys = array_values($fields);

            $params = array_intersect_key($opt, array_flip($keys));
        } else {
            trigger_error("Application\PDA::update(table=$table) нет данных!", E_USER_WARNING);
            return null;
        }
        $f = implode(',', array_map(function ($v) { return $v .' = :'. str_replace('.','_', $v); }, $keys));

        $w = '';
        if (is_string($where)) {
            $w = $this->where($where, $params, $opt);
        } elseif (\Application\Parameter::is_assoc($where)) {
            $w = $this->where($where,$params);
        } elseif ($where) {
            $a = []; foreach ($where as $k=>$v) { $a[$v] = array_key_exists($v, $opt) ? $opt[$v] : (array_key_exists($params[$v]) ? $params[$v] : null); }
            $w = $this->where($a,$params);
        }
        if (!empty($w)) $w = " WHERE $w";

        $stmt = $this->prepare("UPDATE $table SET $f $w RETURNING *", $this->opt);
        foreach ($params as $k=>$v) {
            $stmt->bindValue(':'.$k, \Application\Parameter::ize($v, \PDO::NULL_EMPTY_STRING  | self::OBJECT_STRINGIFY | self::ARRAY_STRINGIFY));
        }

        $this->status = $stmt->execute();
        return $this->status ? $stmt : null;
    }

    /**
     * @function ObjectId
     *
     * @param $timestamp
     * @param $hostname
     * @param $processId
     * @param $id
     * @return ObjectId value. The 12-byte ObjectId value consists of:
     * 4-byte timestamp value, representing the ObjectId’s creation, measured in seconds since the Unix epoch
     * 5-byte random value
     * 3-byte incrementing counter, initialized to a random value
     *
     * @example \Application\PDA::ObjectId(time(), php_uname('n'), getmypid(), $id);
     */
    static function ObjectId($timestamp, $hostname, $processId, $id)
    {
        // Building binary data.
        $bin = sprintf("%s%s%s%s",
            pack('N', $timestamp),
            substr(md5($hostname), 0, 3),
            pack('n', $processId),
            substr(pack('N', $id), 1, 3)
        );

        // Convert binary to hex.
        $result = '';
        for ($i = 0; $i < 12; $i++) {
            $result .= sprintf("%02x", ord($bin[$i]));
        }

        return $result;
    }

    /**
     * fields_diff
     *
     * @param array $row
     * @param array $idx
     * @return array
     */
    final static function fields_diff($row, array $idx): array
    {
        return array_values(array_diff(array_keys($row), $idx));
    }

    /**
     * @function param_wraper
     *
     * @param $v - Value
     * @param $i - Index
     * @param $a - Array
     * @return float|int|string
     */
    final static function param_wraper($v) {
        switch (gettype($v)) {
            case 'object':
                if ($v instanceof DateTime) {
                    $val = "'" . date('Y-m-d H:i:s', $v) . "'" ;
                    break;
                }
            case 'array':
                $val = "'" . json_encode($v, JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) . "'";
                break;
            case 'NULL':
                $val = null; // 'null'; // JSON NULL CHECK QOUTER
                break;
            case 'boolean':
                $val = $v ? 1 : 0;
                break;
            case 'float':
            case 'double':
                $val = floatval($v);
                break;
            case 'integer':
                $val = intval($v);
                break;
            case 'string':
            default:
            if ( is_numeric($v) ) {
                $folat = floatval($v); $val = $v != intval($folat) ? $folat : intval($v);
            } else {
                $val = "'".self::pg_escape_string($v)."'";
            }
        }
        return $val;
    }

    /**
     * @function array2insert
     * Array to Sting for greate sql insert query or vlues pattern
     *
     * @param array $a
     * @param array $fiels
     * @param bool $keys
     * @return string
     */
    final static function array2insert(array $a, array $fiels = [], bool $keys = true):string
    {
        $query = '';
        $ds = \Application\Parameter::is_assoc($a) ? [$a] : $a;

        if ($keys) $query = ' (' . ( count($fiels) ? implode(',', array_keys(array_intersect_key($ds[0], array_flip($fiels)))) : implode(',', array_keys($ds[0]))) . ') ';

        $items = [];
        foreach ($ds as $idx => $row) {
            $items[] ='(' . implode(',', array_map( function ($v) { return \Application\PDA::param_wraper($v);}, array_values(count($fiels) ? array_intersect_key($row, array_flip($fiels)) : $row))) . ')';
        }
        $query .= 'values ' . implode(',', $items);

        return $query;
    }

    /**
     * @function array2update
     *
     * @param array $a - Key Value
     * @return string
     */
    final static function array2update(array $a, array $exclude = []): string
    {
        $p = array_diff_key($a, array_flip($exclude));
        return implode(',', array_map(function ($k, $v) { return "$k = " . \Application\PDA::param_wraper($v); }, array_keys($p), $p));
    }
}

?>