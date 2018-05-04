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

    public $owner = null;
    public $status = false;

    protected $pdo = null;
    protected $opt = array(
        //\PDO::ATTR_PERSISTENT => true,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    );

    /**
     * PDA constructor
     *
     * @param PHPRoll $owner | array ['db'=>'dbengin:dbname=...;host=...;port=...;user=...;password=...']
     * @param array|null $opt
     * @param boolean|null $attach if true \Application\PDA obeject will be attated to paretn object.
     */
    public function __construct(&$owner, $attach = false, array $opt = null)
    {
        if (empty($owner)) throw new \Exception('\Application\PDA - необходимо указать параметры подключения!');

        $db =  null;
        $pdo = null;
        if (is_array($owner)){
            $this->owner = null;
            $db = $owner['db'] ?? null;
        } elseif ($owner instanceof \Application\PHPRoll) {
            $this->owner = $owner;
            $db = $owner->config['db'] ?? null;
            if ($attach) {
                $owner->db = $this;
                if (!empty($owner->db) && $owner->db->pdo instanceof \PDO) $pdo = $this->owner->pdo;
            }
        }

        if (empty($db) && empty($pdo)) throw new \Exception('\Application\PDA ERROR: DATABASE not defined.');
        try {
            $this->pdo = $pdo ?? new \PDO($db, null, null, $opt ?? $this->opt);
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
    public function __get ( $name ) 
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
     *
     * @param $key
     * @return string
     */
    final static function field(&$key): string
    {
        preg_match('/([a-zA-Z0-9\._-]+)/', $key, $v);
        if ($v) return $v[1];
        return $key;
    }

    /**
     * parameterize
     *
     * @param $param
     * @return float|int|null|string
     */
    public static function parameterize ($param)
    {
        switch (gettype($param)) {
            case 'array':
                $a = implode(',', array_map(function ($v) { return \Application\PDA::parameterize($v); }, $param));
                $val = json_encode($a,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                break;
            case 'NULL':
                $val = null;
                break;
            case 'boolean':
                $val = $param ? 1 : 0;
                break;
            case 'double':
                $val =  floatval($param);
                break;
            case 'integer':
                $val = intval($param);
                break;
            case 'object':
//                $val = json_encode($param, JSON_FORCE_OBJECT | JSON_NUMERIC_CHECK);
//                break;
                $param = strval($param);
            case 'string':
                if ( is_numeric($param) ) {
                    $folat = floatval($param); $val =  $folat != intval($folat) ? floatval($param) : intval($param);
                } else $val = strval($param);
                break;
            default:
                $val = strval($param);
        }

        return $val;
    }

    /**
     * Helper - where
     *
     * @param $where
     * @param $vlues
     * @return string
     */
    static function where($where, &$vlues=null):string
    {
        if (is_array($where)) {

            $is_assoc = \Application\PHPRoll::is_assoc($where);
            if ($is_assoc) {
                if ($vlues !== null) $vlues = array_values($where);
                $keys = array_keys($where); $vals = $where;
            } else {
                if ($vlues !== null) $vlues = $where;
                $keys = $where; $vals = [];
            }

            return array_reduce($keys, function ($c, $k) use (&$vals) {
                $key = self::field($k);
                $exp = explode($key, $k);
                $glue = '';
                if (!empty($c)) switch (trim($exp[0])) { //TODO OR engin
                    case '|': $glue = 'OR'; break;
                    case '&':
                    default: $glue = 'AND';
                }

                if (empty($vals)) {
                    $val = ":$key" ;
                } else {
                    if (gettype($vals[$k]) == 'object') $val = $vals[$k]->value;
                    else $val = $vals[$k];

                    switch (gettype($val)) {
                        case 'object':  //TODO: JSON filter
                            $val = json_encode($vals[$k], JSON_FORCE_OBJECT);
                            break;
                        case 'array':
//                            $a = implode(',', array_map(function ($v) { return is_numeric($v) ? $v:printf("'%s'",$v) ; }, $val));
                            $a = implode(',', array_map(function ($v) { return is_numeric($v) ? $v : "'$v'"; }, $val));
                            return "$c $glue $key IN ($a})";
                        case 'NULL':
                            if (empty($exp[1])) $exp[1] = '$^';
                            break;
                        case 'integer':
                            $val = "{$vals[$k]}";
                            break;
                        case 'string':
                        default:
//                            $val = is_numeric($val) ? $val : "'{$val}'";
                            $val = "'{$vals[$k]}'";
                    }
                }

                switch ( trim($exp[1]) ) {
//                    case '{}': ;  //TODO: JSON filter
//                        return "$c $glue $key IN ($val)";
//                        break;
                    case '[]': ;
                        return "$c $glue $key IN ($val)";
                        break;
                    case '!^': ;
                        return "$c $glue $key IS NOT NULL";
                        break;
                    case '$^': ; // если пусто подставить <параметр> is null а если есть значение то значение
                        if (!empty($val)) break;
                    case '^': ;
                        return "$c $glue $key IS NULL";
                        break;
                    case '!~': ;
                        return "$c $glue $key NOT ILIKE $val";
                        break;
                    case '~': ;
                        return "$c $glue $key ILIKE $val";
                        break;
                    case '@@': ;
                        return "$c $glue to_tsvector('english', $key::text) @@ to_tsquery($val)";
                        break;
                    case '>': case '>=': case '<': case '=<': case '=': case '!=':
                        return "$c $glue $key {$exp[1]} $val";
                        break;
                    default:
                        ;
                }

                return "$c $glue $key = $val";

                }, ''
            );

        } else {
            preg_match_all('/:([a-zA-Z0-9\._]+)/', $where, $vars);
            if ($vlues !== null) $vlues = $vars[1] ?? [];
        }

        return $where;
    }

    /**
     * PDO stmt helper
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return \PDOStatement
     */
    public function stmt(string $sql, array $params=null, array $opt = ['normolize'=>true]): \PDOStatement
    {
        $stmt = $this->prepare($sql, $opt['PDO'] ?? []);
        preg_match_all('/:([a-zA-Z0-9\._]+)/', $sql, $v);
        if (isset($v[1])) {
            $data = !is_null($params) && \Application\PHPRoll::is_assoc($params) ? $params :
            ($opt['normolize'] ? \Application\PHPRoll::array_keys_normalization($params ?? $this->owner->params) : $this->owner->params);
            if (count($data)) foreach (array_intersect_key($data, array_flip($v[1])) as $k=>$v)
                $stmt->bindValue(":".$k, \Application\PDA::parameterize($v), \PDO::NULL_EMPTY_STRING);
        }
        $this->status = $stmt->execute();

        return $stmt;
    }

    /**
     * SQL builder - return complite SQL query string
     *
     * @param string $sql
     * @param array|null $params
     * @param array $opt
     * @return string
     */
    public function query(string $sql, array $params=null, array $opt = ['normolize'=>true]): string
    {
        $query = $sql;
        preg_match_all('/:([a-zA-Z0-9\._]+)/', $sql, $v);
        if (isset($v[1])) {
            $data = !is_null($params) && \Application\PHPRoll::is_assoc($params) ? $params :
                ($opt['normolize'] ? \Application\PHPRoll::array_keys_normalization($params ?? $this->owner->params) : $this->owner->params);
            if (count($data)) foreach (array_intersect_key($data, array_flip($v[1])) as $k=>$v) {
                $value = strval($v);
                $query = str_replace(":$k", is_numeric($value) ? $value : "'$value'", $query);
            }
        }

        return $query;
    }

    /**
     * Prepare filter SQL query string
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return string
     */
    private  function filtration(string &$sql, array &$params = [], array &$opt = ['normolize'=>true, 'wrap'=> false]): string
    {
        //$opt = array_merge(['normolize'=>true, 'wrap'=> true], $opt);

        if (count($params)) {
            $params = array_merge(\Application\PDA::FILTER_DEFAULT, $params);
        } else {
            $params = array_merge(\Application\PDA::FILTER_DEFAULT, $opt['normolize'] ?
                \Application\PHPRoll::array_keys_normalization($this->owner->params) : $this->owner->params);
        }

        if (isset($opt['wrap']) && $opt['wrap']) {
            $f = is_string($opt['wrap']) ? $opt['wrap'] : '*';
            $sql = "WITH raw_query_sql as ($sql) SELECT $f FROM raw_query_sql";
        }

        $offset = '';
        $limit = '';
        $ltd = 0;
        if (isset($params['limit'])) {
            $ltd = intval(strval($params['limit']));
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

        $w = $this->where($params);
        $where = empty($w)  ? '' : " WHERE $w";
        if (isset($opt['where'])) $where .= empty($w) ? " WHERE {$opt['where']}": " AND ({$opt['where']}) ";

        return $sql . $where . (isset($opt['group']) ? ' '.$opt['group'].' ':'') . (isset($opt['having']) ? ' '.$opt['having'].' ':'') .(isset($opt['order']) ? ' '.$opt['order'].' ':'') . $offset . $limit;
    }

    /**
     * SQL filter builder - return complite SQL query string
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return string
     */
    public function filter_query(string $sql, array $params = [], array $opt = ['normolize'=>true, 'wrap'=> false]): string
    {
        return $this->query( $this->filtration( $sql, $params, $opt ), $params, $opt );
    }

    /**
     *  PDO select helper with paggination, limit and etc
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return \PDOStatement
     */
    public function filter(string $sql, array $params = [], array $opt = ['normolize'=>true, 'wrap'=> false]): \PDOStatement
    {
        return $this->stmt( $this->filtration( $sql, $params, $opt ), $params, $opt );
    }

    /**
     *  PDO insert helper
     *
     * @param string $table
     * @param array $fields
     * @param array $opt
     * @return bool
     */
    public function insert(string $table, array $fields, array $opt = ['normolize'=>true]): bool
    {
        $is_assoc = \Application\PHPRoll::is_assoc($fields);
        $data = $opt['params'] ?? (!isset($opt['normolize']) || $opt['normolize'] === true ?
                \Application\PHPRoll::array_keys_normalization($this->owner->params) : $this->owner->params);
        $values = array_intersect_key($data, array_flip($is_assoc ? array_values($fields) : $fields));
        $f = $is_assoc ? array_values($fields) : $fields;
        $stmt = $this->prepare("INSERT INTO $table (" . implode(', ', $is_assoc ? array_keys($fields) : $fields)
                            .') VALUES (' . implode(', ', array_map(function($v){return ':'.$v;}, $f)) . ')', $opt['PDO'] ?? []);
//        foreach ($f as $v) $stmt->bindValue(":".$v, $values[$v] == '' ? null : strval($values[$v]), \PDO::NULL_EMPTY_STRING);
        foreach ($f as $v) $stmt->bindValue(":".$v, $values[$v] == '' ? null : strval($values[$v]), \PDO::NULL_EMPTY_STRING);

        return$this->status = $stmt->execute();
    }

    /**
     * PDO update helper
     *
     * @param string $table
     * @param array $fields
     * @param $where
     * @param array $opt
     * @return bool
     */
    public function update(string $table, array $fields, $where, array $opt = ['normolize'=>true]): bool
    {
        $is_assoc = \Application\PHPRoll::is_assoc($fields);
        $data = $opt['params'] ?? (!isset($opt['normolize']) || $opt['normolize'] === true ?
                \Application\PHPRoll::array_keys_normalization($this->owner->params) : $this->owner->params);
        if ($is_assoc) {
            $f_keys = array_keys($fields);
            $f_values = array_values($fields);
        } else {
            $f_keys = $fields;
            $f_values = $fields;
        }
        $f = implode(', ', array_map(function ($v, $k) { return $k . ' = :' . $v; }, $f_values, $f_keys));

        $w_values = [];
        $__were = $this->where($where, $w_values);
        $w = empty( $__were) ? '' : "WHERE  $__were";
//var_dump([$w,$w_values]);exit;
        $stmt = $this->prepare("UPDATE $table SET $f $w", $opt['PDO'] ?? []);
        if (count($data)) foreach (array_intersect_key($data, array_flip(array_merge($f_values, $w_values))) as $k=>$v)
            $stmt->bindValue(":".$k, $v == '' ? null : strval($v), \PDO::NULL_EMPTY_STRING);

        return $this->status = $stmt->execute();
    }

}

?>