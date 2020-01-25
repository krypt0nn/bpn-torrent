<?php

namespace BPN;

class Socket
{
    public $ip;
    public $port;
    public $socket;

    public $sendRepeats = 3;

    public function __construct ($socket = '127.0.0.1', $port = 53236)
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

    public function connect ()
    {
        if (!$this->ip)
            throw new \Exception ('Socket IP not specified');
        
        socket_connect ($this->socket, $this->ip, $this->port);

        return $this;
    }

    public function bind ()
    {
        if (!$this->ip)
            throw new \Exception ('Socket IP not specified');
        
        if (!socket_bind ($this->socket, $this->ip, $this->port))
            throw new \Exception ('Socket binding error: '. socket_strerror (socket_last_error ()));

        return $this;
    }

    public function listen ()
    {
        socket_listen ($this->socket);
        
        return new Socket (socket_accept ($this->socket));
    }

    public function read ()
    {
        $read = '';

        while ($t = socket_read ($this->socket, 1024))
            $read .= $t;

        return $read;
    }

    public function write ($data)
    {
        $count = 0;
        
        do
        {
            $response = socket_write ($this->socket, $data);
        }

        while ($response === false && $count++ < $this->sendRepeats);

        return $this;
    }

    public function close ()
    {
        socket_close ($this->socket);
    }
}
