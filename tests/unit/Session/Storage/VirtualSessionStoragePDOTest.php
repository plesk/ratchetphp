<?php

namespace Ratchet\Session\Storage;

use Ratchet\Session\Serialize\PhpHandler;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * @covers Ratchet\Session\Storage\VirtualSessionStorage
 */
class VirtualSessionStoragePDOTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var VirtualSessionStorage
     */
    protected $virtualSessionStorage;

    protected $pathToDB;

    public function setUp(): void
    {
        if (!extension_loaded('PDO') || !extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Session test requires PDO and pdo_sqlite');
        }

        $schema = <<<SQL
CREATE TABLE `sessions` (
    `sess_id` VARBINARY(128) NOT NULL PRIMARY KEY,
    `sess_data` BLOB NOT NULL,
    `sess_time` INTEGER UNSIGNED NOT NULL,
    `sess_lifetime` MEDIUMINT NOT NULL
);
SQL;
        $this->pathToDB = tempnam(sys_get_temp_dir(), 'SQ3');
        $dsn = 'sqlite:' . $this->pathToDB;

        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec($schema);
        $pdo = null;

        $sessionHandler = new PdoSessionHandler($dsn);
        $serializer = new PhpHandler();
        $this->virtualSessionStorage = new VirtualSessionStorage($sessionHandler, 'foobar', $serializer);
        $this->virtualSessionStorage->registerBag(new FlashBag());
        $this->virtualSessionStorage->registerBag(new AttributeBag());
    }

    public function tearDown(): void
    {
        unlink($this->pathToDB);
    }

    public function testStartWithDSN()
    {
        $this->virtualSessionStorage->start();

        $this->assertTrue($this->virtualSessionStorage->isStarted());
    }
}
