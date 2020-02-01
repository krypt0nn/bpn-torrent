<?php

namespace BPN;

use DHGenerator\Generator;

class Pool
{
    public string $ip      = '127.0.0.1';
    public int $port       = 53236;
    public ?string $selfIp = null;
    public int $selfPort   = 53236;
    public bool $supportSockets = false;

    public array $clients = [];
    public int $requestsRepeats = 3;

    public Generator $generator;
    public string $secret = '`';

    public function __construct (string $ip, int $port = 53236, string $selfIp = null, int $selfPort = 53236, bool $supportSockets = false)
    {
        $this->ip       = $ip;
        $this->port     = $port;
        $this->selfIp   = $selfIp;
        $this->selfPort = $selfPort;
        $this->supportSockets = $supportSockets;

        $this->generator = new Generator (rand (100000000, 999999999), rand (100000000, 999999999));

        $this->update ();
    }

    public function update (): Pool
    {
        $count = 0;

        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/?r='. Tracker::encode ([
                'type'     => 'connect',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp,
                'support_sockets' => $this->supportSockets,
                'g'     => $this->generator->g,
                'p'     => $this->generator->p,
                'alpha' => $this->generator->getAlpha ()
            ]));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        $response = @Tracker::decode ($response);

        if (is_array ($response))
        {
            $this->secret = $this->generator->generate ($response['alpha']);

            foreach ($response['clients'] as $client)
            {
                $client = (new User)->fromArray ($client);

                $this->clients[$client->ip .':'. $client->port] = $client;
            }
        }

        return $this;
    }

    public function user (string $ip, int $port = 53236): ?User
    {
        return $this->clients[$ip .':'. $port] ?? null;
    }

    /**
     * @param string $ip   - IP получателя
     * @param int $port    - port получателя
     * @param mixed $data  - информация для отправки
     * @param string $mask - маска запроса (нужна для индексации единых запросов в разных трекерах)
     */
    public function push (string $ip, int $port, $data, string $mask): Pool
    {
        $count = 0;

        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/?r='. Tracker::encode ([
                'type'     => 'push',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp,
                'reciever' => $ip .':'. $port,
                'data'     => $this->xorcode (serialize ($data)),
                'mask'     => $mask
            ]));
        }

        while ((!$response || @Tracker::decode ($response) != 'ok') && $count++ < $this->requestsRepeats);

        return $this;
    }

    public function pop (): array
    {
        $count = 0;
        
        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/?r='. Tracker::encode ([
                'type'     => 'pop',
                'port'     => $this->selfPort,
                'loopback' => $this->selfIp
            ]));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        $response = @Tracker::decode ($response) ?: [];

        foreach ($response as &$value)
            $value['data'] = @unserialize ($this->xorcode ($value['data']));

        return $response;
    }

    public function xorcode (string $data): string
    {
        return $data ^ str_repeat ($this->secret, ceil (strlen ($data) / strlen ($this->secret)));
    }

    public static function expand (string $secret): string
    {
        return urlencode (hash ('sha512', $secret, true));
    }
}
