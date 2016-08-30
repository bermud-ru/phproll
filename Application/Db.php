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
    protected $parent = null;
    protected $pdo = null;
    protected $opt = array(
        \PDO::ATTR_PERSISTENT => true,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
    );

    public $index = null;
    public $status = false;

    /**
     * Db constructor.
     *
     * @param PHPRoll $parent | array ['db'=>'dbengin:dbname=...;host=...;port=...;user=...;password=...']
     * @param array|null $opt
     * @param boolean|null $attach if true \Application\Db obeject will be attated to paretn object.
     */
    public function __construct(&$parent, array $opt = null, $attach = false)
    {
        if (empty($parent)) throw new \Exception('\PHPRoll\Db - необходимо указать параметры подключения!');

        $db =  null;
        $pdo = null;
        if (is_array($parent)){
            $this->parent = null;
            $db = $parent['db'] ?? null;
        } elseif ($parent instanceof \Application\PHPRoll) {
            $this->parent = $parent;
            $db = $parent->config['db'] ?? null;
            if ($attach) {
                $parent->db = $this;
                if (!empty($parent->db) && $parent->db->pdo instanceof \PDO) $pdo = $this->parent->pdo;
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
     * PDO Native
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($this->pdo && method_exists($this->pdo, $name)) return call_user_func_array(array($this->pdo, $name), $arguments);
        return new \PDOStatement();
    }

    /**
     * PDO insert helper
     *
     * @param string $table
     * @param array $fields
     * @return \PDOStatement
     */
    public function insert(string $table, array $fields, $keys = true): bool
    {
        $stmt = $this->prepare("INSERT INTO $table (" . implode(', ', array_keys($fields)).') VALUES (' . implode(', ', array_map(function($v){return ':'.$v;}, ($keys ? array_keys($fields) : array_values($fields)))) . ')');
        $this->status = $stmt->execute(array_intersect_key(($this->index ? $this->parent->params[$this->index] : $this->parent->params), ($keys ? $fields : array_flip(array_values($fields)))));

        return $this->status;
    }

    /**
     * PDO update helper
     *
     * @param string $table
     * @param array $fields
     * @param string $where
     * @return \PDOStatement
     */
    public function update(string $table, $fields = false, $where = ''): bool
    {
        if ($fields && is_array($fields)) {
            $stmt = $this->prepare("UPDATE $table SET " . implode(', ', array_map(function ($v, $k) {
                    return $k . ' = :' . $v;
                }, $fields, array_keys($fields))) .
                (is_array($where) ? " WHERE " . implode(', ', array_map(function ($v, $k) {
                        return $k . ' = :' . $v;
                    }, $where, array_keys($where))) : $where));
            return $this->status = $stmt->execute(array_intersect_key(($this->index ? $this->parent->params[$this->index] :
                $this->parent->params), array_flip(array_values(array_merge($fields, $where)))));
        }
        else
        {
            $fields = array_diff_key(($this->index ? $this->parent->params[$this->index] : $this->parent->params), $where);

            $stmt = $this->prepare("UPDATE $table SET " . implode(', ', array_map(function ($v) {
                    return $v . ' = :' . $v;
                }, array_keys($fields))) .
                " WHERE " . implode(', ', array_map(function ($v) {
                    return $v . ' = :' . $v;
                }, $where)));
            return $this->status = $stmt->execute($this->index ? $this->parent->params[$this->index] : $this->parent->params);
        }

    }

    /**
     * PDO stmt helper
     *
     * @param string $sql
     * @param array $params
     * @param array $opt
     * @return \PDOStatement
     */
    public function stmt(string $sql, array $params=null, array $opt=[]): \PDOStatement
    {
        $stmt = $this->prepare($sql, $opt);
        preg_match_all('/:[a-zA-Z0-9_]+/',$sql,$vars);
        $v =  isset($vars[0]) ? array_map( function ($v) { return str_replace(':', '', $v); }, $vars[0]) : null;
        if ($v) {
            $data = isset($params) ? $params : ($this->index ? $this->parent->params[$this->index] : $this->parent->params);
            $this->status = $stmt->execute(is_null($data) ? null : array_intersect_key($data, array_flip($v)));
        }
        else
        {
            $this->status = $stmt->execute();
        }
        return $stmt;
    }

}

?>