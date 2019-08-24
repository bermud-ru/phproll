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

    private $json = null;
    private $error = JSON_ERROR_NONE;
    private $assoc = false;
    private $depth = 512;
    private $options = 0;
    private $mode = \Application\Jsonb::JSON_STRICT;
    private $default = [];
    private $owner = null;
    private $params = [];

    public $is_decoded = false;

    /**
     * Parameter constructor
     *
     * @param $parent \Application\Rest
     * @param array $opt
     */

    public function __construct( &$source, $opt = [] )
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
     * @param null $fields
     * @param $obj
     * @param null $default
     * @return array|mixed|\stdClass|string|null
     * @throws \Exception
     */
    private function getParam (&$fields=null, $obj, $default=null) {
        if (is_null($fields)) return $this->json;

        $fx = is_array($fields) ? $fields : explode('.', $fields);

        if (count($fx) > 1) {
            $field = array_shift($fx);
            if ( $this->assoc ? array_key_exists($field, $obj) : property_exists($obj, $field) ) {
                return $this->getParam($fx, $this->assoc ? $obj[$field] : $obj->{$field}, $default);
            } elseif ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
                throw new \Exception("\Application\Jsonb  ($field) not foudnd!");
            }
            return $default;
        }

        if ( $this->assoc ? array_key_exists($fx[0], $obj) : property_exists($obj, $fx[0]) ) {
            return $this->assoc ? $obj[$fx[0]] : $obj->{$fx[0]};
        } elseif ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception("\Application\Jsonb ({$fx[0]}) not foudnd!");
        }
        return $default;
    }

    /**
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
        if (array_key_exists($key, $this->params)) {
            $item = (new \Application\Parameter($this->params[$key], [$key=>$item]))->setOwner($this->owner);
        }

        return $item;
    }

    /**
     * call
     *
     * @param string $name
     * @param array $arguments
     * @param null $bind
     * @return mixed|string
     */
    private function call(string $name, array $arguments, $context = null)
    {
        $fn = $this->get($name);

        if ( is_callable($fn) ) {
            return is_null($context) ? call_user_func_array($fn, $arguments) : call_user_func_array($fn->bindTo($context), $arguments);
        } else {
            throw new \Exception("\Application\Jsonb->$name() method not foudnd!");
        }

        return $fn;
    }

//    /**
//     * Native property
//     *
//     * @param $name
//     * @return mixed
//     * @throws \Exception
//     */
//    public function __set ($name, $value)
//    {
//        if ($this->assoc) $this->json[$name] = $value; else $this->json->{$name} = $value;
//    }

    /**
     * Native property
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
     * Native method
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
     * \JsonSerializable interface release
     *
     * @return mixed|null
     */
    public function jsonSerialize()
    {
        return serialize($this->json);
    }

    /**
     * serialize rule
     *
     * @return array
     */
    public function __sleep(): array
    {
        return $this->assoc ? $this->json : [$this->json];
    }

    /**
     * Prepare for vardump() resutl;
     *
     * @return array
     */
    public function __debugInfo() {
        return $this->assoc ? $this->json : [$this->json];
    }

}