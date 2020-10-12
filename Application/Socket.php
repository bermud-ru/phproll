<?php
/**
 * Soket.php
 *
 * @category SPA (Single-page Application) WebSocket Backend
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 25/09/2020
 * @status beta
 * @version 0.1.0
 * @revision $Id: Soket.php 0001 2020-04-14 15:00:01Z $
 *
 */
namespace Application;

class Socket implements \Application\ISocket
{
    const SOCKET_BUFFER_SIZE = 4096;

    public $type = false;
    public $socket = null;
    private $index = null;
    protected $opt = false;

    public function __construct(array $opt = [], $socket = null) {
        $this->opt = $opt;
        $this->socket = $socket;
        if ($socket) $this->index = intval($socket);
    }

    function __destruct() { $this->close(); }

    function __invoke($socket = null, string $class = null) {
        if (class_exists($class)) return new $class($this->opt, $socket);
        if ($socket) { $this->socket = $socket; $this->index = intval($socket); }
        return $this;
    }

    /**
     * Opt Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get ($name)
    {
        if (array_key_exists($name, $this->opt)) {
            return $this->opt[$name];
        }
        throw new \Exception(get_class($this) . "->$name property not foudnd!");
    }

    /**
     * Opt Native method
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (array_key_exists($name, $this->opt)) {
            return call_user_func_array($this->opt[$name]->bindTo($this), $arguments);
        }
        throw new \Exception(get_class($this) . "->$name(...) method not foudnd");
    }

    /**
     * @function meta
     * Получает информацию о существующем потоке stream.
     * 
     * @return array|null
     */
    public function meta(): ?array
    {
        return is_resource($this->socket) ? stream_get_meta_data($this->socket) : null;
    }

    /**
     * @param string $string
     * @param array $opt
     * @return int|null
     */
    public function write(string $string, array $opt = []): ?int
    {
        return is_resource($this->socket) ? @fwrite($this->socket, $string) : null;
    }

    /**
     * @return string|null
     */
    public function read(): ?string
    {
        return is_resource($this->socket) ? @fread($this->socket, self::SOCKET_BUFFER_SIZE) : null;
    }

    /**
     * @param $socket
     */
    public function close() {
        if (is_resource($this->socket)) stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
    }

}

