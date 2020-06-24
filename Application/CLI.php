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
    public $cron = null;

    private $__running = false;
    public $processPID = null;

    protected static $scriptID;
    public  $path;
    public  $file;

    protected $max_threads = 10;
    protected $threads = [];
    private $threads_timeout = 1;

    /**
     * Конструктор
     *
     * @param $config данные из файла конфигурации
     */
    public function __construct($params, $bootstrap = null)
    {
        $this->path = pathinfo($bootstrap ?? __FILE__, PATHINFO_DIRNAME);
        $this->file = pathinfo($bootstrap ?? __FILE__, PATHINFO_BASENAME);
        $this->cfg = new \Application\Jsonb($params, ['owner'=>$this]);

        static::$scriptID = get_class($this);

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
                $this->processPID = trim(file_get_contents($this->pidfile));
                // yum install -y php-process
                if (posix_kill($this->processPID, 0)) {
                    $this->__running = true;
                }
            }

            if (!$this->__running) {
                $this->processPID = getmypid();
                file_put_contents($this->pidfile, $this->processPID);
                if ($bootstrap && $this->cron && is_string($this->cron)) $this->crontab(str_replace('__FILE__', $bootstrap, $this->cron));
            }
        }
    }

    /**
     * Crontab manager
     *
     * @param $rules
     * @param bool $update
     */
    final static function crontab($rules, $update = true) {
        if (!static::$scriptID) error_log(__CLASS__.': scriptID not defined!',E_USER_WARNING);

        $tasks = @shell_exec('crontab -l') ?? '';
        $tmp = md5($rules);
        $setup = false;
        $scriptID = str_replace(['\\','/'], '~', self::$scriptID);
        if (empty($tasks) || !preg_match_all("/#task:$scriptID:(.*)\r*\n*$/m", $tasks, $matches)) {
            $setup = true;
        } else {
            $setup = isset($matches[1]) ? $matches[1] != $tmp : true;
        }
        if (!$setup) return;

        if (isset($matches[1])) {
            $m = is_array($matches[1]) ? $matches[1] : [$matches[1]];
            foreach ( $m as $k => $v) $tasks = preg_replace("/^.+{$v}\r*\n*$/m", '', $tasks);
        }

        if ($update) {
            $task_id = "#task:$scriptID:{$tmp}";
            file_put_contents("/tmp/{$tmp}.tmp", $tasks . $rules . " " . $task_id . "\n");
        }

        if (exec("crontab /tmp/{$tmp}.tmp")) unlink("/tmp/{$tmp}.tmp");
    }

    /**
     * Проверяет запущел экземпляр класса
     * @return bool
     */
    public function is_running() {
        return $this->__running ? $this->processPID : false;
    }

    /**
     * Деструктор
     *
     */
    public function __destruct() {
        if (!$this->__running && $this->pidfile && file_exists($this->pidfile)) {
            @unlink($this->pidfile);
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

    /**
     * threading
     *
     * @param int $max
     */
    final function threading(int $max=10)
    {
        $this->max_threads = $max;

        while ( true ) {
            while ( count($this->threads) >= $this->max_threads ) {
//                sleep($this->threads_timeout);
                foreach ( $this->threads as $pid => $flag ) {
                    $res = pcntl_waitpid($pid, $status, WNOHANG); // слушаем статус детей
                    if ( $res == -1 || $res > 0 ) unset($this->threads[$pid]);
                }
                continue;
            }

            $this->launcher();
        }
    }

    /**
     * @return bool
     *
     */
    private function launcher() {
        $pid = pcntl_fork();
        if ($pid == -1) {
            trigger_error('Could not launch new job, exiting', E_USER_WARNING);
            return false;
        } else if ($pid) {
            $this->threads[$pid] = $pid; // храним список детей для прослушки
        } else {
            $this->run();
            exit(0);
        }
        return true;
    }

}
