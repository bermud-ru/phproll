<?php
/**
 * ContextException.php
 *
 * @category Exception
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 16/05/2018
 * @status beta
 * @version 0.1.3
 * @revision $Id: ContextException.php 0013 2018-05-16 1:04:01Z $
 *
 */

namespace Application;

class ContextException extends \Exception {
    protected $owner = null;
    protected $option = null;

    /**
     * ContextException constructor.
     * @param $owner
     * @param $pattern
     * @param $options
     */
    public function __construct($owner, $pattern, $options) {
        $this->owner = $owner;
        $this->option = $options;
        $p = is_array($pattern) ? implode(', ', $pattern) : $pattern;
        parent::__construct(get_class($owner) . " contex $p not exist!", $options['code']??'404', null);
    }

    /**
     * @function context
     *
     * @return mixed
     */
    public function context($tmpl=null) {
        $context = '';
        if ( $tmpl || isset($this->config['404']) )  {
            $context = $this->owner->context($tmpl??$this->config['404'], $this->option);
        } else {
            trigger_error("Application\ContextException::context() template or config['404'] not exist!", E_USER_WARNING);
        }

        if (array_key_exists('grinder', $options) && is_callable($options['grinder'])) {
            $context = call_user_func_array($options['grinder']->bindTo($this->owner), ['file' => $tmpl, 'contex' => $context,]);
        }

        return $context;
    }
}

?>