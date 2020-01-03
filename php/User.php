<?php

namespace BPN;

class User
{
    public string $ip;
    public int $port  = 53236;
    public bool $open = false;

    public function __construct (string $ip, int $port = 53236, bool $open = false)
    {
        $this->ip   = $ip;
        $this->port = $port;
        $this->open = $open;
    }
}
