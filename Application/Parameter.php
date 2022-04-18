<?php
/**
 * Parameter.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Parameter helper for \Application\Rest
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 01/08/2016
 * @status beta
 * @version 0.1.2
 * @revision $Id: Parameter.php 0004 2017-07-24 23:44:01Z $
 *
 */

namespace Application;

class Parameter implements \JsonSerializable
{
    protected $owner = null;
    protected $alert = '';
    protected $index = null;
    protected $name = null;
    protected $type = 'string';
    protected $alias = null;
    protected $default = null;
    protected $validator = null;
    protected $opt = null;
    protected $property = null;
    protected $message = null;
    protected $required = false;
    protected $before = null;
    protected $after = null;
    protected $formatter = null;
    protected $raw = null;
    protected $restore = false;

    public $isValid = true;
    public $params = [];
    public $key = null;
    public $value = null;
//    public $original = null;
    
    const MESSAGE = "Parameter error, {name} = {value} is wrong!";
    const KEY_SEPARATOR = '.';
    const glue = ',';

    /**
     * Parameter constructor
     *
     * @param array $opt
     * @param array $params
     */
    public function __construct( array $opt, array &$params )
    {
        $this->params = &$params;
        foreach ($opt as $k => $v) { if (property_exists($this, $k)) $this->{$k} = $v; }

        $this->raw = isset($params[$this->alias]) ? $params[$this->alias] : (isset($params[$this->name]) ? $params[$this->name] : null);
        $this->value = is_null($this->raw) ? (is_callable($this->default) ? call_user_func_array($this->default->bindTo($this), $this->arguments($this->default)) : $this->default) : $this->raw ;

        if (empty($this->validator) && $this->type != 'string') {
            switch (strtolower($this->type)) {
                case 'file':
                    $this->validator = function () {
                        return isset($_FILES[$this->name]);
                    };
                    break;
                case 'array':
                    $this->validator = function () {
                        return is_string($this->value) ? preg_match('/^\s*\[.*\]\s*$/', $this->value) : is_array($this->value);
                    };
                    break;
                case 'bool':
                    $this->validator = '/^(0|1)$/';
                    break;
                case 'date':
                    $this->validator = '/^(0|1|2|3)\d\.(0|1)\d\.\d{4}$/';
                    break;
                case 'float':
                    $this->validator = '[+-]?([0-9]*[.])?[0-9]+';
                    break;
                case 'int':
                    $this->validator = '/^[+-]?\d+$/';
                    break;
            }
        }

        if (is_callable($this->before)) $this->value = call_user_func_array($this->before->bindTo($this), $this->arguments($this->before));

        if (is_callable($this->required)) $this->required = call_user_func_array($this->required->bindTo($this), $this->arguments($this->required));

        $empty = $this->value === null || $this->value === '';
        if ($this->required && $empty) { $this->isValid = false; }
        if ($this->isValid && ($this->validator && !$empty)) {
            if (is_callable($this->validator)) {
                $this->isValid = call_user_func_array($this->validator->bindTo($this), $this->arguments($this->validator));
            } elseif (is_string($this->validator) && !preg_match($this->validator, $this->value)) {
                $this->isValid = false;
            }
        }

        if ($this->isValid) {
            if (is_callable($this->after)) $this->value = call_user_func_array($this->after->bindTo($this), $this->arguments($this->after));
            $this->params[$this->key = $this->alias ?? $this->name] = $this;
//            $this->original = $this->name;
//        $this->params[($this->alias ? preg_replace('/\(.*\)/U', $this->name , $this->alias) : $this->name)] = $this;
        } else {
            $this->alert = \Application\IO::replace($opt['message'] ?? self::MESSAGE, ['name' => $this->name, 'value' => $this->value]);
        }
    }

    /**
     * @function append
     * Create custom parameter and add to poll
     *
     * @param array $fields
     * @param array $params
     * @return bool
     */
    public static function append(array $fields, array &$params):bool
    {
        if (self::is_assoc($fields)) {
            foreach ($fields as $k => $v) {
               new \Application\Parameter(['name'=>$k, 'default'=>$v],$params);
            }
            return true;
        }
        trigger_error("Application\Parameter() creation error!", E_USER_WARNING);
        return false;
    }

