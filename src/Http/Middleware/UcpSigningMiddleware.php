<?php

namespace FastUcp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs JSON responses with a detached JWS (ES256) in the UCP-Signature
 * header. Requires web-token/jwt-framework:
 *
 *   composer require web-token/jwt-framework
 */
class UcpSigningMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('ucp.signing.enabled') || ! config('ucp.signing.key')) {
            return $response;
        }

        $contentType = $response->headers->get('content-type', '');
        if (! str_starts_with($contentType, 'application/json')) {
            return $response;
        }

        try {
            $signature = $this->sign($response->getContent());
            if ($signature !== null) {
                $response->headers->set('UCP-Signature', $signature);
            }
        } catch (\Throwable $e) {
            // A signing failure must not break the response; log and continue.
            report($e);
        }

        return $response;
    }

    protected function sign(string $payload): ?string
    {
        if (! class_exists(\Jose\Component\Signature\JWSBuilder::class)) {
            logger()->warning('UCP signing enabled but web-token/jwt-framework is not installed.');

            return null;
        }

        $jwk = \Jose\Component\Core\JWK::createFromJson(config('ucp.signing.key'));
        $algorithm = new \Jose\Component\Signature\Algorithm\ES256();

        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder(
            new \Jose\Component\Core\AlgorithmManager([$algorithm])
        );

        $header = ['alg' => 'ES256'];
        if ($jwk->has('kid')) {
            $header['kid'] = $jwk->get('kid');
        }

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, $header)
            ->build();

        return (new \Jose\Component\Signature\Serializer\CompactSerializer())->serialize($jws);
    }
}
