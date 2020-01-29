<?php

namespace BPN;

use DHGenerator\Generator;

class Tracker
{
    public $node;
    public $clients = array ();
    public $stack   = array ();

    public $stackTtl = 3600;

    public function __construct ($port = 53236)
    {
        $this->node = new Node ($port);
    }

    public function listen ($callback = null, $cycle = false)
    {
        $self = &$this;

        $this->node->listen (function ($request, $client) use ($callback, &$self)
        {
            $request = Tracker::decode (substr ($request, 5, strpos ($request, ' HTTP/') - 5));

            if (!$request)
                return;

            if (!isset ($request['port']))
                $request['port'] = 53236;

            if (isset ($request['loopback']) && is_string ($request['loopback']))
                $ip = $request['loopback'];

            else socket_getpeername ($client->socket, $ip);

            $port = min (max ((int) $request['port'], 1), 65535);

            if (isset ($self->clients[$ip .':'. $port]))
                $self->clients[$ip .':'. $port]->lastUpdate = time ();

            if (!isset ($request['type']))
                $request['type'] = null;

            switch ($request['type'])
            {
                case 'available':
                    $client->write (new Http . Tracker::encode ('ok'));

                    break;

                case 'connect':
                    if (!isset ($request['support_sockets']))
                        $request['support_sockets'] = false;
                    
                    $self->clients[$ip .':'. $port] = new User ($ip, $port);
                    $self->clients[$ip .':'. $port]->supportSockets = (bool) $request['support_sockets'];

                    $generator = new Generator ($request['g'], $request['p']);
                    $self->clients[$ip .':'. $port]->secret = Pool::expand ($generator->generate ($request['alpha']));

                    $client->write (new Http . Tracker::encode (array (
                        'alpha'   => $generator->getAlpha (),
                        'clients' => array_map (function ($client)
                        {
                            return $client->toArray ();
                        }, $self->clients)
                    )));

                    break;

                case 'push':
                    if (!isset ($request['reciever']) || !isset ($request['data']) || !isset ($request['mask']) || !isset ($self->clients[$request['reciever']]))
                        break;

                    $self->stack[$request['reciever']][] = array
                    (
                        'timestamp' => time (),
                        'author'    => $ip .':'. $port, // Расшифровать полученные данные от ключа отправителя и зашифровать их ключём получателя
                        'data'      => $self->xorcode ($self->xorcode ($request['data'], $self->clients[$ip .':'. $port]->secret), $self->clients[$request['reciever']]->secret),
                        'mask'      => crc32 ($request['mask'])
                    );

                    $client->write (new Http . Tracker::encode ('ok'));

                    break;

                case 'pop':
                    $client->write (new Http . Tracker::encode (isset ($self->stack[$ip .':'. $port]) ?
                        $self->stack[$ip .':'. $port] : array ()));

                    unset ($self->stack[$ip .':'. $port]);

                    break;

                default:
                    if ($callback)
                        $callback ($request, $client);

                    break;
            }
        }, $cycle);

        return $this;
    }

    public function update ()
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

    public static function encode ($data)
    {
        return urlencode (serialize ($data));
    }

    public static function decode ($data)
    {
        return unserialize (urldecode ($data));
    }

    public static function xorcode ($data, $key)
    {
        return $data ^ str_repeat ($key, ceil (strlen ($data) / strlen ($key)));
    }
}
