<?php

namespace BPN;

class Socket
{
    public ?string $ip = null;
    public int $port;
    public $socket;

    public function __construct ($socket = '127.0.0.1', int $port = 53236)
    {
        if (is_resource ($socket))
            $this->socket = $socket;

        else
        {
            $this->socket = socket_create (AF_INET, SOCK_STREAM, 0);
            $this->ip     = $socket;

            if (!$this->socket)
                throw new \Exception ('Socket creation error: '. socket_strerror (socket_last_error ()));
        }

        $this->port = $port;

        socket_set_nonblock ($this->socket);
    }

    public function connect (): Socket
    {
        if (!$this->ip)
            throw new \Exception ('Socket IP not specified');
        
        socket_connect ($this->socket, $this->ip, $this->port);

        return $this;
    }

    public function bind (): Socket
    {
        if (!$this->ip)
            throw new \Exception ('Socket IP not specified');
        
        if (!socket_bind ($this->socket, $this->ip, $this->port))
            throw new \Exception ('Socket binding error: '. socket_strerror (socket_last_error ()));

        return $this;
    }

    public function listen (): Socket
    {
        socket_listen ($this->socket);
        
        return new Socket (socket_accept ($this->socket));
    }

    public function read (): string
    {
        $read = '';

        while ($t = socket_read ($this->socket, 1024))
            $read .= $t;

        return $read;
    }

    public function write (string $data): Socket
    {
        socket_write ($this->socket, $data);

        return $this;
    }

    public function close (): void
    {
        socket_close ($this->socket);
    }
}
