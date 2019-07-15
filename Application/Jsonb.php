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

    /**
     * Parameter constructor
     *
     * @param $parent \Application\Rest
     * @param array $opt
     */

    public function __construct( string $source, $opt = [] )
    {
        foreach ($opt as $k => $v) {
            switch (strtolower($k)) {
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

        $this->json = json_decode($source, $this->assoc, $this->depth, $this->options);
        $this->error = json_last_error();
        if ($this->error !== JSON_ERROR_NONE) if ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception(__CLASS__." $source can't build JSON " . ($this->assoc ? "assoc array!" : "object!"));
        } else {
            $this->json = $this->assoc ? [] : new \stdClass();
        }
    }

    /**
     * Get value by recusion name
     *
     * @param $fields
     * @param string|null $default
     * @return string
     */
    public function v ($fields=null, $default=null)
    {
        if (is_null($fields)) return $this->json;

        $fx = is_array($fields) ? $fields : explode('.', $fields);

        if (count($fx) > 1) {
            $field = array_shift($fx);
            if ( $this->assoc ? array_key_exists($field, $this->json) : property_exists($this->json, $field) ) {
                return $this->v($fx, $default);
            } elseif ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
                throw new \Exception("\Application\Jsonb  ($field) not foudnd!");
            }
            return $default;
        }

        if ( $this->assoc ? array_key_exists($fx[0], $this->json) : property_exists($this->json, $fx[0]) ) {
            return $this->assoc ? $this->json[$fx[0]] : $this->json->{$fx[0]};
        } elseif ( $this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception("\Application\Jsonb ({$fx[0]}) not foudnd!");
        }
        return $default;
    }

    /**
     * Get valuea as String
     *
     * @param $field
     * @param string $default
     * @return string
     * @throws \Exception
     */
    public function str ($field, string $default=''): ?string
    {
        $s = $this->v($field);
        return $s !== null && is_scalar($s) ? strval($s) : $default;
    }

    /**
     * Get value as Int
     *
     * @param $field
     * @param int $default
     * @return int
     * @throws \Exception
     */
    public function int ($field, int $default=0): ?int
    {
        $s = $this->v($field);
        return $s !== null && is_scalar($s) ? intval($s) : $default;
    }

    /**
     * Get value as Date
     *
     * @param $field
     * @param string $format
     * @return false|string|null
     */
    public function date ($field, string $format='Y-m-d H:i:s')
    {
        $s = $this->v($field);
        if (($s !== null && is_scalar($s)) && ($time = strtotime($s))) return date($format, $time);

        return null;
    }

    /**
     * Native property
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
     * Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ($name)
    {
        $v = $this->v($name);
        if (is_null($v) && isset($this->default[$name])) {
            return $this->default[$name];
        }

        return $v;
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
        if (!$this->assoc) {
            if (method_exists($this->json, $name)) return call_user_func_array([$this->json, $name], $arguments);
            throw new \Exception("\Application\Jsonb->$name() method not foudnd!");
        }

        return $this->v($name);
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