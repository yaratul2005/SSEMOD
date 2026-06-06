/**
 * Client Random Chat Modal Manager - handles Omegle-style matching inside overlay modal cards.
 */
class RandomModalManager {
    constructor(app) {
        this.app = app;
        this.roomId = null;
        this.queueSse = null;
        this.chatSse = null;
        this.typingTimeout = null;

        // Modal Elements
        this.modalOverlay = document.getElementById('random-chat-modal');
        this.modalCard = document.getElementById('random-modal-card');
        this.closeBtn = document.getElementById('btn-random-modal-close');
        
        // Phase Views
        this.phaseWaiting = document.getElementById('random-phase-waiting');
        this.phaseMatched = document.getElementById('random-phase-matched');
        this.phaseDisconnected = document.getElementById('random-phase-disconnected');

        // Phase 1 - Waiting elements
        this.waitStatusEl = document.getElementById('random-wait-status-text');
        this.cancelQueueBtn = document.getElementById('btn-random-cancel-queue');

        // Phase 2 - Matched elements
        this.messageListEl = document.getElementById('random-message-list');
        this.messageInputEl = document.getElementById('random-message-input');
        this.sendBtn = document.getElementById('btn-random-send');
        this.typingBadge = document.getElementById('random-typing-badge');
        this.reportBtn = document.getElementById('btn-random-report');
        this.nextBtn = document.getElementById('btn-random-next');

        // Phase 3 - Disconnected elements
        this.nextRequeueBtn = document.getElementById('btn-random-next-requeue');
        this.closeReturnBtn = document.getElementById('btn-random-close-return');

        this.setupEvents();
    }