    /**
    * @function setOwner
    * Set Owner of parameter
    *
    * @param object $owner
    * @return null|object
    */
    public function setOwner(&$owner):\Application\Parameter
    {
        $this->owner = $owner;
        if ($owner && $this->alert) {
            if (!isset($owner->error)) $owner->error = [];
            $owner->error[$this->name] = $this->alert;
        }
        return $this;
    }

    /**
     * @function property
     * Init contex of property
     *
     * @param $name
     * @param $default
     */
    protected function property($name, $default = null)
    {
        if (!property_exists($this, $name)) {
            trigger_error("Application\Parameter::$name not exist!", E_USER_WARNING);
            return $default;
        }

        $result = &$this->{$name};
        if (is_callable($result)) {
            $result = call_user_func_array($result->bindTo($this), $this->arguments($result));
            if ($result === false) {
                trigger_error("Application\Parameter::$name run time error!", E_USER_WARNING);
                return $default;
            }
        }
        return $result ?? $default;
    }

    /**
     * @function arguments
     * Prepare args for closure
     *
     * @param callable $fn
     * @return array
     */
    protected function arguments(callable &$fn): array
    {
        return array_map(function (&$item) {
            switch (strtolower($item->name)) {
                case 'owner':
                    $item->value = &$this->owner;
                    break;
                case 'self':
                    $item->value = &$this;
                    break;
                case 'params':
                    $item->value = &$this->params;
                    break;
                case 'raw':
                    $item->value = &$this->raw;
                    break;
                default:
                    $name = explode(self::KEY_SEPARATOR, strtolower($this->name));
                    if (strtolower($item->name) == end($name) || (!empty($this->alias) && strtolower($item->name) == strtolower(\Application\PDA::field($this->alias))) ) {
                        $item->value = &$this->value;
                    } else {
                        $item->value = null;
                    }
            } return $item->value;
        }, (new \ReflectionFunction($fn))->getParameters());
    }

    /**
     * filter
     *
     * @param array $a
     * @param callable|null $cb
     * @param int $flag
     * @return array
     */
    public static function filter(array $a, callable $cb = null, $flag = ARRAY_FILTER_USE_BOTH): array
    {
        if (is_null($cb)) $cb = function($v) { return ($v instanceof \Application\Parameter) ? !$v->is_null(\PDO::NULL_EMPTY_STRING) : ($v !== null && $v !== ''); };

        return array_filter($a, $cb, $flag);
    }

    /**
     * Приводим ключи массива к нормализованному виду
     *
     * @param array $a
     * @return array
     */
    public static function array_keys_normalization(array $a): array
    {
        $data = [];
        foreach ($a as $k=>$v) {
            $keys = explode(self::KEY_SEPARATOR, $k);
            $data[end($keys)] = $v;
        }
        return $data;
    }

    /**
     * Проверяем является массив ассоциативным
     *
     * @param array $a
     * @return bool
     */
    public static function is_assoc(array $a): bool
    {
        return array_keys($a) !== range(0, count($a) - 1);
    }

    /**
     * @function is_null
     *
     * @return boolean
     */
    public function is_null($opt=null): bool
    {
        $val = $this->getValue($opt === null ?  $this->opt : $opt);
        return is_null($val);
    }

    /**
     * @function count
     *
     * @return int|null
     */
    public function count(): ?int
    {
        $val = $this->getValue();
        if ((is_array($val) || $val instanceof \Countable)) return count($val);
        return null;
    }

    /**
     * @method array2str
     *
     * @param array $a
     * @return string|null
     */
    public static function array2str (array $a, $opt=0): ?string
    {
        $str = implode(self::glue, array_map(function ($v) use($opt) {
            return (is_array($v) || $v instanceof \Countable) ? self::array2str($v, $opt) : self::ize($v, $opt);
        }, $a));
        return  ($opt & \Application\PDA::QUERY_ARRAY_SEQUENCE) ? $str : '[' . $str . ']';
    }

