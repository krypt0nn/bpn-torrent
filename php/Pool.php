<?php

namespace BPN;

use DHGenerator\Generator;

class Pool
{
    public $ip       = '127.0.0.1';
    public $port     = 53236;
    public $selfIp   = null;
    public $selfPort = 53236;
    public $supportSockets = false;
    
    public $clients = array ();
    public $requestsRepeats = 3;

    public $generator;
    public $secret = '`';

    public function __construct ($ip, $port = 53236, $selfIp = null, $selfPort = 53236, $supportSockets = false)
    {
        $this->ip       = $ip;
        $this->port     = $port;
        $this->selfIp   = $selfIp;
        $this->selfPort = $selfPort;
        $this->supportSockets = $supportSockets;

        $this->generator = new Generator (rand (100000000, 999999999), rand (100000000, 999999999));

        $this->update ();
    }

    public function update ()
    {
        $count = 0;

        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/?r='. Tracker::encode (array (
                'type'     => 'connect',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp,
                'support_sockets' => $this->supportSockets,
                'g'     => $this->generator->g,
                'p'     => $this->generator->p,
                'alpha' => $this->generator->getAlpha ()
            )));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        $response = @Tracker::decode ($response);

        if (is_array ($response))
        {
            $this->secret = $this->generator->generate ($response['alpha']);

            foreach ($response['clients'] as $client)
            {
                $client = new User;
                $client = $client->fromArray ($clientInfo);

                $this->clients[$client->ip .':'. $client->port] = $client;
            }
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
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/?r='. Tracker::encode (array (
                'type'     => 'push',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp,
                'reciever' => $ip .':'. $port,
                'data'     => $this->xorcode (serialize ($data)),
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
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/?r='. Tracker::encode (array (
                'type'     => 'pop',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp
            )));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        $response = @Tracker::decode ($response);

        if (!$response)
            $response = array ();

        foreach ($response as &$value)
            $value['data'] = @unserialize ($this->xorcode ($value['data']));

        return $response;
    }

    public function xorcode ($data)
    {
        return $data ^ str_repeat ($this->secret, ceil (strlen ($data) / strlen ($this->secret)));
    }

    public static function expand ($secret)
    {
        return urlencode (hash ('sha512', $secret, true));
    }
}
