<?php

declare(strict_types=1);

namespace Jocoonopa\LaravelQueryMonitor;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Server;

class ListenQueries
{
    /**
     * @var Closure
     */
    private $info;

    /**
     * @var Closure
     */
    private $warn;

    /**
     * @var Closure
     */
    private $comment;

    /**
     * @var Closure
     */
    private $line;

    /**
     * @var boolean
     */
    private $debug = false;

    function __construct(string $host, $port, $moreThanMiliseconds)
    {
        $this->host = $host;
        $this->port = $port;
        $this->moreThanMiliseconds = $moreThanMiliseconds;
        $this->loop = Factory::create();
        $this->socket = new Server($host.':'.$port, $this->loop);
    }

    public function setInfo(Closure $info)
    {
        $this->info = $info;
    }

    public function setWarn(Closure $warn)
    {
        $this->warn = $warn;
    }

    public function setComment(Closure $comment)
    {
        $this->comment = $comment;
    }

    public function setLine(Closure $line)
    {
        $this->line = $line;
    }

    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
    }

    public function run()
    {
        call_user_func($this->info, 'Listen SQL queries on '.$this->host.':'.$this->port . PHP_EOL . PHP_EOL);

        $this->socket->on('connection', function (ConnectionInterface $connection) {
            $connection->on('data', function ($data) use ($connection) {
                if ($this->debug) {
                    call_user_func($this->warn, '# Debug:' . $data);
                }

                $query = json_decode($data, true);

                if (is_null($query) || ! Arr::accessible($query)) {
                    call_user_func($this->line, $data);
                } else {
                    $sql = Arr::get($query, 'sql');
                    $time = Arr::get($query, 'time', 0);

                    if (
                        $time > $this->moreThanMiliseconds &&
                        ! $this->shouldIgnore($sql)
                    ) {

                        call_user_func($this->comment, "# Query received:\n");

                        $bindings = Arr::get($query, 'bindings', []);

                        $normalizedBindings = array_map(function($i){
                            return is_string($i) ? '"'.$i.'"' : $i;
                        }, $bindings);

                        $sql = Str::replaceArray('?', $normalizedBindings, $sql);

                        call_user_func($this->info, SqlFormatter::format($sql) . "\n");
                        call_user_func($this->info, '# Seconds: ' . $time / 1000);
                        call_user_func($this->info, PHP_EOL);
                    }
                }

                $connection->close();
            });
        });

        $this->loop->run();
    }

    protected function shouldIgnore($sql): bool
    {
        $ignoreWords = explode(',', config('laravel-query-monitor.ignore_words'));

        return Str::contains($sql, $ignoreWords ?? 'information_schema.tables');
    }
}
