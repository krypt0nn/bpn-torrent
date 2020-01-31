<?php

namespace BPN;

use DHGenerator\Generator;

class Tracker
{
    public Node $node;
    public array $clients = [];
    public array $stack   = [];

    public int $stackTtl = 3600;

    public function __construct (int $port = 53236)
    {
        $this->node = new Node ($port);
    }

    public function listen (callable $callback = null, bool $cycle = false): Tracker
    {
        $this->node->listen (function (string $request, Socket $client) use ($callback)
        {
            $request = @$this->decode (substr ($request, 5, strpos ($request, ' HTTP/') - 5));

            if (!$request)
                return;

            if (isset ($request['loopback']) && is_string ($request['loopback']))
                $ip = $request['loopback'];

            else socket_getpeername ($client->socket, $ip);

            $port = min (max ((int) ($request['port'] ?? 53236), 1), 65535);

            if (isset ($this->clients[$ip .':'. $port]))
                $this->clients[$ip .':'. $port]->lastUpdate = time ();

            switch ($request['type'] ?? null)
            {
                case 'available':
                    $client->write (new Http . $this->encode ('ok'));

                    break;

                case 'connect':
                    $this->clients[$ip .':'. $port] = new User ($ip, $port);
                    $this->clients[$ip .':'. $port]->supportSockets = (bool) $request['support_sockets'] ?? false;

                    $this->clients[$ip .':'. $port]->secret = Pool::expand (($generator = new Generator ($request['g'], $request['p']))->generate ($request['alpha']));

                    $client->write (new Http . $this->encode ([
                        'alpha'   => $generator->getAlpha (),
                        'clients' => array_map (fn ($client) => $client->toArray (), $this->clients)
                    ]));

                    break;

                case 'push':
                    if (!isset ($request['reciever']) || !isset ($request['data']) || !isset ($request['mask']) || !isset ($this->clients[$request['reciever']]))
                        break;

                    $this->stack[$request['reciever']][] = [
                        'timestamp' => time (),
                        'author'    => $ip .':'. $port,
                        'data'      => $this->xorcode ($request['data'], $this->clients[$ip .':'. $port]->secret),
                        'mask'      => crc32 ($request['mask'])
                    ];

                    $client->write (new Http . $this->encode ('ok'));

                    break;

                case 'pop':
                    $client->write (new Http . $this->encode (array_map (function ($data) use ($ip, $port)
                    {
                        $data['data'] = $this->xorcode ($data['data'], $this->clients[$ip .':'. $port]->secret);

                        return $data;
                    }, $this->stack[$ip .':'. $port]) ?? []));

                    unset ($this->stack[$ip .':'. $port]);

                    break;

                default:
                    if ($callback)
                        $callback ($request, $client);

                    break;
            }
        }, $cycle);

        return $this;
    }

    public function update (): Tracker
    {
        foreach ($this->clients as $address => $client)
            if (!$client->available ())
                unset ($this->clients[$address], $this->stack[$address]);

        $timestamp = time ();

        foreach ($this->stack as $address => $messages)
        {
            foreach ($messages as $id => $message)
                if ($timestamp - $message['timestamp'] > $this->stackTtl)
                    unset ($this->stack[$address][$id]);

            if (sizeof ($this->stack[$address]) == 0)
                unset ($this->stack[$address]);
        }

        return $this;
    }

    public static function encode ($data): string
    {
        return urlencode (base64_encode (serialize ($data)));
    }

    public static function decode (string $data)
    {
        return unserialize (base64_decode (urldecode ($data)));
    }

    public static function xorcode (string $data, string $key): string
    {
        return $data ^ str_repeat ($key, ceil (strlen ($data) / strlen ($key)));
    }
}
