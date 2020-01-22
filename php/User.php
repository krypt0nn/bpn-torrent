<?php

namespace BPN;

class User
{
    public ?string $ip  = null;
    public int $port    = 53236;
    public int $userTtl = 600;
    public int $lastUpdate;
    public bool $supportSockets = false;

    public function __construct (string $ip = null, int $port = 53236)
    {
        $this->ip   = $ip;
        $this->port = $port;

        $this->lastUpdate = time ();
    }

    public function available (): bool
    {
        return time () - $this->lastUpdate < $this->userTtl;
    }

    public function toArray (): array
    {
        $array = [];

        foreach (get_object_vars ($this) as $id => $value)
            $array[$id] = $value;

        return $array;
    }

    public function fromArray (array $user): User
    {
        foreach ($user as $id => $value)
            $this->$id = $value;

        return $this;
    }
}
