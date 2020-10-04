<?php
/**
 * WebSoket.php
 *
 * @category SPA (Single-page Application) WebSocket Backend
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 14/04/2020
 * @status beta
 * @version 0.1.0
 * @revision $Id: WebSoket.php 0001 2020-04-14 15:00:01Z $
 *
 */
namespace Application;

class WebSocket extends \Application\Socket
{
    const SOCKET_BUFFER_SIZE = 8192;

    public function read(): ?string
    {
        return $this->hybi10Decode($this->readBuffer());
    }

    public function write(string $string, array $opt = []): ?int
    {
        $opt = array_merge(['type' => 'text', 'masked' => true], $opt);
        return $this->writeBuffer($this->hybi10Encode($string, $opt['type'], $opt['masked']));
    }

    /**
     * FIX: method originally found in phpws project:
     * @return false|string|null
     */
    public function readBuffer()
    {
        if (!is_resource($this->socket)) return null;

        if($this->ssl_mode === true)
        {
            $buffer = fread( $this->socket, self::SOCKET_BUFFER_SIZE );
            // extremely strange chrome behavior: first frame with ssl only contains 1 byte?!
            if (strlen($buffer) === 1)
            {
                $buffer .= fread( $this->socket,self::SOCKET_BUFFER_SIZE );
            }
            return $buffer;
        }
        else
        {
            $buffer = '';
            $buffsize = self::SOCKET_BUFFER_SIZE;
            $metadata['unread_bytes'] = 0;
            do
            {
                if( feof($this->socket) )
                {
                    return null;
                }
                $result = fread( $this->socket, $buffsize );
                if($result === false || feof( $this->socket ))
                {
                    return null;
                }
                $buffer .= $result;
                $metadata = stream_get_meta_data( $this->socket );
                $buffsize = ($metadata['unread_bytes'] > $buffsize) ? $buffsize : $metadata['unread_bytes'];
            } while($metadata['unread_bytes'] > 0);

            return $buffer;
        }
    }

    /**
     * FIX: method originally found in phpws project:
     * @param string $string
     * @return int|null
     */
    public function writeBuffer(string $string): ?int
    {
        if (!is_resource($this->socket)) return null;

        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($this->socket, substr($string, $written));
            if ($fwrite === false) {
                return $written;
            }
        }
        return $written;
    }

    /**
     * @function hybi10Decode
     *
     * @param $data
     * @return string|null
     */
    private function hybi10Decode($data): ?string
    {
        $bytes = $data;
        $dataLength = '';
        $mask = '';
        $coded_data = '';
        $decodedData = '';
        $secondByte = sprintf('%08b', ord($bytes[1]));
        $masked = ($secondByte[0]=='1') ? true : false;
        $dataLength = ($masked===true) ? ord($bytes[1]) & 127 : ord($bytes[1]);

        if ($masked===true)
        {
            if ($dataLength===126)
            {
                $mask = substr($bytes, 4, 4);
                $coded_data = substr($bytes, 8);
            }
            elseif ($dataLength===127)
            {
                $mask = substr($bytes, 10, 4);
                $coded_data = substr($bytes, 14);
            }
            else
            {
                $mask = substr($bytes, 2, 4);
                $coded_data = substr($bytes, 6);
            }
            for ($i = 0; $i<strlen($coded_data); $i++)
                $decodedData .= $coded_data[$i] ^ $mask[$i % 4];
        }
        else
        {
            if ($dataLength===126)
                $decodedData = substr($bytes, 4);
            elseif ($dataLength===127)
                $decodedData = substr($bytes, 10);
            else
                $decodedData = substr($bytes, 2);
        }

        return $decodedData;
    }

    /**
     * @function hybi10Encode(
     *
     * @param $payload
     * @param string $type
     * @param bool $masked
     * @return string
     */
    private function hybi10Encode($payload, $type = 'text', $masked = true): string
    {
        $frameHead = [];
        $frame = '';
        $payloadLength = strlen($payload);

        switch ($type)
        {
            case 'text' :
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close' :
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping' :
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong' :
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength>65535)
        {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked===true) ? 255 : 127;
            for ($i = 0; $i<8; $i++)
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);

            // most significant bit MUST be 0 (close connection if frame too big)
            if ($frameHead[2]>127)
            {
//                $this->close(1004);
                return null;
            }
        }
        elseif ($payloadLength>125)
        {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked===true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        }
        else
            $frameHead[1] = ($masked===true) ? $payloadLength + 128 : $payloadLength;

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i)
            $frameHead[$i] = chr($frameHead[$i]);

        if ($masked===true)
        {
            // generate a random mask:
            $mask = [];
            for ($i = 0; $i<4; $i++)
                $mask[$i] = chr(rand(0, 255));

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);
        // append payload to frame:
        for ($i = 0; $i<$payloadLength; $i++)
            $frame .= ($masked===true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];

        return $frame;
    }

    /**
     * @function generateRandomString
     * Helper
     *
     * @param int $length
     * @param bool $addSpaces
     * @param bool $addNumbers
     * @return false|string
     */
    private static function generateRandomString($length = 10, $addSpaces = true, $addNumbers = true)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"§$%&/()=[]{}';
        $useChars = array();
        // select some random chars:
        for($i = 0; $i < $length; $i++)
        {
            $useChars[] = $characters[mt_rand(0, strlen($characters)-1)];
        }
        // add spaces and numbers:
        if($addSpaces === true)
        {
            array_push($useChars, ' ', ' ', ' ', ' ', ' ', ' ');
        }
        if($addNumbers === true)
        {
            array_push($useChars, rand(0,9), rand(0,9), rand(0,9));
        }
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, $length);
        return $randomString;
    }

    /**
     * @function handshake
     *
     * @param $host
     * @param $port
     * @param $path
     * @param bool $origin
     * @return bool
     */
    public static function handshake($host, $port, $path, $origin = false)
    {
        $key = base64_encode(self::generateRandomString(16, false, true));
        $header = "GET " . $path . " HTTP/1.1\r\n";
        $header.= "Host: ".$host.":".$port."\r\n";
        $header.= "Upgrade: websocket\r\n";
        $header.= "Connection: Upgrade\r\n";
        $header.= "Sec-WebSocket-Key: " . $key . "\r\n";
        if($origin !== false)
        {
            $header.= "Sec-WebSocket-Origin: " . $origin . "\r\n";
        }
        $header.= "Sec-WebSocket-Version: 13\r\n";

        $Socket = fsockopen($host, $port, $errno, $errstr, 2);
        fwrite($Socket, $header);
        $response = fread($Socket, 1500);
        fclose($Socket);

        preg_match('#Sec-WebSocket-Accept:\s(.*)$#mU', $response, $matches);
        $keyAccept = trim($matches[1]);
        $expectedResonse = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        return ($keyAccept === $expectedResonse) ? true : false;
    }
}

