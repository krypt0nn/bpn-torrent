<?php

namespace BPN;

class Http
{
    public array $header = [
        'HTTP/1.1 200 OK',
        'Date: Fri, 31 Dec 1999 23:59:59 GMT',
        'Content-Type: text/html'
    ];

    public function __toString (): string
    {
        return implode ("\r\n", $this->header) ."\r\n\r\n";
    }
}
