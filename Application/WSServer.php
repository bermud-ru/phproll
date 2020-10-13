<?php
/**
 * WSServer.php
 *
 * @category SPA (Single-page Application) WebSocket Backend
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 14/04/2020
 * @status beta
 * @version 0.1.0
 * @revision $Id: WSServer.php 0001 2020-04-14 15:00:01Z $
 *
 */
namespace Application;

if (version_compare(PHP_VERSION, "5.3.0", '<')) { declare(ticks = 1); }

abstract class WSServer extends \Application\CLI
{
    const WEBSOCKET_RESPONSE_CODE = [
        1000 => 'normal closure',
        1001 => 'going away',
        1002 => 'protocol error',
        1003 => 'unknown data (opcode)',
        1004 => 'frame too large',
        1005 => 'Unknow rrmote host error',
        1006 => 'Unknow rrmote host error',
        1007 => 'utf8 expected',
        1008 => 'message violates server policy',
        1015 => 'Untrusted SSL certificate'
    ];
    
    protected $ssl_mode = false;
    protected $context = null;
    protected $host = '127.0.0.1';
    protected $port = 8000;
    protected $server = null;

    protected $service = null;
    public $unix_socket = '/tmp/websocket.sock';

    protected $read = [];
    protected $write = [];
    protected $except = [];

    abstract public function handshake(\Application\ISocket $s);
    abstract public function open(\Application\ISocket $s, array $opt = []);
    abstract public function close(\Application\ISocket $s);
    abstract public function clear();
    abstract public function message($data);
    abstract public function send(\Application\ISocket $socket, $data, array $opt = []);

    public function __construct(array $params, $bootstrap = null)
    {
        parent::__construct($params, $bootstrap);
        if (array_key_exists('host', $params)) $this->host = $params['host'];
        if (array_key_exists('port', $params)) $this->port = $params['port'];
        if (array_key_exists('unix_socket', $params)) $this->unix_socket = $params['unix_socket'];

        $this->context = stream_context_create();
        if (array_key_exists('pem', $params) && file_exists($params['pem'])) {
            $this->ssl_mode = true;
            stream_context_set_option($this->context, 'ssl', 'local_cert', $params['pem']);
            if (array_key_exists('pem_passphrase', $params)) stream_context_set_option($this->context, 'ssl', 'passphrase', $params['pem_passphrase']);
            stream_context_set_option($this->context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($this->context, 'ssl', 'verify_peer', false);
        }
    }

    function __destruct() {
        $this->stop();
        parent::__destruct();
    }

    /**
     * @function SSLContext
     * Generate PEM file
     *
     * @param string $pem_file
     * @param array $dn
     * @param string|null $pem_passphrase
     * DN:
     * [
     * "countryName" => "UN",
     * "stateOrProvinceName" => "none",
     * "localityName" => "none",
     * "organizationName" => "none",
     * "organizationalUnitName" => "none",
     * "commonName" => "none.no",
     * "emailAddress" => "box@none.no"
     * ];
     */
    protected function SSLContext(string $pem_file, array $dn, ?string $pem_passphrase = null)
    {
        if (!file_exists($pem_file)) {
            $privkey = openssl_pkey_new();
            $cert = openssl_csr_new($dn, $privkey);
            $cert = openssl_csr_sign($cert, null, $privkey, 365);
            $pem = [];
            openssl_x509_export($cert, $pem[0]);
            if ($pem_passphrase) openssl_pkey_export($privkey, $pem[1], $pem_passphrase);
            $pem = implode($pem);
            file_put_contents($pem_file, $pem);
        }
    }

    public function start($thread)
    {
        if (file_exists($this->unix_socket)) @unlink($this->unix_socket);
        $this->service = stream_socket_server('unix://'.$this->unix_socket, $errorNumber, $error,STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if ($this->service) stream_set_blocking($this->service, 0);
//            if (!$this->service || $errorNumber || $error) return $this->stop();

        $url = ($this->ssl_mode ? 'tls:' : 'tcp:') . "//{$this->host}:{$this->port}";
        $this->server = stream_socket_server($url, $errorNumber, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->context);
        if (!$this->server || $errorNumber || $error) return $this->stop();
        if ($this->server) stream_set_blocking($this->server, 0);

        if (is_resource($this->server)) $this->read[] = $this->server;
        if (is_resource($this->service)) $this->read[] = $this->service;

        return $this->fork($thread, self::FORK_EXCHANGE);
    }

    public function stop()
    {
        $socket = new \Application\Socket();
        foreach ( array_merge($this->read, $this->write, $this->except) as $client ) {
            if (is_resource($client)) $socket($client)->close();
        }
        if (is_resource($this->server)) stream_socket_shutdown($this->server, STREAM_SHUT_RDWR);
        if (is_resource($this->service)) {
            stream_socket_shutdown($this->service, STREAM_SHUT_RDWR);
            if (file_exists($this->unix_socket)) @unlink($this->unix_socket);
        }
    }

    public function SIGTSTP()
    {
        $this->looper = false;
        $this->stop();
    }

    public function SIGHUP()
    {
        $this->looper = false;
        $this->stop();
    }

    public function run()
    {
        if (array_key_exists('start', $this->args)) {
            $host = array_key_exists('host', $this->args) ? $this->args['host'] : $this->host;
            $port = array_key_exists('port', $this->args) ? $this->args['port'] : $this->port;
            $thread = array_key_exists('thread', $this->args) ? $this->args['thread'] : 1;
            $cmd = "php $this->path/$this->file --host=$host --port=$port --thread=$thread > /dev/null &";
            exec($cmd);
            echo "done!" . PHP_EOL;
            exit;
        } elseif (array_key_exists('stop', $this->args)) {
            $cmd = "pkill -HUP -f $this->path/$this->file";
            exec($cmd);
            echo "done!" . PHP_EOL;
            exit;
        }

        if (array_key_exists('host', $this->args)) $this->host = $this->args['host'];
        if (array_key_exists('port', $this->args)) $this->port = $this->args['port'];
        if (array_key_exists('thread', $this->args)) $thread = intval($this->args['thread']);

        $this->start($thread);
    }
}