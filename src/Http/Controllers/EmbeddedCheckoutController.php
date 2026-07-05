<?php

namespace FastUcp\Http\Controllers;

use FastUcp\Protocols\EmbeddedCheckoutProtocol;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmbeddedCheckoutController extends Controller
{
    /**
     * GET /ucp/embedded-checkout/{sessionId}?ec_delegate=payment.credential,...
     *
     * Serves the merchant checkout UI destined for the host's iframe.
     * The ec_delegate query parameter lists operations the host wants to
     * handle natively; we negotiate against what we support and hand the
     * accepted set to the JS bridge for the ec.ready handshake.
     */
    public function show(Request $request, EmbeddedCheckoutProtocol $protocol, string $sessionId): View
    {
        $viewData = $protocol->prepareView(
            sessionId: $sessionId,
            ecDelegateParam: $request->query('ec_delegate'),
            hostOrigin: $request->query('ec_origin', $request->headers->get('referer')
                ? parse_url($request->headers->get('referer'), PHP_URL_SCHEME).'://'.parse_url($request->headers->get('referer'), PHP_URL_HOST)
                : null),
        );

        return view('ucp::embedded-checkout', $viewData);
    }
}
