<?php

namespace BPN;

use DStructure\{
    Structure,
    Item
};

use Flurex;

class BitPoint
{
    public static Structure $db;
    public static Node $node;

    public function __construct (int $port = 53236)
    {
        self::$db   ??= new Structure (dirname (__DIR__) .'/.BPN-storage', '#~BPN-storage');
        self::$node ??= new Node ($port);

        $this->update ();
    }

    public function listen (callable $callback = null, bool $cycle = false): BitPoint
    {
        self::$node->listen (function (string $request, Socket $client)
        {
            $request = $this->decode (substr ($request, 5, strpos ($request, ' HTTP/') - 5));

            switch ($request['type'] ?? null)
            {
                case 'is_available':
                    $client->write (new Http . $this->encode ([
                        'status' => 'Ok'
                    ]));

                    break;

                case 'connect':
                    socket_getpeername ($client->socket, $ip);

                    if ($ip == '127.0.0.1')
                    {
                        $client->write (new Http . $this->encode ([
                            'error' => 'Localhost connections are not available'
                        ]));

                        break;
                    }

                    $users = (self::$db->get ('users') ?? new Item ([]))->getData ();
                    unset ($users[$ip]);

                    $client->write (new Http . $this->encode ($users));

                    $users[$ip] = new User ($ip, ($request['port'] ?? 53236), @file_get_contents ('http://'. $ip .':'. ($request['port'] ?? 53236) .'/'. $this->encode ([
                        'type' => 'is_available'
                    ]) ? true : false));

                    foreach ($request['users'] as $ip => $user)
                        $users[$ip] = new User ($ip, $user->port, @file_get_contents ('http://'. $ip .':'. $user->port .'/'. $this->encode ([
                            'type' => 'is_available'
                        ]) ? true : false));

                    self::$db->set ('users', new Item ($users));
                    self::$db->save ();

                    break;

                default:
                    if ($callback)
                        $callback ($request, $client);

                    break;
            }
        }, $cycle);

        return $this;
    }

    public function users (array $users = null): array
    {
        $self_users = (self::$db->get ('users') ?? new Item ([]))->getData ();

        if ($users !== null)
        {
            foreach ($users as $user)
                if (!isset ($self_users[$user->ip]))
                    $self_users[$user->ip] = $user;

            self::$db->set ('users', new Item ($self_users));
            self::$db->save ();
        }

        return $self_users;
    }

    public function update (): BitPoint
    {
        $users = (self::$db->get ('users') ?? new Item ([]))->getData ();

        foreach ($users as $ip => $user)
            $users[$ip]->open = @file_get_contents ('http://'. $ip .':'. $users[$ip]->port .'/'. $this->encode ([
                'type' => 'is_available'
            ]) ? true : false);

        return $this;
    }

    public function send (string $to, array $data, int $port = null): ?array
    {
        $to = $this->users ()[$to] ?? new User ($to, $port ?? 53236);

        if ($port !== null && $to->port != $port)
            $to = new User ($to, $port);

        $response = @file_get_contents ('http://'. $to->ip .':'. $to->port .'/'. $this->encode ($data));

        return $response ?
            $this->decode ($response) : null;
    }

    public function broadcast (array $data): array
    {
        $responses = [];
        
        foreach ($this->users () as $ip => $user)
            $responses[$ip] = $this->send ($ip, $data);

        return $responses;
    }

    public function connect (string $ip, int $port = 53236): BitPoint
    {
        $response = $this->decode (file_get_contents ('http://'. $ip .':'. $port .'/'. $this->encode ([
            'type'  => 'connect',
            'port'  => self::$node->port,
            'users' => $this->users ()
        ])));

        if (!isset ($response['error']))
        {
            $new_users = array_diff ($this->users (), $this->users ($response));

            foreach ($new_users as $user)
                $this->connect ($user->ip, $user->port);
        }

        return $this;
    }

    public static function encode (array $data): string
    {
        return urlencode (base64_encode (Flurex::encode (serialize ($data), '#~BPN')));
    }

    public static function decode (string $data): ?array
    {
        return unserialize (Flurex::decode (base64_decode (urldecode ($data)), '#~BPN'));
    }
}
