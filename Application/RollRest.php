<?php
/**
 *  RollRest.php
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 11/11/2015
 */
namespace Application;

class RollRest implements \Application\IRollRest
{
    protected $_instance = null;
    protected $_method = 'GET';
    protected $_validator = null;
    protected $_helper = null;
    protected $_file = null;

    public $_result = null;
    public $_route = array();
    public $_data = null;
    public $_config = array();

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_instance = $this;
        $this->_method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->_result = new \Application\RollResult($this->_instance);
        $this->_validator = new \Application\RollValidator($this->_instance);
        $this->getHelper(@$this->_config['helper']);
    }

    /**
     * @param $cfg
     * @param $route
     * @return null | Object
     */
    protected function createInstance($cfg, $route) {
        if (isset($cfg['type'])) {
            $type = strtolower($cfg['type']);
            $class = '\Application\Extensions\\' . ucfirst($type);
            $object = new $class($cfg);
            if ($object instanceof \Application\IRollRest) return $object->instance($route);
        }

        return null;
    }

    /**
     * @param null $helper
     * @return null
     */
    protected function getHelper($helper){
        if (!empty($helper)) {
            try {
                $this->_instance->_helper = new $helper($this->_instance);
            } catch (\Exception $e){
                $this->_instance->_result->isError = 500;
                $this->_instance->_result->message = $e->getMessage();
            }
        }

        return $this->_instance->_helper;
    }

    /**
     * @param object $instance
     * @param array $methodExplain
     * @param array $params
     * @return mixed|null
     */
    protected function execMethod($instance, array $methodExplain, array $params) {
        try {
            if (is_object($instance) && count($methodExplain)) {
                $method = key(array_slice($methodExplain, 0, 1));
                return call_user_func_array(array($instance, $method), count($methodExplain[$method]) ? $methodExplain[$method] : $params);
            }
        } catch(\Exception $e) {
            $this->_instance->_result->isError = 500;
            $this->_instance->_result->message = $e->getMessage() . '; ';
        } finally {
            $this->_instance->_result->message .= 'Error in ' . get_class($instance) . '->' . (isset($method['method']) ? $method['method'] : 'NULL') . '(' . implode(',', array_keys($params)) . ')';
        }
        return null;
    }

    /**
     * @param $route
     * @return CRest|null
     */
    public function instance($route){
        $this->_route = $route;
        $instance = null;
        $cfg = $this->_config;
        do {
            if (!is_null($instance)){
                $this->_instance = $instance;
            }
            if (isset($route[1]) && is_array($cfg) && in_array($route[1], array_keys($cfg))) {
                $route = array_slice($route,1);
                $cfg = $cfg[$route[0]];
                $instance = $this->createInstance($cfg, $route);
                if (!empty($instance)) $instance->getHelper(@$cfg['helper']);
            }
        } while (!is_null($instance) && $instance !== $this->_instance);

        return $this->_instance;
    }

    /**
     * pattern
     *
     * @param String $pattern
     * @param Array $data
     * @return String
     */
    public static function pattern($pattern, array $data)
    {
        $result = '';
        if ($pattern && $data) {
            $keys = array_keys($data);
            $keysmap = array_flip($keys);
            $values = array_values($data);
            while (preg_match('/%\(([a-zA-Z0-9_ -]+)\)/', $pattern, $m)) {
                if (!isset($keysmap[$m[1]])) {
                    return $result;
                }
                $pattern = str_replace($m[0], '%' . ($keysmap[$m[1]] + 1) . '$', $pattern);
            }
            array_unshift($values, $pattern);
            $result = call_user_func_array('sprintf', $values);
        }
        return $result;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($this->_instance && method_exists($this->_instance, $name)) {
            return call_user_func_array(array($this->_instance, $name), $arguments);
        } elseif(!empty($this->_helper)) {
            return call_user_func_array(array($this->_helper, $name), $arguments);
        }
    }

    /**
     * @param array $params
     * @return mixed|null
     */
    public function __invoke()
    {
        $args = func_get_args();
        $params = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
        if (isset($this->_config['method'])) {
            $instance =  empty($this->_instance->_helper) ? $this->_instance : $this->_instance->_helper;
            return $this->execMethod($instance, $this->_config['method'], $params);
        } elseif (isset($this->_config[$this->_method])) {
            $route = array_merge(array(array_shift($this->_route), $this->_method), $this->_route);
            return $this->instance($route)->{$this->_method}($params);
        }

        return $this->_instance->{$this->_method}($params);
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function get(array $params)
    {
        return $this->_result(strtolower(@$this->_config['format']));
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function put(array $params)
    {
        return $this->_result(strtolower(@$this->_config['format']));
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function post(array $params)
    {
        return $this->_result(strtolower(@$this->_config['format']));
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function delete(array $params)
    {
        return $this->_result(strtolower(@$this->_config['format']));
    }

}
?>