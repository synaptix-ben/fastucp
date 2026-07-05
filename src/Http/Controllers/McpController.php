<?php

namespace FastUcp\Http\Controllers;

use FastUcp\Protocols\McpProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class McpController extends Controller
{
    /**
     * POST /ucp/mcp — JSON-RPC 2.0 endpoint.
     */
    public function handle(Request $request, McpProtocol $protocol): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return response()->json(
                $protocol->error(null, -32700, 'Parse error: invalid JSON received.')
            );
        }

        return response()->json($protocol->handle($payload));
    }

    /**
     * GET /ucp/mcp — human-friendly status check.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'online',
            'message' => 'MCP server is running. Use POST with a JSON-RPC 2.0 payload.',
        ]);
    }
}
