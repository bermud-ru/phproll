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
    protected $message = null;
    protected $required = false;
    protected $notValid = false;
    protected $before = null;
    protected $after = null;
    protected $formatter = null;
    protected $raw = null;
    protected $restore = false;

    public $params = null;
    public $value = null;

    const MESSAGE = "Parameter error, %(name)s = %(value)s is wrong!";
    /**
     * Parameter constructor
     *
     * @param $parent \Application\Rest
     * @param array $opt
     */
    public function __construct( array $opt, array &$params ){
        $this->params = &$params;

        foreach ($opt as $k => $v) { if (property_exists($this, $k)) $this->{$k} = $v; }

        $this->raw = isset($params[$this->alias]) ? $params[$this->alias] : (isset($params[$this->name]) ? $params[$this->name] : null);
        $this->value = is_null($this->raw) ? $this->default : $this->raw ;

        if (is_callable($this->before)) $this->value = call_user_func_array($this->before, $this->arguments($this->before));
        if (is_callable($this->required)) $this->required = call_user_func_array($this->required, $this->arguments($this->required));

        if ($this->required && (is_null($this->value) || $this->value === '')) {
            $this->setMessage($opt['message'] ?? \Application\Parameter::MESSAGE, ['name' => $this->name, 'value'=>strval($this->value)]);
        } else {
            if (empty($this->validator) && $this->type != 'string') {
                switch (strtolower($this->type)) {
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
            if (!empty($this->validator) && ($this->required || !empty($this->value))) {
                if (is_callable($this->validator)) {
                    $this->notValid = !call_user_func_array($this->validator, $this->arguments($this->validator));
                } elseif (is_string($this->validator) && !preg_match($this->validator, $this->value)) {
                    $this->notValid = true;
                }
                if ($this->notValid && !(isset($params['required']) && $this->required)) $this->setMessage($opt['message'] ?? \Application\Parameter::MESSAGE, ['name' => $this->name, 'value' => $this->value]);
            }

            if (is_callable($this->after)) $this->value = call_user_func_array($this->after, $this->arguments($this->after));
        }

        $this->params[$this->alias ? $this->alias : $this->name] = $this;
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
        return $this;
    }

    /**
     * @function count
     * 
     * @return int|null
     */
    public function count(): ?int
    {
        if (!empty($this->value) && (is_array($this->value) || $this->value instanceof \Countable)) return count($this->value);
        return null;
    }

    /**
     * @function __toString
     *
     * @return string
     */
    public function __toString(): ?string
    {
        if (is_callable($this->formatter)) return call_user_func_array($this->formatter, $this->arguments($this->formatter));
//                $a = implode(',', array_map(function ($v) { return $this->parameterize($v); }, $param));
//                $val = json_encode($a,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
//        elseif (is_array($this->value)) return json_encode($this->value);
        elseif (is_array($this->value)) return implode(',', array_map(function ($v) { return \Application\Parametr::ize($v); }, $this->value));

        if ($this->value === NULL) return NULL;
        return strval($this->value);
    }

    /**
     * @function __toInt
     *
     * @return int
     */
    public function __toInt(): ?int
    {
        if ($this->value === NULL) return NULL;

        $val = preg_replace('/[^0-9]/', '', $this->value);
        if (is_numeric($val)) return intval($val);

        trigger_error("Application\Parameter::__toInt() can't resolve numeric value!", E_USER_WARNING);
        return null;
    }

    /**
     * @function __toFloat
     *
     * @return int
     */
    public function __toFloat(): ?float
    {
        if ($this->value === NULL) return NULL;

        $val = (float) filter_var( $this->value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
        if (is_numeric($val)) return floatval($val);

        trigger_error("Application\Parameter::__toFloat() can't resolve numeric value!", E_USER_WARNING);
        return null;
    }

    /**
     * @function __toFloat
     *
     * @return int
     */
    public function __toArray(): ?array
    {
        if (is_array($this->value)) return $this->value; elseif (!empty($this->value)) return [$this->value];

        trigger_error("Application\Parameter::__toArray() can't resolve numeric value!", E_USER_WARNING);
        return null;
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
    public function __toJSON(bool $assoc = true , int $depth = 512 , int $options = 0): ?array
    {
        $json = json_decode($this->__toString(), $assoc, $depth, $options);
        return json_last_error() === JSON_ERROR_NONE ? $json : null;
    }

    /**
     * \JsonSerializable interface release
     * @return mixed|null
     */
    public function jsonSerialize()
    {
        return self::ize($this->value);
    }

    /**
     * @param null $opt
     * @return array|float|int|null|string
     */
    public function getValue($opt=null)
    {
        $val = null;
        switch (strtolower($this->type)) {
            case 'date':
                $val = empty($this->value) ? null : implode('-', array_reverse(explode('.', $this->value)));
                break;
            case 'json':
                $val = $this->__toJSON();
                break;
            case 'array':
                $val = $this->__toArray();
                break;
            case 'bool':
                $val = boolval($this->value) ? 1 : 0;
                break;
            case 'float':
                $val =  $this->__toFloat();
                break;
            case 'int':
                $val = $this->__toInt();
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
        if ($param instanceof \Application\Parameter) return $param->getValue($opt | \Application\PDA::ADDSLASHES);

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
                if ($opt & \Application\PDA::OBJECT_STRINGIFY) {
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
                    $folat = floatval($param); $val =  $folat != intval($folat) ? floatval($param) : intval($param);
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
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array
    {
        return [$this->alias ?? $this->name => self::ize($this->restore ? $this->raw : $this->value)];
    }

    /**
     * Prepare for vardump() resutl;
     * @return array
     */
    public function __debugInfo() {
        return [ $this->alias ?? $this->name => self::ize($this->restore ? $this->raw : $this->value) ];
    }
}
?>