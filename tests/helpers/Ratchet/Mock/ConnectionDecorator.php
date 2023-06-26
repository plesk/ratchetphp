<?php

namespace Ratchet\Mock;

use Ratchet\AbstractConnectionDecorator;

class ConnectionDecorator extends AbstractConnectionDecorator
{
    public array $last = [
        'write' => ''
      , 'end'   => false
    ];

    public function send($data): void
    {
        $this->last[__FUNCTION__] = $data;

        $this->getConnection()->send($data);
    }

    public function close(): void
    {
        $this->last[__FUNCTION__] = true;

        $this->getConnection()->close();
    }
}
