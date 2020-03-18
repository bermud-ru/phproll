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
    public $params = null;
    public $value = null;
    public $original = null;

    const MESSAGE = "Parameter error, %(name)s = %(value)s is wrong!";
    /**
     * Parameter constructor
     *
     * @param $parent \Application\Rest
     * @param array $opt
     */
    public function __construct( array $opt, array &$params )
    {
        $this->params = &$params;
        foreach ($opt as $k => $v) { if (property_exists($this, $k)) $this->{$k} = $v; }

        $this->raw = isset($params[$this->alias]) ? $params[$this->alias] : (isset($params[$this->name]) ? $params[$this->name] : null);
        $this->value = is_null($this->raw) ? (is_callable($this->default) ? call_user_func_array($this->default, $this->arguments($this->default)) : $this->default) : $this->raw ;

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

        if (is_callable($this->before)) $this->value = call_user_func_array($this->before, $this->arguments($this->before));

        if (is_callable($this->required)) $this->required = call_user_func_array($this->required, $this->arguments($this->required));

        if ($this->required && (is_null($this->value) || $this->value === '')) {
            $this->isValid = false;
        } elseif (!empty($this->validator) && ($this->required || !empty($this->value))) {
            if (is_callable($this->validator)) {
                $this->isValid = call_user_func_array($this->validator, $this->arguments($this->validator));
            } elseif (is_string($this->validator) && !preg_match($this->validator, $this->value)) {
                $this->isValid = false;
            }
        }

        if ($this->isValid) {
            if (is_callable($this->after)) $this->value = call_user_func_array($this->after, $this->arguments($this->after));

            $this->params[$this->alias ? $this->alias : $this->name] = $this;
            $this->original = $this->name;
//        $this->params[($this->alias ? preg_replace('/\(.*\)/U', $this->name , $this->alias) : $this->name)] = $this;
        } else {
            $this->setMessage($opt['message'] ?? \Application\Parameter::MESSAGE, ['name' => $this->name, 'value' => $this->value]);
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
        if (\Application\PHPRoll::is_assoc($fields)) {
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
     * @param $p
     */
    protected function property($name, $default = null)
    {
        if (!property_exists($this, $name)) {
            trigger_error("Application\Parameter::$name not exist!", E_USER_WARNING);
            return $default;
        }

        $result = &$this->{$name};
        if (is_callable($result)) {
            $result = call_user_func_array($result, $this->arguments($result));
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
                    $name = explode(\Application\PHPRoll::KEY_SEPARATOR, strtolower($this->name));
                    if (strtolower($item->name) == end($name) || (!empty($this->alias) && strtolower($item->name) == strtolower(\Application\PDA::field($this->alias))) ) {
                        $item->value = &$this->value;
                    } else {
                        $item->value = null;
                    }
            } return $item->value;
        }, (new \ReflectionFunction($fn))->getParameters());
    }

    /**
     * @function setMessage
     * Set error message
     *
     * @param $e
     */
    public function setMessage($message, $opt):\Application\Parameter
    {
        $this->alert = \Application\PHPRoll::formatter($message ? $message: "Parameter error %(name)s!", $opt);
        if (!$this->owner) trigger_error($this->alert, E_USER_WARNING);
        return $this;
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
     * @function is_null
     *
     * @return boolean
     */
    public function is_null($opt=null): bool
    {
        $val = $this->getValue($opt === NULL ?  $this->opt : $opt);
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
     * array_to_string
     *
     * @param array $a
     * @return string|null
     */
    public function array_to_string (array $a, $opt=null): ?string
    {
        $str = implode(',', array_map(function ($v) {
            return (is_array($v) || $v instanceof \Countable) ? $this->array_to_string($v, $opt) : \Application\Parameter::ize($v);
        },  $a));
        return  (!$this->is_null($opt) && $opt & \Application\PDA::QUERY_ARRAY_SEQUENCE) ? $str : '[' . $str . ']';
    }

    /**
     * @function __toString
     *
     * @return string | null
     */
    public function __toString(): ?string
    {

        if (is_callable($this->formatter)) {
            return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));
        }


        if (is_array($this->value) || $this->value instanceof \Countable) {
            return $this->array_to_string($this->value, $this->opt);
        } elseif (($this->opt & \Application\PDA::QUERY_ARRAY_SEQUENCE) && preg_match('/^\s*\[(.*)\]\s*$/', $this->value, $matches) ) {
            return $matches ? $matches[1] : $this->value;
        }

        return $this->value !== NULL && is_scalar($this->value) ? strval($this->value) : null;
    }

    /**
     * @function __toInt
     *
     * @return int | mixed | null
     */
    public function __toInt($opt)
    {
        if ( is_callable($this->formatter) ) {
            return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));
        }

        if ( $this->value !== NULL && $this->value !=='' && is_scalar($this->value) ) {
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
    public function __toFloat($opt)
    {
        if (is_callable($this->formatter)) {
            return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));
        }

        if ( $this->value !== NULL && $this->value !=='' && is_scalar($this->value) ) {
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
    public function __toArray($opt)
    {
        if (is_callable($this->formatter)) return call_user_func_array($this->formatter->bindTo($this), $this->arguments($this->formatter));

        if (is_array($this->value) || $this->value instanceof \Countable) {
            return array_map(function ($v) { return \Application\Parameter::ize($v, $opt); }, $this->value);
        } else {
            if (is_string($this->value)) {
                if (preg_match('/^\s*\[.*\]\s*$/', $this->value)) return json_decode($this->value);
                return explode(',', $this->value);
            }
            return [\Application\Parameter::ize($this->value, $opt)];
        }

        return $this->value !== NULL ? [] : null;
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
            return call_user_func_array($this->formatter, $this->arguments($this->formatter));
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
        $opt = $option === NULL ? $this->opt : $option;
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
                $val = $this->__toJSON(is_array($opt) ? $opt : ['assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS ]);
                break;
            case 'array':
                $val = $this->__toArray($opt);
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
                if (!empty($val)) {
                    if ($opt & \Application\PDA::ADDSLASHES) $val = addslashes($val);
                    if ($opt & \Application\PDA::QUERY_STRING_QUOTES ) $val = "'$val'";
                } elseif ($opt & \PDO::NULL_EMPTY_STRING) { $val = NULL; }
                elseif (($val === NULL || $val === '') && !($opt & \PDO::NULL_EMPTY_STRING)) { $val = 'NULL'; }
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
        if ($param instanceof \Application\Parameter) return $param->getValue($param->opt === NULL ? ($opt | \Application\PDA::ADDSLASHES) : $param->opt );

        switch (gettype($param)) {
            case 'array':
                $val = array_map(function ($v) { return self::ize($v, \PDO::NULL_EMPTY_STRING | \Application\PDA::OBJECT_STRINGIFY); }, $param);
                break;
            case 'NULL':
                $val = null;
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
                    $val = json_encode($param, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                }
                break;
            case 'string':
                if ( is_numeric($param) ) {
                    $folat = floatval($param); $val = $folat != intval($folat) ? floatval($param) : intval($param);
                } else {
                    $val = strval($param);
                    if ($opt & \Application\PDA::ADDSLASHES) $val = addslashes($val);
                    if ($opt & \PDO::NULL_EMPTY_STRING) $val = ($val === '' ? null : $val);
                    if ($opt & \Application\PDA::QUERY_STRING_QUOTES) {
                        if ($val !== null) $val = "'$val'"; elseif (!($opt & \PDO::NULL_EMPTY_STRING)) $val = 'NULL';
                    }
                }
                break;
            default:
                $val = strval($param);
                if ($opt & \PDO::NULL_EMPTY_STRING) $val = ($val === '' ? null : $val);
                if (($opt & \Application\PDA::QUERY_STRING_QUOTES) && $val !== null) $val = "'$val'";
        }

        return $val;
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
        
        if ( $v instanceof \Application\Jsonb ) return $v->get($name);
        elseif (is_array($v) && key_exists($name,$v)) return $v[$name];
        elseif (is_object($v) && property_exists($v, $name)) return $v->{naem};

        return null;
    }

    /**
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array
    {
        return [$this->alias ?? $this->name => $this->getValue($this->opt === NULL ? (\PDO::NULL_NATURAL | \Application\PDA::ADDSLASHES) : $this->opt)];
    }

    /**
     * Prepare for vardump() resutl;
     * @return array
     */
    public function __debugInfo() {
        return [ $this->alias ?? $this->name => $this->getValue($this->opt === NULL ? (\PDO::NULL_NATURAL | \Application\PDA::ADDSLASHES) : $this->opt) ];
    }

}
?>