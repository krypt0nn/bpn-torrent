<?php

namespace BPN;

class Node
{
    public int $port = 53236;
    public $socket;

    public function __construct (int $port = 53236)
    {
        $this->port   = $port;
        $this->socket = socket_create_listen ($port);
    }

    public function listen (callable $callback, bool $cycle = false): Node
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
