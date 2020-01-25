<?php

namespace BPN;

class Pool
{
    public $ip       = '127.0.0.1';
    public $port     = 53236;
    public $selfIp   = null;
    public $selfPort = 53236;
    public $supportSockets = false;
    
    public $clients = array ();
    public $requestsRepeats = 3;

    public function __construct ($ip, $port = 53236, $selfIp = null, $selfPort = 53236, $supportSockets = false)
    {
        $this->ip       = $ip;
        $this->port     = $port;
        $this->selfIp   = $selfIp;
        $this->selfPort = $selfPort;
        $this->supportSockets = $supportSockets;

        $this->update ();
    }

    public function update ()
    {
        $count = 0;

        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/'. Tracker::encode (array (
                'type'     => 'connect',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp,
                'support_sockets' => $this->supportSockets
            )));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        $response = @Tracker::decode ($response);

        if (is_array ($response))
            foreach ($response as $clientInfo)
            {
                $client = new User;
                $client = $client->fromArray ($clientInfo);

                $this->clients[$client->ip .':'. $client->port] = $client;
            }

        return $this;
    }

    public function user ($ip, $port = 53236)
    {
        return isset ($this->clients[$ip .':'. $port]) ?
            $this->clients[$ip .':'. $port] : null;
    }

    /**
     * @param string $ip   - IP получателя
     * @param int $port    - port получателя
     * @param mixed $data  - информация для отправки
     * @param string $mask - маска запроса (нужна для индексации единых запросов в разных трекерах)
     */
    public function push ($ip, $port, $data, $mask)
    {
        $count = 0;

        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/'. Tracker::encode (array (
                'type'     => 'push',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp,
                'reciever' => $ip .':'. $port,
                'data'     => $data,
                'mask'     => $mask
            )));
        }

        while ((!$response || @Tracker::decode ($response) != 'ok') && $count++ < $this->requestsRepeats);

        return $this;
    }

    public function pop ()
    {
        $count = 0;
        
        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/'. Tracker::encode (array (
                'type'     => 'pop',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp
            )));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        return Tracker::decode ($response);
    }
}
