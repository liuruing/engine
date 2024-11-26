<?php

declare(strict_types=1);

namespace Hyperf\Engine\WebSocket;

use Hyperf\WebSocketClient\ClientInterface;
use Hyperf\WebSocketClient\Client as BaseClient;
use Hyperf\WebSocketClient\Frame;
use Swoole\Coroutine\Http\Client as SwooleClient;
use Hyperf\WebSocketClient\Constant\Opcode;
use Hyperf\HttpMessage\Uri\Uri;

class Client extends BaseClient implements ClientInterface
{
    protected SwooleClient $client;

    public function __construct(protected Uri $uri, array $headers = [])
    {
        $host = $uri->getHost();
        $port = $uri->getPort();
        $ssl = $uri->getScheme() === 'wss';

        $this->client = new SwooleClient($host, $port ?: ($ssl ? 443 : 80), $ssl);
        $this->setHeaders($headers);
        parent::__construct($uri, $headers);
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        $this->client->setHeaders($headers);
        return $this;
    }

    protected function connectInternal(string $path): bool 
    {
        return $this->client->upgrade($path);
    }

    protected function recvInternal(float $timeout = -1): Frame
    {
        $data = $this->client->recv($timeout);
        if ($data instanceof \Swoole\WebSocket\Frame) {
            return new Frame($data->finish, $data->opcode, $data->data);
        }
        return new Frame(false, 0, '');
    }

    protected function getFrame(mixed $data): bool|Frame
    {
        if ($data instanceof \Swoole\WebSocket\Frame) {
            return new Frame($data->finish, $data->opcode, $data->data);
        }
        return false;
    }

    protected function recvData(float $timeout = -1): mixed
    {
        return $this->client->recv($timeout);
    }

    public function push(string $data, int $opcode = Opcode::TEXT, ?int $flags = null): bool
    {
        return $this->client->push($data, $opcode, $flags);
    }

    public function close(): bool
    {
        return $this->client->close();
    }

    public function getErrCode(): int
    {
        return $this->client->errCode;
    }

    public function getErrMsg(): string
    {
        return $this->client->errMsg;
    }
}