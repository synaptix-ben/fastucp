<?php

namespace FastUcp\Http\Controllers;

use FastUcp\Protocols\A2aProtocol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class A2aController extends Controller
{
    /**
     * GET /.well-known/agent-card.json
     */
    public function agentCard(A2aProtocol $protocol): JsonResponse
    {
        return response()->json($protocol->agentCard());
    }

    /**
     * POST /ucp/agent/message
     */
    public function handleMessage(Request $request, A2aProtocol $protocol): JsonResponse
    {
        $body = $request->json()->all();

        if ($body === []) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $response = $protocol->handleMessage($body);

        return response()->json($response, isset($response['error']) ? 500 : 200);
    }
}
