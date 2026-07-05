/**
 * UCP Embedded Checkout Protocol (ECP) bridge.
 *
 * Runs inside the merchant checkout page when it is embedded in a host's
 * iframe/webview. Speaks JSON-RPC 2.0 over postMessage with the host, per
 * the UCP embedded-checkout binding:
 *
 *   - Handshake: sends an `ec.ready` request listing the delegations this
 *     checkout accepted (negotiated server-side from ?ec_delegate=...).
 *   - Delegation: when the buyer triggers a delegated operation (select
 *     payment instrument, change address, provide payment credential),
 *     the bridge sends the matching `*_request` to the host and resolves
 *     with the host's response.
 *   - Notifications: emits `ec.checkout.updated` / `ec.checkout.completed`
 *     notifications (no id, no response expected).
 *   - Port upgrade: if the host responds to `ec.ready` with a MessagePort,
 *     all subsequent traffic moves to that dedicated channel so sensitive
 *     data (payment credentials) never transits the window boundary.
 *
 * Security: every incoming window message is origin-checked. The bridge
 * pins the first origin that answers `ec.ready` (or a configured
 * whitelist) and drops messages from anywhere else.
 */
(function (global) {
    'use strict';

    class EcpBridge {
        /**
         * @param {Object} options
         * @param {string[]} options.acceptedDelegates Delegations negotiated server-side
         * @param {string[]|null} options.allowedOrigins Origin whitelist (null = pin first responder)
         * @param {number} options.requestTimeoutMs Per-request timeout (default 60s)
         */
        constructor(options = {}) {
            this.acceptedDelegates = options.acceptedDelegates || [];
            this.allowedOrigins = options.allowedOrigins || null;
            this.requestTimeoutMs = options.requestTimeoutMs || 60000;

            this.hostOrigin = null;   // pinned after handshake
            this.port = null;          // MessagePort after upgrade
            this.nextId = 1;
            this.pending = new Map();  // id -> {resolve, reject, timer}
            this.ready = false;

            this._onWindowMessage = this._onWindowMessage.bind(this);
            this._onPortMessage = this._onPortMessage.bind(this);

            global.addEventListener('message', this._onWindowMessage);
        }

        /**
         * Perform the ec.ready handshake with the host.
         * Resolves with the host's response result (may include host capabilities).
         */
        async connect() {
            if (!global.parent || global.parent === global) {
                // Not embedded — run standalone with no host.
                this.ready = false;
                return null;
            }

            const result = await this._request('ec.ready', {
                delegate: this.acceptedDelegates,
                protocol_version: '2026-01-11',
            }, { broadcast: true });

            this.ready = true;
            return result;
        }

        /**
         * Ask the host for a payment credential (token). Only valid when
         * 'payment.credential' was delegated.
         *
         * @param {Object} params e.g. { handler_id, total, currency }
         * @returns {Promise<Object>} { credential: {...} }
         */
        requestPaymentCredential(params) {
            this._assertDelegated('payment.credential');
            return this._request('ec.payment.credential_request', params);
        }

        /**
         * Ask the host to present its native payment instrument picker.
         */
        requestInstrumentsChange(params) {
            this._assertDelegated('payment.instruments_change');
            return this._request('ec.payment.instruments_change_request', params);
        }

        /**
         * Ask the host to present its native address picker.
         * @returns {Promise<Object>} { address: {...} }
         */
        requestAddressChange(params) {
            this._assertDelegated('fulfillment.address_change');
            return this._request('ec.fulfillment.address_change_request', params);
        }

        /** Notify the host that checkout state changed (totals, items...). */
        notifyUpdated(checkout) {
            this._notify('ec.checkout.updated', { checkout });
        }

        /** Notify the host that the order completed. */
        notifyCompleted(order) {
            this._notify('ec.checkout.completed', { order });
        }

        /** Notify the host that the buyer canceled. */
        notifyCanceled() {
            this._notify('ec.checkout.canceled', {});
        }

        isDelegated(name) {
            return this.acceptedDelegates.indexOf(name) !== -1;
        }

        destroy() {
            global.removeEventListener('message', this._onWindowMessage);
            if (this.port) {
                this.port.close();
                this.port = null;
            }
            this.pending.forEach(function (entry) {
                clearTimeout(entry.timer);
                entry.reject(new Error('ECP bridge destroyed'));
            });
            this.pending.clear();
        }

        // ------------------------------------------------------------------

        _assertDelegated(name) {
            if (!this.isDelegated(name)) {
                throw new Error('Delegation "' + name + '" was not accepted by this checkout');
            }
        }

        _request(method, params, opts) {
            const id = this.nextId++;
            const message = { jsonrpc: '2.0', id: id, method: method, params: params || {} };

            return new Promise((resolve, reject) => {
                const timer = setTimeout(() => {
                    this.pending.delete(id);
                    reject(new Error('ECP request timed out: ' + method));
                }, this.requestTimeoutMs);

                this.pending.set(id, { resolve: resolve, reject: reject, timer: timer });
                this._post(message, opts);
            });
        }

        _notify(method, params) {
            // Notifications carry no id and expect no response.
            this._post({ jsonrpc: '2.0', method: method, params: params || {} });
        }

        _post(message, opts) {
            if (this.port) {
                this.port.postMessage(message);
                return;
            }

            // Before the handshake pins an origin we must broadcast ('*');
            // afterwards we always target the pinned origin.
            const target = (opts && opts.broadcast) || !this.hostOrigin ? '*' : this.hostOrigin;
            global.parent.postMessage(message, target);
        }

        _onWindowMessage(event) {
            if (!this._originAllowed(event.origin)) {
                return;
            }

            const data = event.data;
            if (!data || data.jsonrpc !== '2.0') {
                return;
            }

            // Pin the host origin on the first valid protocol message.
            if (!this.hostOrigin) {
                this.hostOrigin = event.origin;
            }

            // Port upgrade: host hands us a dedicated MessagePort alongside
            // its ec.ready response for sensitive traffic.
            if (event.ports && event.ports.length > 0 && !this.port) {
                this.port = event.ports[0];
                this.port.onmessage = this._onPortMessage;
                this.port.start && this.port.start();
            }

            this._dispatch(data);
        }

        _onPortMessage(event) {
            const data = event.data;
            if (!data || data.jsonrpc !== '2.0') {
                return;
            }
            this._dispatch(data);
        }

        _dispatch(data) {
            // Response to one of our requests.
            if (data.id !== undefined && (data.result !== undefined || data.error !== undefined)) {
                const entry = this.pending.get(data.id);
                if (!entry) {
                    return;
                }
                this.pending.delete(data.id);
                clearTimeout(entry.timer);

                if (data.error) {
                    const err = new Error(data.error.message || 'ECP host error');
                    err.code = data.error.code;
                    err.data = data.error.data;
                    entry.reject(err);
                } else {
                    entry.resolve(data.result);
                }
                return;
            }

            // Request or notification from the host.
            if (data.method) {
                const handler = this._hostHandlers && this._hostHandlers[data.method];
                if (handler) {
                    Promise.resolve(handler(data.params || {}))
                        .then((result) => {
                            if (data.id !== undefined) {
                                this._post({ jsonrpc: '2.0', id: data.id, result: result || {} });
                            }
                        })
                        .catch((err) => {
                            if (data.id !== undefined) {
                                this._post({
                                    jsonrpc: '2.0',
                                    id: data.id,
                                    error: { code: -32000, message: String(err && err.message || err) },
                                });
                            }
                        });
                } else if (data.id !== undefined) {
                    this._post({
                        jsonrpc: '2.0',
                        id: data.id,
                        error: { code: -32601, message: 'Method not found: ' + data.method },
                    });
                }
            }
        }

        /**
         * Register a handler for host-initiated methods, e.g.
         * bridge.on('ec.checkout.cancel_request', async () => {...})
         */
        on(method, handler) {
            this._hostHandlers = this._hostHandlers || {};
            this._hostHandlers[method] = handler;
            return this;
        }

        _originAllowed(origin) {
            if (this.hostOrigin) {
                return origin === this.hostOrigin;
            }
            if (this.allowedOrigins === null) {
                return true;
            }
            return this.allowedOrigins.indexOf(origin) !== -1;
        }
    }

    global.UcpEcpBridge = EcpBridge;
})(typeof window !== 'undefined' ? window : this);
