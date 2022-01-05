<?php
/**
 * Jsonb.php
 *
 * @category JSON helper, PHPRoll Framework (Backend RESTfull)
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 15/07/2019
 * @status beta
 * @version 0.0.1
 * @revision $Id: Jsonb.php 0004 2019-07-15 03:44:01Z $
 *
 */

namespace Application;


class Jsonb implements \JsonSerializable
{
    const JSON_ALWAYS = 0;
    const JSON_STRICT = 1;
    const JSON_STRINGIFY = 2;

    protected $json = null;
    protected $error = JSON_ERROR_NONE;
    protected $assoc = false;
    protected $depth = 512;
    protected $options = 0;
    protected $mode = \Application\Jsonb::JSON_STRICT;
    protected $default = [];
    protected $owner = null;
    protected $params = [];

    public $is_decoded = false;

    /**
     * @constructor
     *
     * @param $parent \Application\Rest
     * @param array $opt
     */

    public function __construct( $source, $opt = [] )
    {
        foreach ($opt as $k => $v) {
            switch (strtolower($k)) {
                case 'owner':
                    $this->owner = is_null($v) ? $this : $v;
                    break;
                case 'assoc':
                    $this->assoc = boolval($v);
                    break;
                case 'depth': case 'options': case 'mode':
                    $this->{$k} = intval($v);
                    break;
                default:
                    if (property_exists($this, $k)) $this->{$k} = $v;
            }
        }

        if ( is_null($source) || is_string($source) ) {
            $this->json = json_decode($source, $this->assoc, $this->depth, $this->options);
            $this->error = json_last_error();
            $this->is_decoded = $this->error !== JSON_ERROR_NONE;
        } elseif ( is_object($source) ) {
            $this->assoc = false;
            $this->json = $source;
        } elseif ( is_array($source) ) {
            $this->assoc = true;
            $this->json = $source;
        }

        if ($this->error !== JSON_ERROR_NONE) if ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception(__CLASS__." $source can't build JSON " . ($this->assoc ? "assoc array!" : "object!"));
        } else {
            $this->json = $this->assoc ? [] : new \stdClass();
        }

    }

    /**
     * @method getParam
     *
     * @param null $fields
     * @param $obj
     * @param null $default
     * @return array|mixed|\stdClass|string|null
     * @throws \Exception
     */
    protected function getParam (&$fields=null, $obj, $default=null)
    {
        if (is_null($fields)) return $this->json;

        $fx = is_array($fields) ? $fields : explode(\Application\PHPRoll::KEY_SEPARATOR, $fields);

        if (count($fx) > 1) {
            $field = array_shift($fx);
            if ( $this->assoc ? array_key_exists($field, $obj) : property_exists($obj, $field) ) {
                return $this->getParam($fx, $this->assoc ? $obj[$field] : $obj->{$field}, $default);
            } elseif ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
                throw new \Exception("\Application\Jsonb params  ($field) not foudnd!");
            }
            return $default;
        }
       
        if ( $this->assoc ? array_key_exists($fx[0], $obj) : property_exists($obj, $fx[0]) ) {
            return $this->assoc ? $obj[$fx[0]] : $obj->{$fx[0]};
        } elseif ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception("\Application\Jsonb param ({$fx[0]}) not foudnd!");
        }
        return $default;
    }

    /**
     * @method get
     * Get value by recusion name
     *
     * @param $fields
     * @param string|null $default
     * @return string
     */
    public function get ($fields=null, $default=null)
    {
        $item = $this->getParam($fields, $this->json, $default);

        if (is_callable($item) || is_null($fields)) return $item;

        $key = is_array($fields) ? \Application\PHPRoll::array_keys_normalization($fields) : strval($fields);
        if (array_key_exists($key, $this->params) && is_array($this->params[$key])) {
            $this->params[$key]['name'] = $key;
            $item = (new \Application\Parameter($this->params[$key], [$key=>$item]))->setOwner($this->owner);
        }

        return $item;
    }

    /**
     * @method find
     *
     * @param sting $pattern regex
     * @param string|null $with
     * @return mixed|null
     */
    public function find ($pattern, string $with = null)
    {
        if ( $this->assoc ) {
            $a = $with && array_key_exists($with, $this->json)? $this->json[$with] : $this->json;
            $keys = array_values(preg_grep($pattern, array_keys($a)));
            if (count($keys)) return array($a[$keys[0]],$keys[0]);
        } else {
            $a = get_object_vars($this->json);
            $keys = array_values(preg_grep($pattern, $a));
            if (count($keys)) return array($this->json->{$keys[0]},$keys[0]) ;
        }
        return null;
    }

    /**
     * @method delete
     *
     * @param $key
     */
    public function delete ($key)
    {
        if ( $this->assoc ) {
            if (array_key_exists($key, $this->json)) unset($this->json[$key]);
        } else if (property_exists($this->json, $key) || method_exists($this->json, $key)) {
            unset($this->json->{$key});
        }
    }

    /**
     * @magicmethod
     *
     * @param null $fields
     * @param null $default
     * @return array|callable|\stdClass|string|null
     */
    public function __invoke($fields=null, $default=null)
    {
        return $this->get($fields, $default);
    }

    /**
     * @magicmethod call
     *
     * @param string $name
     * @param array $arguments
     * @param null $bind
     * @return mixed|string
     */
    protected function call(string $name, array $arguments, $context = null)
    {
        $fn = $this->get($name);

        if ( is_callable($fn) ) {
            return is_null($context) ? call_user_func_array($fn, $arguments) : call_user_func_array($fn->bindTo($context), $arguments);
        } else {
            throw new \Exception("\Application\Jsonb->$name() method not foudnd!");
        }

        return $fn;
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
        if ($this->assoc) $this->json[$name] = $value; else $this->json->{$name} = $value;
    }

    /**
     * @magicmethod __get Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ( $name )
    {
        return $this->get($name);
    }

    /**
     * @magicmethod __call Native method
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->call($name, $arguments, $this->owner);
    }

    /**
     * @method __toString
     *
     * @return string | null
     */
    public function __toString(): string
    {
        return json_encode($this->assoc ?
            $this->json : json_decode($this->json, true),
            JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @method jsonSerialize
     * \JsonSerializable interface release
     *
     * @return mixed|null
     */
    public function jsonSerialize()
    {
        return $this->assoc ? $this->json : json_decode($this->json, true);
    }

    /**
     * @method __sleep
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array
    {
        return $this->assoc ? $this->json : json_decode($this->json, true);
    }

    /**
     * @method __debugInfo
     * Prepare for vardump() resutl;
     *
     * @return array
     */
    public function __debugInfo() {
        return $this->assoc ? $this->json : json_decode($this->json, true);
    }

}