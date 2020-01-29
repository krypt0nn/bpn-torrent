<?php

namespace BPN;

class User
{
    public $ip;
    public $port = 53236;
    public $userTtl = 600;
    public $lastUpdate;
    public $supportSockets = false;

    public $secret = '`';

    public function __construct ($ip = null, $port = 53236)
    {
        $this->ip   = $ip;
        $this->port = $port;

        $this->lastUpdate = time ();
    }

    public function available ()
    {
        return time () - $this->lastUpdate < $this->userTtl;
    }

    public function toArray ()
    {
        $array = array ();

        foreach (array_diff (get_object_vars ($this), array (
            'secret'
        )) as $id => $value)
            $array[$id] = $value;

        return $array;
    }

    public function fromArray ($user)
    {
        foreach ($user as $id => $value)
            $this->$id = $value;

        return $this;
    }
}