    /**
     * Wire up modal action triggers.
     */
    setupEvents() {
        this.closeBtn.addEventListener('click', () => this.close());
        this.cancelQueueBtn.addEventListener('click', () => this.cancelQueue());
        this.closeReturnBtn.addEventListener('click', () => this.close());
        
        this.nextBtn.addEventListener('click', () => this.triggerNextChat());
        this.nextRequeueBtn.addEventListener('click', () => this.startWaitingQueue());

        this.sendBtn.addEventListener('click', () => this.submitMessage());
        this.messageInputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                this.submitMessage();
            } else {
                this.triggerTypingIndicator();
            }
        });

        this.reportBtn.addEventListener('click', () => this.submitReport());
    }

    /**
     * Open RandomChat Modal and start wait queue.
     */
    open() {
        this.modalOverlay.classList.add('active');
        this.startWaitingQueue();
    }

    /**
     * Close modal, cancel queue, disconnect chats, and return to Arena.
     */
    close() {
        this.cancelQueue();
        this.disconnectChat();
        this.modalOverlay.classList.remove('active');
    }

    /**
     * Set up Phase 1 (Waiting Queue).
     */
    async startWaitingQueue() {
        this.disconnectChat();
        
        // Show Phase 1 view
        this.phaseWaiting.style.display = 'flex';
        this.phaseMatched.style.display = 'none';
        this.phaseDisconnected.style.display = 'none';
        
        this.waitStatusEl.textContent = 'Estimated wait time: calculating...';

        try {
            // Join Random Queue
            const response = await fetch('queue/join', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': this.app.getCsrfToken()
                }
            });

            if (response.ok) {
                const data = await response.json();
                
                // Connect to matching queue stream
                this.queueSse = new SSEManager(
                    'stream/queue',
                    (event, data) => {
                        if (event === 'matched' && data.room_id) {
                            this.onMatched(data.room_id);
                        } else if (event === 'wait_status' && data) {
                            const size = data.queue_length || 0;
                            const sec = data.estimated_wait_seconds || 2;
                            this.waitStatusEl.textContent = `Estimated wait time: ~${sec}s (${size} in queue)`;
                        }
                    },
                    (err) => console.error('[RandomModal] Queue SSE error:', err),
                    'RandomQueueStream'
                );
                this.queueSse.connect();

                if (data.status === 'matched') {
                    this.onMatched(data.room_id);
                }
            } else {
                const data = await response.json();
                this.waitStatusEl.textContent = data.error || 'Failed to enter queue.';
            }
        } catch (err) {
            console.error('[RandomModal] Queue enter error:', err);
            this.waitStatusEl.textContent = 'Connection error occurred.';
        }
    }

    /**
     * Cancel match queue.
     */
    cancelQueue() {
        if (this.queueSse) {
            this.queueSse.disconnect();
            this.queueSse = null;
        }
        fetch('queue/leave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': this.app.getCsrfToken()
            }
        }).catch(err => console.error('Cancel queue notify error:', err));
    }

    /**
     * Phase 2 (Matched) transition.
     */
    onMatched(roomId) {
        // Stop queue listener
        if (this.queueSse) {
            this.queueSse.disconnect();
            this.queueSse = null;
        }

        this.roomId = roomId;
        this.messageListEl.innerHTML = '';
        this.messageInputEl.value = '';
        this.messageInputEl.disabled = false;
        this.sendBtn.disabled = false;
        this.typingBadge.style.display = 'none';

        // Display green flash
        this.modalCard.classList.add('flash-green-match');
        setTimeout(() => this.modalCard.classList.remove('flash-green-match'), 800);

        this.phaseWaiting.style.display = 'none';
        this.phaseMatched.style.display = 'flex';
        this.messageInputEl.focus();

        this.renderSystemMessage('You are now connected with a random stranger.');

        // Play chime sound
        this.playMatchedChime();

        // Subscribe to chat stream
        this.chatSse = new SSEManager(
            `stream/chat?room_id=${encodeURIComponent(roomId)}`,
            (event, data) => this.handleChatEvent(event, data),
            (err) => console.error('[RandomModal] Chat stream error:', err),
            'RandomChatStream'
        );
        this.chatSse.connect();
    }

    /**
     * Route message events inside modal.
     */
    handleChatEvent(event, data) {
        if (event === 'message') {
            const isMe = data.sender_id === this.app.getUserId();
            this.renderMessageBubble(data.content, isMe);
            if (!isMe) {
                this.playMatchedChime();
            }
        } else if (event === 'typing') {
            this.typingBadge.style.display = data.typing ? 'inline-block' : 'none';
        } else if (event === 'room_closed') {
            this.onStrangerDisconnected();
        }
    }

    /**
     * Send direct message.
     */
    async submitMessage() {
        const text = this.messageInputEl.value.trim();
        if (text === '' || !this.roomId) return;

        this.messageInputEl.value = '';
        this.messageInputEl.focus();

        try {
            const response = await fetch('chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': this.app.getCsrfToken()
                },
                body: `room_id=${encodeURIComponent(this.roomId)}&content=${encodeURIComponent(text)}`
            });

            if (!response.ok) {
                const data = await response.json();
                this.renderSystemMessage(data.error || 'Failed to deliver message.', true);
            }
        } catch (err) {
            console.error('[RandomModal] Send error:', err);
            this.renderSystemMessage('Network delivery error.', true);
        }
    }

    /**
     * Dispatch typing notifications.
     */
    triggerTypingIndicator() {
        if (this.typingTimeout || !this.roomId) return;

        fetch('chat/typing', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': this.app.getCsrfToken()
            },
            body: `room_id=${encodeURIComponent(this.roomId)}`
        }).catch(err => console.error('Typing indicator error:', err));

        this.typingTimeout = setTimeout(() => {
            this.typingTimeout = null;
        }, 2000);
    }

    /**
     * Re-queue directly from chat.
     */
    async triggerNextChat() {
        this.messageInputEl.disabled = true;
        this.sendBtn.disabled = true;
        
        try {
            const response = await fetch('chat/next', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': this.app.getCsrfToken()
                }
            });

            if (response.ok) {
                this.disconnectChat();
                this.startWaitingQueue();
            }
        } catch (err) {
            console.error('[RandomModal] Next chat navigation error:', err);
            this.messageInputEl.disabled = false;
            this.sendBtn.disabled = false;
        }
    }

    /**
     * Report user from modal.
     */
    async submitReport() {
        if (!this.roomId) return;
        
        const reportModal = document.getElementById('report-modal');
        const submitBtn = document.getElementById('btn-report-submit');
        
        reportModal.classList.add('active');
        
        const triggerSubmit = async () => {
            const reason = document.getElementById('report-reason-select').value;
            reportModal.classList.remove('active');
            submitBtn.removeEventListener('click', triggerSubmit);
            
            try {
                const response = await fetch('chat/report', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': this.app.getCsrfToken()
                    },
                    body: `room_id=${encodeURIComponent(this.roomId)}&reason=${encodeURIComponent(reason)}`
                });

                if (response.ok) {
                    this.disconnectChat();
                    this.phaseMatched.style.display = 'none';
                    this.phaseDisconnected.style.display = 'flex';
                    this.app.showToast('Stranger reported. Chat closed.', 'danger');
                }
            } catch (err) {
                console.error('[RandomModal] Report submit error:', err);
            }
        };
        
        submitBtn.addEventListener('click', triggerSubmit);
    }

    /**
     * Phase 3 (Disconnected) transition.
     */
    onStrangerDisconnected() {
        this.disconnectChat();

        // Display red flash
        this.modalCard.classList.add('flash-red-disconnect');
        setTimeout(() => this.modalCard.classList.remove('flash-red-disconnect'), 800);

        this.phaseMatched.style.display = 'none';
        this.phaseDisconnected.style.display = 'flex';
    }

    /**
     * Disconnect active chat streams.
     */
    disconnectChat() {
        if (this.chatSse) {
            this.chatSse.disconnect();
            this.chatSse = null;
        }
        this.roomId = null;
    }

    /**
     * Inject message bubble.
     */
    renderMessageBubble(content, isMe) {
        const wrapper = document.createElement('div');
        wrapper.className = `message-wrapper ${isMe ? 'me' : 'stranger'}`;

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = content;

        wrapper.appendChild(bubble);
        this.messageListEl.appendChild(wrapper);
        this.messageListEl.scrollTop = this.messageListEl.scrollHeight;
    }

    /**
     * Render notice tags.
     */
    renderSystemMessage(text, isError = false) {
        const msg = document.createElement('div');
        msg.className = `message-system ${isError ? 'error' : ''}`;
        msg.textContent = text;
        this.messageListEl.appendChild(msg);
        this.messageListEl.scrollTop = this.messageListEl.scrollHeight;
    }

    /**
     * Play beep chimes.
     */
    playMatchedChime() {
        if (!this.app.soundEnabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); 
            osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.08); 
            
            gain.gain.setValueAtTime(0.04, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);

            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {
            console.warn('Audio play failed:', e);
        }
    }
}
