<?php

namespace BPN;

class Client
{
    public string $ip = '127.0.0.1';
    public Node $node;
    public array $trackers = [];
    public bool $supportSockets = false;

    public function __construct (int $port = 53236, string $ip = null, bool $supportSockets = false)
    {
        $this->ip = $ip ?? @file_get_contents ('http://api.ipify.org');

        $this->node = new Node ($port);
        $this->supportSockets = $supportSockets;
    }

    public function connect (string $ip, int $port = 53236): Client
    {
        $this->trackers[$ip .':'. $port] = new Pool ($ip, $port, $this->ip, $this->node->port, $this->supportSockets);

        return $this;
    }

    public function update (): Client
    {
        foreach ($this->trackers as $tracker)
            $tracker->update ();

        return $this;
    }

    /**
     * @param string $ip  - IP получателя
     * @param int $port   - port получателя
     * @param mixed $data - информация для отправки
     */
    public function send (string $ip, $data, int $port = 53236, bool $forceRetranslate = true): Client
    {
        if (!$forceRetranslate)
        {
            $support = true;

            foreach ($this->trackers as $tracker)
                if (!$tracker->user ($ip, $port)->supportSockets)
                {
                    $support = false;

                    break;
                }

            if ($support)
            {
                @file_get_contents ('http://'. $ip .':'. $port .'/'. Tracker::encode ($data));

                return $this;
            }
        }

        $mask = sha1 (uniqid (rand (PHP_INT_MIN, PHP_INT_MAX), true));

        foreach ($this->trackers as $tracker)
            $tracker->push ($ip, $port, $data, $mask);

        return $this;
    }

    public function recieve (): array
    {
        $pop = [];

        foreach ($this->trackers as $tracker)
            foreach ($tracker->pop () as $data)
                $pop[$data['mask']] = $data;

        return $pop;
    }

    public function listen (callable $callback = null, bool $cycle = false): Client
    {
        $this->node->listen (function (string $request, Socket $client) use ($callback)
        {
            $request = Tracker::decode (substr ($request, 5, strpos ($request, ' HTTP/') - 5));

            $callback ($request, $client);
        }, $cycle);

        return $this;
    }
}
