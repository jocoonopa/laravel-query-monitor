<?php declare(strict_types=1);

namespace Jocoonopa\LaravelQueryMonitor;

use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

class DispatchQueries
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var Connector
     */
    private $connector;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->loop = Factory::create();
        $this->connector = new Connector($this->loop);
    }

    public function send($mixed): void
    {
        $this->connector
            ->connect($this->host . ':' . $this->port)
            ->then(function (ConnectionInterface $connection) use ($mixed) {
                $mixed = is_string($mixed) ? $mixed : json_encode($mixed);

                $connection->write($mixed);
            });

        $this->loop->run();
    }
}
