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


class Jsonb implements \JsonSerializable, \Countable, \Iterator
{
    const JSON_ALWAYS = 0;
    const JSON_STRICT = 1;
    const JSON_STRINGIFY = 2;
    const JSON_EXCLUDE_EMPTY = true;

    protected $__json = null;
    protected $__error = JSON_ERROR_NONE;
    protected $__assoc = false;
    protected $__depth = 512;
    protected $__options = 0;
    protected $__mode = self::JSON_STRICT;
    protected $__owner = null;

    public $is_decoded = false;

    /**
     * @constructor
     *
     * @param array $source
     * @param array $opt
     */

    public function __construct( $source, $opt = [] )
    {
        foreach ($opt as $k => $v) {
            switch (strtolower($k)) {
                case 'owner':
                    $this->__owner = is_null($v) ? $this : $v;
                    break;
                case 'assoc':
                    $this->__assoc = boolval($v);
                    break;
                case 'depth': case 'options': case 'mode':
                    $this->{'__'.$k} = intval($v);
                    break;
                default:
                    if (property_exists($this, $k)) $this->{$k} = $v;
            }
        }

        if ( is_null($source) || is_string($source) ) {
            $this->__json = json_decode($source, $this->__assoc, $this->__depth, $this->__options);
            $this->__error = json_last_error();
            $this->is_decoded = $this->__error !== JSON_ERROR_NONE;
        } elseif ( is_object($source) ) {
            $this->__assoc = false;
            $this->__json = $source;
        } elseif ( is_array($source) ) {
            $this->__assoc = true;
            $this->__json = $source;
        }

        if ($this->__error !== JSON_ERROR_NONE) if ( $this->__mode & self::JSON_STRICT ) {
            throw new \Exception(__CLASS__." $source can't build JSON " . ($this->__assoc ? "assoc array!" : "object!"));
        } else {
            $this->__json = $this->__assoc ? [] : new \stdClass();
        }

    }

    /**
     * @method \Countable
     * @return mixed
     */
    public function count()
    {
        return $this->__assoc ? count($this->__json) :  0;
    }

    /**
     * @method \Iterator
     * @return mixed
     */
    public function rewind() {
        reset($this->__json);
    }

    /**
     * @method \Iterator
     * @return mixed
     */
    public function current() {
        return \Application\Parameter::ize(current($this->__json), \PDO::NULL_EMPTY_STRING);
    }

    /**
     * @method \Iterator
     * @return mixed
     */
    public function key() {
        return (string) key($this->__json);
    }

    public function next() {
        next($this->__json);
    }

    /**
     * @method \Iterator
     * @return mixed
     */
    public function valid() {
        return key($this->__json) !== null;
    }

    /**
     * @method getParam
     * Get value by recusion name
     *
     * @param $fields
     * @param object $obj
     * @param null $default
     * @return array|mixed|\stdClass|string|null
     * @throws \Exception
     */
    protected function getParam ($fields, $src, $default=null)
    {
        $fx = is_array($fields) ? $fields : explode(\Application\Parameter::KEY_SEPARATOR, $fields);

        if (count($fx) > 1) {
            $field = array_shift($fx);
            if ( $this->__assoc ? array_key_exists($field, $src) : property_exists($src, $field) ) {
                return $this->getParam($fx, $this->__assoc ? $src[$field] : $src->{$field}, $default);
            } elseif ( $this->__mode & self::JSON_STRICT ) {
                throw new \Exception("\Application\Jsonb param($field) not foudnd!");
            }
            return $default;
        }
       
        if ( $this->__assoc ? array_key_exists($fx[0], $src) : property_exists($src, $fx[0]) ) {
            return $this->__assoc ? $src[$fx[0]] : $src->{$fx[0]};
        } elseif ( $this->__mode & self::JSON_STRICT ) {
            throw new \Exception("\Application\Jsonb param ({$fx[0]}) not foudnd!");
        }
        return $default;
    }

    /**
     * @method get
     *
     * @param array|string|null $fields
     * @param mixed|null $default
     * @param bool $excludeEmpty
     * @return mixed
     */
    public function get ($fields=null, $default=null, bool $excludeEmpty=false)
    {
        if (is_string($fields)) {
            return $this->getParam($fields, $this->__json, $default);
        } elseif (is_array($fields)) {
            $fields = array_flip($fields);
            foreach ($fields as $k => $v) {
                $fields[$k] = $this->getParam($k, $this->__json, is_array($default) ? $default[$k] : null);
                if ($excludeEmpty && (is_null($f=\Application\Parameter::ize($fields[$k], \PDO::NULL_EMPTY_STRING)) || $f === '')) unset($fields[$k]);
            }
            return $fields;
        }

        return $excludeEmpty && $this->__assoc ? array_filter($this->__json, function($i) {
            $v = \Application\Parameter::ize($i,\PDO::NULL_EMPTY_STRING);
            return !is_null($v) && $v !== '';
        }) : $this->__json;
    }

    /**
     * @method find
     *
     * @param string $pattern
     * @param string|null $with
     * @return mixed|null
     */
    public function find (string $pattern, string $with = null)
    {
        if ( $this->__assoc ) {
            $a = $with && array_key_exists($with, $this->__json) ? $this->__json[$with] : $this->__json;
            array_walk_recursive($a, function ($item, $key) use ($pattern) { if ($pattern === $key) return $item; });
        } else {
            return $this->$this->get(($with ? "$with." : '').$pattern);
        }
        return null;
    }

    /**
     * @method merge
     *
     * @param array $a
     * @return $this
     */
    public function merge(array $a)
    {
        if ( $this->__assoc ) {
            $this->__json = array_merge($this->__json, $a);
        } else {
            foreach ($a as $k=> $v) $this->__json->{$k} = $v;
        }
        return $this;
    }

    /**
     * @method delete
     *
     * @param $key
     */
    public function delete ($key)
    {
        if ( $this->__assoc ) {
            if (array_key_exists($key, $this->__json)) unset($this->__json[$key]);
        } else if (property_exists($this->__json, $key) || method_exists($this->__json, $key)) {
            unset($this->__json->{$key});
        }
    }

    /**
     * @magicmethod __invoke
     *
     * @param null $excludeEmpty
     * @param null $fields
     * @param null $default
     * @return array|callable|\stdClass|string|null
     */
    public function &__invoke(bool $excludeEmpty=false, $fields=null, $default=null )
    {
        $result = $this->get($fields, $default, $excludeEmpty);
        return $result;
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
        if ($this->__assoc) $this->__json[$name] = $value; else $this->__json->{$name} = $value;
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
        return \Application\Parameter::ize($this->get($name));
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
        $fn = $this->get($name);

        if ( is_callable($fn) ) {
            return is_null($this->__owner) ? call_user_func_array($fn, $arguments) : call_user_func_array($fn->bindTo($this->__owner), $arguments);
        }

        throw new \Exception("\Application\Jsonb->$name() method not foudnd!");
    }

    /**
     * @method __toString
     *
     * @return string | null
     */
    public function __toString(): string
    {
        return json_encode($this->__assoc ?
            $this->__json : json_decode($this->__json, true),
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
        return $this->__assoc ? $this->__json : json_decode($this->__json, true);
    }

    /**
     * @method __sleep
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array
    {
        return $this->__assoc ? $this->__json : json_decode($this->__json, true);
    }

    /**
     * @method __debugInfo
     * Prepare for vardump() resutl;
     *
     * @return array
     */
    public function __debugInfo() {
        return $this->__assoc ? $this->__json : json_decode($this->__json, true);
    }

}