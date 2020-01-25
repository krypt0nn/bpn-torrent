<?php

namespace BPN;

class Node
{
    public $port = 53236;
    public $socket;

    public function __construct ($port = 53236)
    {
        $this->port   = $port;
        $this->socket = socket_create_listen ($port);
    }

    public function listen ($callback, $cycle = false)
    {
        do
        {
            $client = new Socket (socket_accept ($this->socket));

            $callback ($client->read (), $client);
            $client->close ();
        }

        while ($cycle);

        return $this;
    }
}
