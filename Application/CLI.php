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

if (version_compare(PHP_VERSION, "5.3.0", '<')) { declare(ticks = 1); }

abstract class CLI
{
    public $args = [];
    public $pidfile = null;
    public $threadfile = null;
    public $cron = null;

    private $__running = false;
    public $processPID = null;

    protected static $scriptID;
    public $path;
    public $file;

    const THREAD_DEFAULT = 0;
    const THREAD_INFINITY = 1;
    const THREAD_LAZYJOB = 2;
    const THREAD_EMPTY = 4;
    const THREAD_RESULTSET = 8;
    const THREAD_COMPLETE = 16;

    public $looper = true;
    public $max_threads = 0;
    public $thread_idx = 0;
    private $threads = [];
    private $threads_timeout = 0;

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
                } else {
                    @unlink($this->pidfile);
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
     * @function crontab( Crontab manager
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
            foreach ( $m as $k => $v ) $tasks = preg_replace("/^.+{$v}\r*\n*$/m", '', $tasks);
        }

        if ($update) {
            $task_id = "#task:$scriptID:{$tmp}";
            file_put_contents("/tmp/{$tmp}.tmp", $tasks . $rules . " " . $task_id . "\n");
        }

        if (exec("crontab /tmp/{$tmp}.tmp")) unlink("/tmp/{$tmp}.tmp");
    }

    /**
     * @function is_running(
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
        $pidfile = null;
        if ($this->pidfile) $pidfile = $this->thread_idx == 0 ? $this->pidfile : $this->pidfile .":" . $this->thread_idx;
        if (!$this->__running && $pidfile && file_exists($pidfile)) {
            @unlink($pidfile);
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
     * System signals handlers:
     * @function SIGTERM(), ...
     * 1) SIGHUP       2) SIGINT       3) SIGQUIT      4) SIGILL
     * 5) SIGTRAP      6) SIGABRT      7) SIGBUS       8) SIGFPE
     * 9) SIGKILL      10) SIGUSR1     11) SIGSEGV     12) SIGUSR2
     * 13) SIGPIPE     14) SIGALRM     15) SIGTERM     16) SIGSTKFLT
     * 17) SIGCHLD     18) SIGCONT     19) SIGSTOP     20) SIGTSTP
     * 21) SIGTTIN     22) SIGTTOU     23) SIGURG      24) SIGXCPU
     * 25) SIGXFSZ     26) SIGVTALRM   27) SIGPROF     28) SIGWINCH
     * 29) SIGIO       30) SIGPWR      31) SIGSYS      34) SIGRTMIN
     * 35) SIGRTMIN+1  36) SIGRTMIN+2  37) SIGRTMIN+3  38) SIGRTMIN+4
     * 39) SIGRTMIN+5  40) SIGRTMIN+6  41) SIGRTMIN+7  42) SIGRTMIN+8
     * 43) SIGRTMIN+9  44) SIGRTMIN+10 45) SIGRTMIN+11 46) SIGRTMIN+12
     * 47) SIGRTMIN+13 48) SIGRTMIN+14 49) SIGRTMIN+15 50) SIGRTMAX-14
     * 51) SIGRTMAX-13 52) SIGRTMAX-12 53) SIGRTMAX-11 54) SIGRTMAX-10
     * 55) SIGRTMAX-9  56) SIGRTMAX-8  57) SIGRTMAX-7  58) SIGRTMAX-6
     * 59) SIGRTMAX-5  60) SIGRTMAX-4  61) SIGRTMAX-3  62) SIGRTMAX-2
     * 63) SIGRTMAX-1  64) SIGRTMAX
     */
    /**
     * @function threadin
     *
     * @param int $max threads count
     * @param int $opt
     * @param int $timeout between threads chunk
     * SIGINT — прерывание процесса. Случается, когда пользователь оканчивает выполнение скрипта командой "ctrl+c".
     * SIGTERM — окончание процесса. Происходит, когда процесс останавливают командой kill (либо другой командой, посылающей такой сигнал).
     * SIGHUP - Кроме остановки выполнения скрипта, существует также сигнал перезапуска. Его часто используют для обновления конфигурации работающих процессов без их остановки.
     * pkill -HUP -f test.php
     * ps -efw | grep php | grep -v grep | awk '{print $2}' | xargs kill
     * netstat -anop
     */
    final function threading(int $max = 5, int $opt = \Application\CLI::THREAD_DEFAULT, int $timeout=0)
    {
        if (version_compare(PHP_VERSION, "5.3.0", '>=')) {
//            pcntl_signal_dispatch();
            pcntl_async_signals(true);
        }
        if (method_exists($this,'SIGTERM')) { pcntl_signal(SIGTERM, [$this, "SIGTERM"]); }
        if (method_exists($this,'SIGTSTP')) { pcntl_signal(SIGTSTP, [$this, "SIGTSTP"]); }
        if (method_exists($this,'SIGINT')) { pcntl_signal(SIGINT, [$this, "SIGINT"]); }
        if (method_exists($this,'SIGQUIT')) { pcntl_signal(SIGQUIT, [$this, "SIGQUIT"]); }
        if (method_exists($this,'SIGHUP')) { pcntl_signal(SIGHUP, [$this, "SIGHUP"]); }

        $this->looper = true;
        $this->max_threads = $max;
        $timeout = $opt & \Application\CLI::THREAD_INFINITY ? $timeout : 0;
        $empty_length = 0;
        $complete = false;
        $waite_empty_results = !($opt & \Application\CLI::THREAD_INFINITY);

        while ( $this->looper ) {
            while ( count($this->threads) > $this->max_threads ) {
                foreach ( $this->threads as $pid => $thread_num ) {
//                    $child = pcntl_waitpid($pid, $result, WNOHANG|WUNTRACED); // слушаем статус детей
//                    if ( $child == -1 || $child > 0 ) {
//                        $code = pcntl_wexitstatus($result);
//                        echo "=== STATUS: [ $code ] === $child : {$this->threads[$child]} ======\n\n";
//                        unset($this->threads[$pid]);
//                    }
                    $child = pcntl_wait($result);

                    if (pcntl_wifexited($result) !== 0) {
                        $code = pcntl_wexitstatus($result);
                        $complete = $complete || $code & \Application\CLI::THREAD_COMPLETE;
                        $empty_length = $code & \Application\CLI::THREAD_EMPTY ? $empty_length + 1 : 0;
                        if ($waite_empty_results && $max <= $empty_length) {
                            $this->max_threads = 0;
                            $this->looper = false;
                        } else if ($code & \Application\CLI::THREAD_RESULTSET && $this->max_threads < $max && !$timeout) {
                            if (!$complete) $this->max_threads++;
                        }
                        unset($this->threads[$child]);
                    }
                }
                continue;
            }

            if ( $this->looper && !$complete ) $this->launcher($opt, $timeout);

            if ( $this->max_threads == 0 && $timeout && $this->looper ) {
                sleep($timeout);
                $this->max_threads = $max;
            }
        }

    }

    /**
     * `@function launcher
     *
     * @param int $opt
     * @param $timeout
     * @return bool
     */
    private function launcher(int $opt = \Application\CLI::THREAD_DEFAULT, $timeout) {
        $pid = pcntl_fork();
        if (!$opt & \Application\CLI::THREAD_INFINITY || $timeout > 0) $this->max_threads--;
        if ($pid == -1) {
            trigger_error('Could not launch new job, exiting', E_USER_WARNING);
            return false;
        } else if ($pid) {
            $this->processPID = $pid;
            if ($this->pidfile) {
                $pidfile = $this->thread_idx == 0 ? $this->pidfile : $this->pidfile . ":" . $this->thread_idx;
                file_put_contents($pidfile, $this->processPID);
            }
            $this->thread_idx = $this->threads[$pid] = $this->max_threads; // храним список детей для прослушки
        } else {
            exit( $this->run() );
        }
        return true;
    }

}