    /**
     * @function __toString
     *
     * @return string | null
     */
    public function __toString(): string
    {
        if (is_callable($this->formatter)) {
            return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));
        }

        if (is_array($this->value) || $this->value instanceof \Countable) {
            return $this->array2str($this->value, $this->opt);
        } elseif (($this->opt & \Application\PDA::QUERY_ARRAY_SEQUENCE) && preg_match('/^\s*\[(.*)\]\s*$/', $this->value, $matches) ) {
            return $matches ? $matches[1] : $this->value;
        }

        return $this->value !== null && is_scalar($this->value) ? strval($this->value) : '';
    }

    /**
     * @function __toInt
     *
     * @return int | mixed | null
     */
    public function __toInt($opt=null)
    {
        if ( is_callable($this->formatter) ) {
            return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));
        }

        if ( $this->value !== null && $this->value !=='' && is_scalar($this->value) ) {
            $val = is_int($this->value) ? $this->value : intval(filter_var($this->value, FILTER_SANITIZE_NUMBER_INT));
            return $val;
        }

        return null;
    }

    /**
     * @function __toFloat
     *
     * @return float | mixed | null
     */
    public function __toFloat($opt=null)
    {
        if (is_callable($this->formatter)) {
            return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));
        }

        if ( $this->value !== null && $this->value !=='' && is_scalar($this->value) ) {
            $val = is_float($this->value) ? $this->value : floatval(filter_var($this->value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            return $val;
        }

        return null;
    }

    /**
     * @function __toFloat
     *
     * @return array | mixed | null
     */
    public function __toArray($opt=0): ?array
    {
        if (is_callable($this->formatter)) return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));

        if (is_array($this->value) || $this->value instanceof \Countable) {
            return array_map(function ($v) use ($opt){ return self::ize($v, $opt); }, $this->value);
        } else {
            if (is_string($this->value)) {
                // JSON_OBJECT_AS_ARRAY | JSON_INVALID_UTF8_IGNORE
                if (preg_match('/^\s*\[.*\]\s*$/', $this->value)) return json_decode($this->value, false, 512,JSON_OBJECT_AS_ARRAY | JSON_INVALID_UTF8_IGNORE) ?? null;
                return explode(',', $this->value);
            }
        }
        return [self::ize($this->value, $opt)];
    }

    /**
     * @function __toJSON
     *
     * @param string $json
     * @param bool $assoc
     * @param int $depth
     * @param int $options
     * @return null|object
     */
    public function __toJSON($opt = [ 'assoc'=>false, 'mode'=>\Application\Jsonb::JSON_ALWAYS ])
    {
        if (is_callable($this->formatter)) {
            return call_user_func_array($this->formatter->bindTo($this->params), $this->arguments($this->formatter));
        }

        return (new \Application\Jsonb($this->value, $opt))->get();
    }

    /**
     * \JsonSerializable interface release
     * @return mixed|null
     */
    public function jsonSerialize()
    {
//        return self::ize($this->value);
        return $this->getValue(\PDO::NULL_NATURAL | \Application\PDA::ADDSLASHES);
    }

    /**
     * @param null $opt
     * @return array|float|int|null|string
     */
    public function getValue($option=null)
    {
        $opt = $option === null ? $this->opt : $option;

        $val = null;
        if ($this->isValid) switch (strtolower($this->type)) {
            case 'file':
                $val = new \Application\Jsonb($_FILES[$this->name]);
                break;
            case 'date':
                if (empty($this->value)) {
                    $val = null;
                } else {
                    if (preg_match('/^(0|1|2|3)*\d.(0|1)\d.\d{4}$/', $this->value)) {
                        $dt = implode('-', array_reverse(explode('.', $this->value)));
                    } elseif (preg_match('/^(\d{4}-(0|1)\d-(0|1|2|3)\d).*$/', $this->value, $match)) {
                        $dt = $match[1];
                    }
                    if ($opt & \Application\PDA::QUERY_STRING_QUOTES ) { $val = "'$dt'"; } else { $val = "$dt";}
                }
                break;
            case 'json':
                $val = $this->__toJSON(is_array($opt) ? $opt : ['assoc' => true, 'mode' => \Application\Jsonb::JSON_ALWAYS]);
                if ($opt & \Application\PDA::OBJECT_STRINGIFY || $opt & \Application\PDA::OBJECT_STRINGIFY ) {
                    $val = json_encode($val, JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                }
                break;
            case 'array':
                $val = $this->__toArray($opt);
                if ($opt & \Application\PDA::OBJECT_STRINGIFY) {
                    $val = json_encode($val, JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                }
                break;
            case 'bool':
                $val = boolval($this->value) ? 1 : 0;
                break;
            case 'float':
                $val =  $this->__toFloat($opt);
                break;
            case 'int':
                $val = $this->__toInt($opt);
                break;
            case 'string':
            default:
                $val = $this->__toString();
                if (empty($val)) {
                    $val = ($opt & \PDO::NULL_EMPTY_STRING) ?  null : 'null' ;
                } else {
                    if ($opt & \Application\PDA::ADDSLASHES) $val = addslashes($val);
                    if ($opt & \Application\PDA::QUERY_STRING_QUOTES ) $val = "'".\Application\PDA::pg_escape_string($val)."'";
                }
        }
        return $val;
    }

    /**
     * @function ize
     *
     * @param $param
     * @param int $opt
     * @return array|float|int|null|string
     */
    public static function ize ($param, $opt = \PDO::NULL_NATURAL)
    {
        if (is_callable($param)) return $param;

        if ($param instanceof \Application\Parameter) return $param->getValue($param->opt === null ? ($opt | \Application\PDA::ADDSLASHES | \PDO::NULL_EMPTY_STRING) : $param->opt );

        switch (gettype($param)) {
            case 'array':
                $val = ($opt & \Application\PDA::ARRAY_STRINGIFY) ? new \Application\Jsonb($param) :
                array_map(function ($v) { return self::ize($v, \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY ); }, $param);
                break;
            case 'NULL':
                $val = null; // 'null' JSON QOUTER
                break;
            case 'boolean':
                $val = boolval($param) ? 1 : 0;
                break;
            case 'double':
                $val = floatval($param);
                break;
            case 'integer':
                $val = intval($param);
                break;
            case 'object':
                if (is_callable($param)) {
                    $val = null;
                } elseif ($opt & \Application\PDA::OBJECT_STRINGIFY) {
                    $val = strval($param);
                    if ($opt & \Application\PDA::ADDSLASHES) $val = addslashes($val);
                    if ($opt & \PDO::NULL_EMPTY_STRING) $val = ($val === '' ? null : $val);
                } else {
//                    $val = json_encode($param, JSON_FORCE_OBJECT | JSON_NUMERIC_CHECK);
                    $val = json_encode($param, JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                }
                break;
            case 'string':
                if ( is_numeric($param) ) {
                    $folat = floatval($param); $val = $folat != intval($folat) ? floatval($param) : intval($param);
                } else {
                    $val = strval($param);
                    if ($opt & \Application\PDA::ADDSLASHES) $val = addslashes($val);
                    if (empty($val) && $opt & \PDO::NULL_EMPTY_STRING) $val = null;
                    if ($opt & \Application\PDA::QUERY_STRING_QUOTES)  $val = "'$val'";
//                    {
//                        if ($val !== null) $val = "'$val'"; //elseif (!($opt & \PDO::NULL_EMPTY_STRING)) $val = 'null';
//                    }
                }
                break;
            default:
                $val = strval($param);
                if (empty($val) && $opt & \PDO::NULL_EMPTY_STRING) $val = null;
                if ($val && ($opt & \Application\PDA::QUERY_STRING_QUOTES)) $val = "'".\Application\PDA::pg_escape_string($val)."'";
        }

        return $val;
    }

    /**
     * @magicmethod __set Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __set ($name, $value)
    {
        if (($this->type === 'array' || $this->type === 'json') && is_string($this->value)) { $this->value = $this->getValue(); }
        if (is_array($this->value)) $this->value[$name] = $value; elseif (is_object($this->value)) $this->value->{$name} = $value;
        else $this->{$name} = $value;
    }

    /**
     * Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ( $name )
    {
        $v = $this->getValue();
        if (is_array($v) && key_exists($name,$v)) return $v[$name];
        elseif (is_object($v) && property_exists($v, $name)) return $v->{$name};

        return null;
    }

    /**
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array
    {
        return [$this->alias ?? $this->name => $this->getValue($this->opt === null ? (\PDO::NULL_NATURAL | \Application\PDA::ADDSLASHES) : $this->opt)];
    }

    /**
     * Prepare for vardump() resutl;
     * @return array
     */
    public function __debugInfo() {
        return [ $this->alias ?? $this->name => $this->getValue($this->opt === null ? (\PDO::NULL_NATURAL | \Application\PDA::ADDSLASHES) : $this->opt) ];
    }

}
?>