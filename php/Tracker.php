<?php

namespace BPN;

use DHGenerator\Generator;

class Tracker
{
    public $node;
    public $clients = array ();
    public $stack   = array ();

    public $stackTtl = 3600;

    public function __construct ()
    {
        if (file_exists ('tracker.data'))
        {
            $data = unserialize ($this->xorcode (file_get_contents ('tracker.data'), TRACKER_KEY));

            $this->clients  = $data['clients'];
            $this->stack    = $data['stack'];
            $this->stackTtl = $data['stackTtl'];
        }
    }

    public function __destruct ()
    {
        file_put_contents ('tracker.data', $this->xorcode (serialize (get_object_vars ($this)), TRACKER_KEY));
    }

    public function processRequest ($request, $callback = null)
    {
        $request = @$this->decode ($request);

        if (!$request)
            return $this;

        if (!isset ($request['port']))
            $request['port'] = 53236;

        if (isset ($request['loopback']) && is_string ($request['loopback']))
            $ip = $request['loopback'];

        else $ip = $_SERVER['REMOTE_ADDR'];

        $port = min (max ((int) $request['port'], 1), 65535);

        if (isset ($this->clients[$ip .':'. $port]))
            $this->clients[$ip .':'. $port]->lastUpdate = time ();

        if (!isset ($request['type']))
            $request['type'] = null;

        switch ($request['type'])
        {
            case 'available':
                echo $this->encode ('ok');

                break;

            case 'connect':
                if (!isset ($request['support_sockets']))
                    $request['support_sockets'] = false;
                
                $this->clients[$ip .':'. $port] = new User ($ip, $port);
                $this->clients[$ip .':'. $port]->supportSockets = (bool) $request['support_sockets'];

                $generator = new Generator ($request['g'], $request['p']);
                $this->clients[$ip .':'. $port]->secret = urlencode (hash ('sha512',
                    $generator->generate ($request['alpha']), true));

                echo $this->encode (array (
                    'alpha'   => $generator->getAlpha (),
                    'clients' => array_map (function ($client)
                    {
                        return $client->toArray ();
                    }, $self->clients)
                ));

                break;

            case 'push':
                if (!isset ($request['reciever']) || !isset ($request['data']) || !isset ($request['mask']) || !isset ($this->clients[$request['reciever']]))
                    break;

                $this->stack[$request['reciever']][] = array
                (
                    'timestamp' => time (),
                    'author'    => $ip .':'. $port,
                    'data'      => $this->xorcode ($request['data'], $this->clients[$ip .':'. $port]->secret),
                    'mask'      => crc32 ($request['mask'])
                );

                echo $this->encode ('ok');

                break;

            case 'pop':
                echo $this->encode (isset ($this->stack[$ip .':'. $port]) ?
                    array_map (function ($data) use ($ip, $port)
                    {
                        $data['data'] = $this->xorcode ($data['data'], $this->clients[$ip .':'. $port]->secret);

                        return $data;
                    }, $this->stack[$ip .':'. $port]) : array ());

                unset ($this->stack[$ip .':'. $port]);

                break;

            default:
                if ($callback)
                    $callback ($request, $client);

                break;
        }

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
        return urlencode (base64_encode (serialize ($data)));
    }

    public static function decode ($data)
    {
        return unserialize (base64_decode (urldecode ($data)));
    }

    public static function xorcode ($data, $key)
    {
        return $data ^ str_repeat ($key, ceil (strlen ($data) / strlen ($key)));
    }
}
