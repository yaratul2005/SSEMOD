/**
 * Real-time SSE Connection Manager with exponential backoff & polling fallback.
 */
class SSEManager {
    constructor(endpoint, onMessage, onError, streamType) {
        this.endpoint = endpoint;
        this.onMessage = onMessage;
        this.onError = onError;
        this.streamType = streamType;
        this.eventSource = null;
        this.reconnectTimeout = null;
        this.backoffMs = 1000;
        this.maxBackoffMs = 15000;
        this.lastEventId = 0;
        this.usePolling = !window.EventSource;
        this.pollInterval = 3000;
        this.pollTimeout = null;
        this.isConnected = false;
    }

    /**
     * Establish EventSource connection.
     */
    connect() {
        this.disconnect();

        if (this.usePolling) {
            this.startPolling();
            return;
        }

        let absoluteEndpoint = this.endpoint;
        if (!absoluteEndpoint.startsWith('http://') && !absoluteEndpoint.startsWith('https://') && !absoluteEndpoint.startsWith('//')) {
            const cleanPath = absoluteEndpoint.startsWith('/') ? absoluteEndpoint : '/' + absoluteEndpoint;
            absoluteEndpoint = (window.BASE_URL || '') + cleanPath;
        }
        const url = new URL(absoluteEndpoint, window.location.origin);
        if (this.lastEventId) {
            url.searchParams.set('last_id', String(this.lastEventId));
        }
        // Cache buster
        url.searchParams.set('cb', Date.now().toString());

        this.eventSource = new EventSource(url.toString());

        this.eventSource.addEventListener('connected', (e) => {
            try {
                const data = JSON.parse(e.data);
                this.backoffMs = 1000; // Reset backoff on success
                this.isConnected = true;
                this.pollInterval = (data.poll_interval || 3) * 1000;
                console.log(`[SSE] Connected to ${this.streamType}. Heartbeat: ${data.heartbeat}s, Poll: ${data.poll_interval}s`);
            } catch (err) {
                console.error('[SSE] Failed to parse handshake:', err);
            }
        });

        // Set up message event listener
        this.eventSource.addEventListener('message', (e) => {
            if (e.lastEventId) {
                this.lastEventId = parseInt(e.lastEventId, 10);
            }
            try {
                const data = JSON.parse(e.data);
                this.onMessage('message', data);
            } catch (err) {
                console.error('[SSE] JSON parse error on message:', err);
            }
        });

        // Register custom event listeners
        const events = ['typing', 'matched', 'wait_status', 'presence', 'room_closed', 'ban', 'system'];
        events.forEach(evt => {
            this.eventSource.addEventListener(evt, (e) => {
                try {
                    const data = JSON.parse(e.data);
                    if (evt === 'system' && data.action === 'reconnect') {
                        console.log(`[SSE] Server-requested reconnect: ${data.reason}`);
                        this.reconnect(false); // Quick reconnect (no exponential backoff)
                        return;
                    }
                    this.onMessage(evt, data);
                } catch (err) {
                    console.error(`[SSE] JSON parse error on event [${evt}]:`, err);
                }
            });
        });

        this.eventSource.onerror = (err) => {
            console.warn('[SSE] EventSource connection error. Scheduling reconnect...');
            this.isConnected = false;
            if (this.onError) {
                this.onError(err);
            }
            this.scheduleReconnect();
        };
    }

    /**
     * Schedule a reconnection attempt with exponential backoff.
     */
    scheduleReconnect() {
        if (this.reconnectTimeout) {
            clearTimeout(this.reconnectTimeout);
        }

        console.log(`[SSE] Reconnecting in ${this.backoffMs}ms`);
        this.reconnectTimeout = setTimeout(() => {
            this.backoffMs = Math.min(this.backoffMs * 1.5, this.maxBackoffMs);
            this.connect();
        }, this.backoffMs);
    }

    /**
     * Trigger a manual reconnection.
     */
    reconnect(withBackoff = true) {
        this.disconnect();
        if (withBackoff) {
            this.scheduleReconnect();
        } else {
            this.connect();
        }
    }

    /**
     * Close EventSource connection and clear timers.
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        if (this.reconnectTimeout) {
            clearTimeout(this.reconnectTimeout);
            this.reconnectTimeout = null;
        }
        if (this.pollTimeout) {
            clearTimeout(this.pollTimeout);
            this.pollTimeout = null;
        }
        this.isConnected = false;
    }

    /**
     * Fallback to active AJAX polling if SSE is blocked or unsupported.
     */
    startPolling() {
        console.log('[SSE] Fallback AJAX polling started.');
        
        const poll = async () => {
            try {
                let absoluteEndpoint = this.endpoint;
                if (!absoluteEndpoint.startsWith('http://') && !absoluteEndpoint.startsWith('https://') && !absoluteEndpoint.startsWith('//')) {
                    const cleanPath = absoluteEndpoint.startsWith('/') ? absoluteEndpoint : '/' + absoluteEndpoint;
                    absoluteEndpoint = (window.BASE_URL || '') + cleanPath;
                }
                const url = new URL(absoluteEndpoint, window.location.origin);
                if (this.lastEventId) {
                    url.searchParams.set('last_id', String(this.lastEventId));
                }
                url.searchParams.set('fallback_poll', '1');

                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data && Array.isArray(data.events)) {
                        data.events.forEach(evt => {
                            if (evt.id) {
                                this.lastEventId = parseInt(evt.id, 10);
                            }
                            this.onMessage(evt.event, evt.data);
                        });
                    }
                }
            } catch (err) {
                console.error('[SSE] Polling error:', err);
            }
            this.pollTimeout = setTimeout(poll, this.pollInterval);
        };

        this.pollTimeout = setTimeout(poll, 1000);
    }
}
