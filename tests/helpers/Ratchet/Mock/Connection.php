<?php

namespace Ratchet\Mock;

use Ratchet\ConnectionInterface;

#[\AllowDynamicProperties]
class Connection implements ConnectionInterface
{
    public array $last = [
        'send'  => ''
      , 'close' => false
    ];

    public string $remoteAddress = '127.0.0.1';

    public function send($data): void
    {
        $this->last[__FUNCTION__] = $data;
    }

    public function close(): void
    {
        $this->last[__FUNCTION__] = true;
    }
}
