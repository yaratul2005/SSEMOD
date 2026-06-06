/**
 * Client Presence Tracker - listens to online counter stream
 */
class PresenceTracker {
    constructor() {
        this.sseManager = null;
        this.counterEl = document.getElementById('online-counter');
    }

    /**
     * Start listening to the presence stream.
     */
    start() {
        // Build path relative to the page URL
        this.sseManager = new SSEManager(
            'stream/presence',
            (event, data) => {
                if (event === 'presence' && data && typeof data.count === 'number') {
                    this.counterEl.textContent = `${data.count} online`;
                }
            },
            (err) => {
                console.error('[Presence] Connection error:', err);
            },
            'PresenceStream'
        );
        this.sseManager.connect();
    }

    /**
     * Terminate presence stream connection.
     */
    stop() {
        if (this.sseManager) {
            this.sseManager.disconnect();
        }
    }
}
