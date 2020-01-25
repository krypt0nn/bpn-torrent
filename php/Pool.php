<?php

namespace BPN;

class Pool
{
    public string $ip    = '127.0.0.1';
    public int $port     = 53236;
    public int $selfPort = 53236;
    public bool $supportSockets = false;

    public array $clients = [];
    public int $requestsRepeats = 5;

    public function __construct (string $ip, int $port = 53236, int $selfPort = 53236, bool $supportSockets = false)
    {
        $this->ip   = $ip;
        $this->port = $port;
        $this->selfPort = $selfPort;
        $this->supportSockets = $supportSockets;

        $this->update ();
    }

    public function update (): Pool
    {
        $count = 0;

        do
        {
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/'. Tracker::encode ([
                'type' => 'connect',
                'port' => $this->selfPort,
                'support_sockets' => $this->supportSockets
            ]));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        $response = @Tracker::decode ($response);

        if (is_array ($response))
            foreach ($response as $client)
            {
                $client = (new User)->fromArray ($client);

                $this->clients[$client->ip .':'. $client->port] = $client;
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
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/'. Tracker::encode ([
                'type'     => 'push',
                'port'     => $this->selfPort,
                'reciever' => $ip .':'. $port,
                'data'     => $data,
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
            $response = @file_get_contents ('http://'. $this->ip .':'. $this->port .'/'. Tracker::encode ([
                'type' => 'pop',
                'port' => $this->selfPort
            ]));
        }

        while (!$response && $count++ < $this->requestsRepeats);

        return Tracker::decode ($response);
    }
}
