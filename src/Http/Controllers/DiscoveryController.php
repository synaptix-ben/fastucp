<?php

namespace FastUcp\Http\Controllers;

use FastUcp\UcpManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DiscoveryController extends Controller
{
    /**
     * GET /.well-known/ucp
     *
     * The UCP discovery manifest. CORS headers are required so agent
     * platforms can fetch the profile cross-origin.
     */
    public function manifest(UcpManager $manager): JsonResponse
    {
        return response()
            ->json($manager->buildManifest()->toArray())
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
