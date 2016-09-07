<?php
/**
 * Db.php
 *
 * @category Intermedia class database
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Backend
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 07/12/2015
 *
 */
namespace Application;

class Db
{
    protected $owner = null;
    protected $pdo = null;
    protected $opt = array(
        \PDO::ATTR_PERSISTENT => true,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    );

    public $status = false;

    /**
     * Db constructor.
     *
     * @param PHPRoll $owner | array ['db'=>'dbengin:dbname=...;host=...;port=...;user=...;password=...']
     * @param array|null $opt
     * @param boolean|null $attach if true \Application\Db obeject will be attated to paretn object.
     */
    public function __construct(&$owner, $attach = false, array $opt = null)
    {
        if (empty($owner)) throw new \Exception('\PHPRoll\Db - необходимо указать параметры подключения!');

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

        if (empty($db) && empty($pdo)) throw new \Exception('\PHPRoll\Db ERROR: DATABASE not defined.');
        try {
            $this->pdo = $pdo ?? new \PDO($db, null, null, $opt ?? $this->opt);
        } catch (\Exception $e) {
            $this->pdo = null;
        }
    }

    /**
     * PDO Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    protected function __get ( $name ) {
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
     * PDO stmt helper
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return \PDOStatement
     */
    public function stmt(string $sql, array $params=null, array $opt = ['normolize'=>true]): \PDOStatement
    {
        $stmt = $this->prepare($sql, $opt);
        preg_match_all('/:([a-zA-Z0-9\._]+)/', $sql, $v);
        if (isset($v[1])) {
            $data = !is_null($params) && \Application\PHPRoll::is_assoc($params) ? $params :
                ($opt['normolize'] ? \Application\PHPRoll::array_keys_normalization($params ?? $this->owner->params) : $this->owner->params);
            $this->status = $stmt->execute( count($data) ?  array_intersect_key($data, array_flip($v[1])) : null );
        } else {
            $this->status = $stmt->execute();
        }
        return $stmt;
    }

    /**
     *  PDO insert helper
     *
     * @param string $table
     * @param array $fields
     * @param bool $normolize
     * @return bool
     */
    public function insert(string $table, array $fields, bool $normolize = true): bool
    {
        $is_assoc = \Application\PHPRoll::is_assoc($fields);
        $data = $normolize ? \Application\PHPRoll::array_keys_normalization($this->owner->params) : $this->owner->params;
        $stmt = $this->prepare("INSERT INTO $table (" . implode(', ', $is_assoc ? array_keys($fields) : $fields).') VALUES ('
            . implode(', ', array_map(function($v){return ':'.$v;}, ($is_assoc ? array_values($fields) : $fields))) . ')');
        return$this->status = $stmt->execute(array_intersect_key($data, ($is_assoc ? array_values($fields) : array_flip($fields))));
    }

    /**
     * PDO update helper
     *
     * @param string $table
     * @param array $fields
     * @param $where
     * @param bool $normolize
     * @return bool
     */
    public function update(string $table, array $fields, $where, bool $normolize = true): bool
    {
        $is_assoc = \Application\PHPRoll::is_assoc($fields);
        $data = $normolize ? \Application\PHPRoll::array_keys_normalization($this->owner->params) : $this->owner->params;
        if ($is_assoc) {
            $f_keys = array_keys($fields);
            $f_values = array_values($fields);
        } else {
            $f_keys = $fields;
            $f_values = $fields;
        }
        $f = implode(', ', array_map(function ($v, $k) { return $k . ' = :' . $v; }, $f_values, $f_keys));

        if (is_array($where)) {
            $is_assoc = \Application\PHPRoll::is_assoc($where);
            if ($is_assoc) {
                $w_keys = array_keys($where);
                $w_values = array_values($where);
            } else {
                $w_keys = $where;
                $w_values = $where;
            }
            $w = implode(' AND ', array_map(function ($v, $k) { return $k . ' = :' . $v; }, $w_values, $w_keys));
        } else {
            preg_match_all('/:([a-zA-Z0-9\._]+)/', $where, $vars);
            $w_values = $vars[1] ?? [];
        }

        $stmt = $this->prepare("UPDATE $table SET $f WHERE $w");
        return $this->status = $stmt->execute(array_intersect_key($data, array_flip(array_merge($f_values, $w_values))));
    }

}

?>