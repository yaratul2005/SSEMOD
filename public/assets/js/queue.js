/**
 * Client Queue Manager - handles waiting, estimated wait time updates, and cycling funny tips
 */
class QueueManager {
    constructor(app) {
        this.app = app;
        this.sseManager = null;
        this.tipEl = document.getElementById('queue-wait-tip');
        this.estimateEl = document.getElementById('estimated-wait-text');
        this.tipInterval = null;
        this.tips = [
            "Waking up a stranger...",
            "Polishing the chat bubbles...",
            "Sorting users by interests tags...",
            "Adjusting SSE heartbeat intervals...",
            "Optimizing FileStore read operations...",
            "Finding someone interesting for you...",
            "Surviving shared hosting limits..."
        ];
    }

    /**
     * Start waiting in queue, connecting to the queue stream.
     */
    start() {
        this.estimateEl.textContent = "Estimated wait time: calculating...";
        this.cycleTips();

        this.sseManager = new SSEManager(
            'stream/queue',
            (event, data) => {
                if (event === 'matched' && data && data.room_id) {
                    this.stop();
                    this.app.onMatched(data.room_id);
                } else if (event === 'wait_status' && data) {
                    const queueSize = data.queue_length || 0;
                    const waitSec = data.estimated_wait_seconds || 2;
                    this.estimateEl.textContent = `Estimated wait time: ~${waitSec}s (${queueSize} waiting in queue)`;
                }
            },
            (err) => {
                console.error('[Queue] connection error:', err);
            },
            'QueueStream'
        );
        this.sseManager.connect();
    }

    /**
     * Terminate queue stream and stop tip rotations.
     */
    stop() {
        if (this.sseManager) {
            this.sseManager.disconnect();
            this.sseManager = null;
        }
        if (this.tipInterval) {
            clearInterval(this.tipInterval);
            this.tipInterval = null;
        }
    }

    /**
     * Rotate engaging messages.
     */
    cycleTips() {
        let index = 0;
        this.tipEl.textContent = `"${this.tips[index]}"`;
        
        // Ensure styling transition is supported
        this.tipEl.style.transition = 'opacity 0.3s ease';
        this.tipEl.style.opacity = '1';

        this.tipInterval = setInterval(() => {
            index = (index + 1) % this.tips.length;
            this.tipEl.style.opacity = '0';
            setTimeout(() => {
                this.tipEl.textContent = `"${this.tips[index]}"`;
                this.tipEl.style.opacity = '1';
            }, 300);
        }, 4000);
    }
}
