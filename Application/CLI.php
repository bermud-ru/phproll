<?php
/**
 * CLI.php
 *
 * @category Console Line Interface Helper
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 11/06/2020
 * @status beta
 * @version 0.1.2
 * @revision $Id: CLI.php 0004 2020-06-11 23:44:01Z $
 *
 */

namespace Application;

abstract class CLI
{
    public $args = [];
    public $pidfile = null;

    private $__running = false;
    private $__PID = null;

    /**
     * Конструктор
     *
     * @param $config данные из файла конфигурации
     */
    public function __construct($params)
    {
        $this->path = pathinfo(__FILE__, PATHINFO_DIRNAME);
        $this->file = pathinfo(__FILE__, PATHINFO_BASENAME);
        $this->cfg = new \Application\Jsonb($params, ['owner'=>$this]);

        global $argv;
        for ($i = 1; $i < count($argv); $i++) {
            if (preg_match('/^--([^=]+)=(.*)$/', $argv[$i], $match)) {
                $this->args[$match[1]] = $match[2];
            } elseif (preg_match('/^-([a-zA-Z0-9])$/', $argv[$i], $match)) {
                $this->args[$match[1]] = true;
            } else {
                $this->args[$argv[$i]] = true;
            }
        }

        if ($this->pidfile = isset($params['pidfile']) ? $params['pidfile'] : $this->pidfile) {
            if (file_exists($this->pidfile)) {
                $this->__PID = trim(file_get_contents($this->pidfile));
                // yum install -y php-process
                if (posix_kill($this->__PID, 0)) {
                    $this->__running = true;
                }
            }

            if (!$this->__running) {
                $this->__PID = getmypid();
                file_put_contents($this->pidfile, $this->__PID);
            }
        }
    }

    /**
     * Проверяет запущел экземпляр класса
     * @return bool
     */
    public function is_running() {
        return $this->__running ? $this->__PID : false;
    }

    /**
     * Деструктор
     *
     */
    public function __destruct() {
        if (!$this->__running && $this->pidfile && file_exists($this->pidfile)) {
            unlink($this->pidfile);
        }
    }

    /**
     * CLI Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ( $name )
    {
        $value = null;
        switch (strtolower($name)) {
            case 'cfg':
                $value = $this->cfg;
                break;
            case (strpos($name, 'db') === 0 ? true: false):
                $value = isset($this->{$name}) ? $this->{$name} : ($this->{$name} = new \Application\PDA($this->cfg->{$name}));
                break;
        }
        return $value;
    }

    /**
     * run Abstract method
     *
     * @return mixed
     */
    abstract function run();

}
