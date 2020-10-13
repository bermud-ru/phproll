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

//use http\Encoding\Stream;

if (version_compare(PHP_VERSION, "5.3.0", '<')) { declare(ticks = 1); }

abstract class CLI
{
    const SYSTEM_SIGNALS = [
        'SIGHUP' => SIGHUP, 'SIGINT'=> SIGINT , 'SIGQUIT' => SIGQUIT, 'SIGILL' => SIGILL,
        'SIGTRAP' => SIGTRAP, 'SIGABRT5' => SIGABRT, 'SIGBUS' => SIGBUS, 'SIGFPE' => SIGFPE,
        'SIGKILL' => SIGKILL, 'SIGUSR1' => SIGUSR1, 'SISEGV' => SIGSEGV, 'SIGUSR2' => SIGUSR2,
        'SIGPIPE' => SIGPIPE, 'SIGALRM' => SIGALRM, 'SIGTERM' => SIGTERM, 'SIGSTKFLT' => SIGSTKFLT,
        'SIGCHLD' => SIGCHLD, 'SIGCONT' => SIGCONT, 'SIGSTOP' => SIGSTOP, 'SIGTSTP' => SIGTSTP,
        'SIGTTIN' => SIGTTIN,  'SIGTTOU' => SIGTTOU, 'SIGURG' => SIGURG, 'SIGXCPU' => SIGXCPU,
        'SIGXFSZ' => SIGXFSZ, 'SIGVTALRM' => SIGVTALRM, 'SIGPROF' => SIGPROF, 'SIGWINCH' => SIGWINCH,
        'SIGIO' => SIGPOLL, 'SIGPWR' => SIGPWR, 'SIGSYS' => SIGSYS,
//        'SIGRTMIN' => 34,
//        'SIGRTMIN+1' => 35, 'SIGRTMIN+2' => 36, 'SIGRTMIN+3' => 37, 'SIGRTMIN+4' => 38,
//        'SIGRTMIN+5' => 39, 'SIGRTMIxN+6' => 40, 'SIGRTMIN+7' => 41, 'SIGRTMIN+8' => 42,
//        'SIGRTMIN+9' => 43, 'SIGRTMIN+10' => 44, 'SIGRTMIN+11' => 45, 'SIGRTMIN+12' => 46,
//        'SIGRTMIN+13' => 47, 'SIGRTMIN+14' => 48, 'SIGRTMIN+15' => 49, 'SIGRTMAX-14' => 50,
//        'SIGRTMAX-13' => 51, 'SIGRTMAX-12' => 52, 'SIGRTMAX-11' => 53, 'SIGRTMAX-10' => 54,
//        'SIGRTMAX-9' => 55, 'SIGRTMAX-8' => 56, 'SIGRTMAX-7' => 57, 'SIGRTMAX-6' => 58,
//        'SIGRTMAX-5' => 59, 'SIGRTMAX-4' => 60, 'SIGRTMAX-3' => 61, 'SIGRTMAX-2' => 62,
//        'SIGRTMAX-1' => 63, 'SIGRTMAX' => 64,
    ];

    public $args = [];
    public $pidfile = null;
    public $threadfile = null;
    public $cron = null;

    protected $__running = false;
    public $processPID = null;

    protected static $scriptID;
    public $path;
    public $file;

    const FORK_DEFAULT = 0;
    const FORK_INFINITY = 1;
    const FORK_EXCHANGE = 2;
    const FORK_EMPTY = 4;
    const FORK_RESULTSET = 8;
    const FORK_COMPLETE = 16;

    public $looper = true;
    public $max_forks = 0;
    public $fork_idx = 0;
    protected $forks = [];

