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
            throw new \Exception(__CLASS__." $source can't build JSON object!");
        } else {
            $this->json = new \stdClass();
        }
    }

    /**
     * Get value by recusion name
     *
     * @param $fields
     * @param string|null $default
     * @return string
     */
    public function v ($fields, string $default=null): string
    {
        $fields = explode('.', $field); $self = $this;

        function iterator($json, array &$fields, $default) use ($self) {
            if (count($field) > 1) {
                $field = array_shift($fields);
                if (property_exists($json, $field)) {
                    return iterator($json->{$field}, $fields, $default);
                } elseif ($self->mode & \Application\Jsonb::JSON_STRICT ) {
                    throw new \Exception(__CLASS__."->$field property not foudnd!");
                }

                return $default;
            }

            if (property_exists($json, $fields[0])) {
                return $json->{$fields[0]};
            } elseif ($self->mode & \Application\Jsonb::JSON_STRICT ) {
                throw new \Exception(__CLASS__."->{$fields[0]} property not foudnd!");
            }

            return $default;
        }

        return iterator($this->json, $fields, $default);

    }

    /**
     * Get valuea as String
     *
     * @param $field
     * @param string $default
     * @return string
     * @throws \Exception
     */
    public function str ($field, string $default=''): string
    {
        if (property_exists($this->json, $field) && is_scalar($this->json->{$field})) {
            return strval($this->json->{$field});
        } elseif ($this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception(__CLASS__."->$field property not foudnd!");
        }

        return $default;

    }

    /**
     * Get value as Int
     *
     * @param $field
     * @param int $default
     * @return int
     * @throws \Exception
     */
    public function int ($field, int $default=0): int
    {
        if (property_exists($this->json, $field) && is_scalar($this->json->{$field})) {
            return intval($this->json->{$name});
        } elseif ($this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception(__CLASS__."->$name property not foudnd!");
        }

        return $default;

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
        return $this->json->{$name} = $value;
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
        if (property_exists($this->json, $name)) {
            return $this->json->{$name};
        } elseif ($this->mode & \Application\Jsonb::JSON_STRICT ) {
            throw new \Exception(__CLASS__."->$name property not foudnd!");
        } elseif (isset($this->default[$name])) {
            return $this->default[$name];
        }

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
        if (method_exists($this->json, $name)) return call_user_func_array([$this->json, $name], $arguments);
        throw new \Exception(__CLASS__."->$name(...) method not foudnd");
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
        return [$this->json];
    }

    /**
     * Prepare for vardump() resutl;
     *
     * @return array
     */
    public function __debugInfo() {
        return [ $this->json ];
    }

}