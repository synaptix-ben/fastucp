<?php

namespace FastUcp\Protocols;

use FastUcp\Exceptions\UcpException;
use FastUcp\UcpManager;
use Throwable;

/**
 * MCP (Model Context Protocol) server: JSON-RPC 2.0 over HTTP POST.
 * Bridges tools/call requests into the same handlers the REST layer uses.
 */
class McpProtocol
{
    public const PROTOCOL_VERSION = '2024-11-05';

    public function __construct(protected UcpManager $manager) {}

    public function handle(array $payload): array
    {
        $method = $payload['method'] ?? null;
        $params = $payload['params'] ?? [];
        $id = $payload['id'] ?? null;

        return match ($method) {
            'initialize' => $this->initialize($id),
            'notifications/initialized' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) []],
            'tools/list' => $this->toolsList($id),
            'tools/call' => $this->toolsCall($id, $params),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };
    }

    protected function initialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => [
                    'name' => $this->manager->title(),
                    'version' => $this->manager->version(),
                ],
            ],
        ];
    }

    protected function toolsList(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => ['tools' => $this->manager->toolDefinitions()],
        ];
    }

    protected function toolsCall(mixed $id, array $params): array
    {
        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        $knownTools = array_column($this->manager->toolDefinitions(), 'name');
        if (! in_array($toolName, $knownTools, true)) {
            return $this->error($id, -32601, "Tool not found: {$toolName}");
        }

        try {
            $sessionId = $arguments['id'] ?? $arguments['session_id'] ?? $arguments['checkout_id'] ?? null;

            $result = $this->manager->callHandler($toolName, $sessionId, $arguments);

            $resultData = is_object($result) && method_exists($result, 'toArray')
                ? $result->toArray()
                : $result;

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        ['type' => 'text', 'text' => json_encode($resultData)],
                    ],
                    'structuredContent' => $resultData,
                ],
            ];
        } catch (UcpException $e) {
            return $this->toolError($id, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->toolError($id, 'Error executing tool: '.$e->getMessage());
        }
    }

    /**
     * Tool-level errors are returned as successful JSON-RPC responses with
     * isError=true, per the MCP specification.
     */
    protected function toolError(mixed $id, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'content' => [['type' => 'text', 'text' => $message]],
                'isError' => true,
            ],
        ];
    }

    public function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'error' => ['code' => $code, 'message' => $message],
            'id' => $id,
        ];
    }
}