    /**
     * Конструктор
     *
     * @param $config данные из файла конфигурации
     */
    public function __construct(array $params, $bootstrap = null)
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
        if ($this->pidfile) $pidfile = $this->fork_idx == 0 ? $this->pidfile : $this->pidfile .":" . $this->fork_idx;
        if (!$this->__running && $pidfile && file_exists($pidfile)) { @unlink($pidfile); }

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
            default:
                $value = isset($this->args[$name]) ? $this->args[$name] : null;
        }
        return $value;
    }

    /**
     * @function run
     * @return mixed
     */
    abstract public function run();

    /**
     * @function job
     * Payload part for multithreading tasks
     *
     * @param stream $io
     * @return int FORK_EMPTY | FORK_RESULTSET | FORK_COMPLETE
     */
    public function job( $io = null ): ?int { return self::FORK_COMPLETE; }

    /**
     * @function fork
     *
     * @param int $max forks count
     * @param int $opt
     * @param int $timeout between forks chunk  micro_seconds
     * SIGINT — прерывание процесса. Случается, когда пользователь оканчивает выполнение скрипта командой "ctrl+c".
     * SIGTERM — окончание процесса. Происходит, когда процесс останавливают командой kill (либо другой командой, посылающей такой сигнал).
     * SIGHUP - Кроме остановки выполнения скрипта, существует также сигнал перезапуска. Его часто используют для обновления конфигурации работающих процессов без их остановки.
     * pkill -HUP -f test.php
     * ps -efw | grep php | grep -v grep | awk '{print $2}' | xargs kill
     * netstat -anop
     */
    final function fork(int $max = 1, int $opt = \Application\CLI::FORK_DEFAULT, int $timeout=0)
    {
        if (defined('SINGLETON') && SINGLETON) return $this->job();

        if (version_compare(PHP_VERSION, "5.3.0", '>=')) {
//            pcntl_signal_dispatch();
            pcntl_async_signals(true);
        }

        foreach (self::SYSTEM_SIGNALS as $SIGNAL => $code) {
            if (method_exists($this, $SIGNAL)) { pcntl_signal($code, [$this, $SIGNAL]); }
        }

        $this->looper = true;
        $this->max_forks = $max;
        $timeout = $opt & self::FORK_INFINITY ? $timeout : 0;
        $empty_length = 0;
        $complete = false;
        $waite_empty_results = !($opt & self::FORK_INFINITY);
        $write = $except = [];
        $unix = new \Application\Socket();

        while ( $this->looper ) {
            if ( $opt & self::FORK_EXCHANGE ) {
                if (count($this->forks) && stream_select($this->forks, $write, $except, null)) {
                    foreach ($this->forks as $src) {
                        $data = $unix($src)->read();
                        if (!$data) { //соединение было закрыто
//                            $unix($src)->close();
                            continue;
                        }
                        foreach ($this->forks as $dest) { //пересылаем данные во все воркеры
                            if (is_resource($dest) && ($dest !== $src)) {
                               $unix($dest)->write($data);
                            }
                        }
                    }
                }
            }
            while ( count($this->forks) > $this->max_forks ) {
                foreach ( $this->forks as $pid => $socket ) {
//                    $child = pcntl_waitpid($pid, $result, WNOHANG|WUNTRACED); // слушаем статус детей
//                    if ( $child == -1 || $child > 0 ) {
//                        $code = pcntl_wexitstatus($result);
//                        echo "=== STATUS: [ $code ] === $child : {$this->forks[$child]} ======\n\n";
//                        @fclose($socket);
//                        unset($this->forks[$pid]);
//                    }
                    $child = pcntl_wait($result);
                    if (pcntl_wifexited($result) !== 0) {
                        $code = pcntl_wexitstatus($result);
                        $complete = $complete || $code & self::FORK_COMPLETE;
                        $empty_length = $code & self::FORK_EMPTY ? $empty_length + 1 : 0;
                        if ($waite_empty_results && $max <= $empty_length) {
                            $this->max_forks = 0;
                            $this->looper = false;
                        } else if ($code & self::FORK_RESULTSET && $this->max_forks < $max && !$timeout) {
                            if (!$complete) $this->max_forks++;
                        }
                        @fclose($this->forks[$child]);
                        unset($this->forks[$child]);

                        if ( $this->max_forks == 0 && $timeout && $this->looper ) {
                            usleep($timeout);
                            $this->max_forks = $max;
                        }
                    }
                }
                continue;
            }
            if ( $this->looper && !$complete ) list($pid, $fork_idx) = $this->launcher($opt, $timeout);
        }
        return self::FORK_COMPLETE;
    }

    /**
     * `@function launcher
     *
     * @param int $opt
     * @param $timeout
     * @return array PID , ForkIndex
     */
    protected function launcher(int $opt = \Application\CLI::FORK_DEFAULT, $timeout) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();

        if (!$opt & self::FORK_INFINITY || $timeout > 0) $this->max_forks--;
        if ($pid == -1) {
            trigger_error('Could not launch new job, exiting', E_USER_WARNING);
            return false;
        } else if ($pid) {
            $this->processPID = $pid;
            if ($this->pidfile) {
                $pidfile = $this->fork_idx == 0 ? $this->pidfile : $this->pidfile . ":" . $this->fork_idx;
                @file_put_contents($pidfile, $this->processPID);
            }
            @fclose($pair[0]);
            $this->forks[$pid] = $pair[1]; // храним список детей для прослушки
            $this->fork_idx = $this->max_forks;
        } else {
            @fclose($pair[1]);
            exit( $this->job( $pair[0]) );
        }
        return [$pid, $this->fork_idx];
    }

}
