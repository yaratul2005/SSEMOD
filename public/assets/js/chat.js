/**
 * Client Chat Manager - handles active chatting, messages, typing indicators, chimes, and tab title updates.
 */
class ChatManager {
    constructor(app) {
        this.app = app;
        this.roomId = null;
        this.sseManager = null;
        
        // DOM Elements
        this.messagesEl = document.getElementById('chat-message-list');
        this.textareaEl = document.getElementById('chat-message-input');
        this.sendBtn = document.getElementById('btn-send-msg');
        this.charCounterEl = document.getElementById('char-counter-text');
        this.typingBadge = document.getElementById('stranger-typing-badge');
        this.strangerStatusEl = document.getElementById('stranger-status');
        this.headerBar = document.getElementById('chat-header-bar');
        
        // Report Modal Elements
        this.reportModal = document.getElementById('report-modal');
        this.reportBtn = document.getElementById('btn-report');
        this.reportCancelBtn = document.getElementById('btn-report-cancel');
        this.reportSubmitBtn = document.getElementById('btn-report-submit');
        this.reportReasonSelect = document.getElementById('report-reason-select');
        
        this.nextBtn = document.getElementById('btn-next');
        
        // Typing Debounce
        this.typingTimeout = null;
        
        // Tab Focus & Unread Count Tracker
        this.isTabFocused = true;
        this.unreadMessages = 0;
        this.origTitle = document.title;
        
        this.setupEvents();
    }

    /**
     * Start the chat session.
     */
    start(roomId) {
        this.roomId = roomId;
        this.messagesEl.innerHTML = '';
        this.textareaEl.value = '';
        this.textareaEl.disabled = false;
        this.textareaEl.focus();
        this.sendBtn.disabled = false;
        this.charCounterEl.textContent = '500';
        this.typingBadge.style.display = 'none';
        
        this.strangerStatusEl.style.backgroundColor = 'var(--accent-green)';
        this.strangerStatusEl.style.boxShadow = 'var(--glow-green)';
        this.headerBar.classList.remove('shake', 'pulse-red-border');
        
        this.unreadMessages = 0;

        // Render connected system message
        this.renderSystemMessage('You are now connected with a stranger. Say hello!');

        // Connect SSE chat stream
        const csrfToken = this.app.getCsrfToken();
        const url = `stream/chat?room_id=${encodeURIComponent(roomId)}`;
        
        this.sseManager = new SSEManager(
            url,
            (event, data) => this.handleEvent(event, data),
            (err) => console.error('[Chat] Connection error:', err),
            'ChatStream'
        );
        this.sseManager.connect();
    }

    /**
     * Shut down the chat session.
     */
    stop() {
        if (this.sseManager) {
            this.sseManager.disconnect();
            this.sseManager = null;
        }
        this.roomId = null;
    }

    /**
     * Set up all page events.
     */
    setupEvents() {
        // Tab focus listeners
        window.addEventListener('focus', () => {
            this.isTabFocused = true;
            this.unreadMessages = 0;
            document.title = this.origTitle;
        });

        window.addEventListener('blur', () => {
            this.isTabFocused = false;
        });

        // Message input enter key listener
        this.textareaEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.submitMessage();
            } else {
                this.triggerTypingIndicator();
            }
        });

        this.textareaEl.addEventListener('input', () => {
            const length = this.textareaEl.value.length;
            this.charCounterEl.textContent = String(500 - length);
        });

        this.sendBtn.addEventListener('click', () => this.submitMessage());

        // Navigation controls
        this.nextBtn.addEventListener('click', () => this.triggerNextChat());
        
        // Report Modal controls
        this.reportBtn.addEventListener('click', () => {
            this.reportModal.classList.add('active');
        });

        this.reportCancelBtn.addEventListener('click', () => {
            this.reportModal.classList.remove('active');
        });

        this.reportSubmitBtn.addEventListener('click', () => this.submitReport());
    }

    /**
     * Send typed message using fetch POST request.
     */
    async submitMessage() {
        const text = this.textareaEl.value.trim();
        if (text === '' || !this.roomId) return;

        this.textareaEl.value = '';
        this.charCounterEl.textContent = '500';
        this.textareaEl.focus();

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
                this.renderSystemMessage(data.error || 'Failed to send message.', true);
            }
        } catch (err) {
            console.error('[Chat] Submit error:', err);
            this.renderSystemMessage('Network error occurred. Unable to send.', true);
        }
    }

    /**
     * Dispatch debounced typing notifications to the backend.
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
        }).catch(err => console.error('Typing notify error:', err));

        // Limit typing notifies to once every 2 seconds
        this.typingTimeout = setTimeout(() => {
            this.typingTimeout = null;
        }, 2000);
    }

    /**
     * Handle incoming SSE channel events.
     */
    handleEvent(event, data) {
        if (event === 'message') {
            const isMe = data.sender_id === this.app.getUserId();
            this.renderMessageBubble(data.content, isMe, data.sent_at);
            
            if (!isMe) {
                this.playNotificationChime();
                if (!this.isTabFocused) {
                    this.unreadMessages++;
                    document.title = `💬 (${this.unreadMessages}) New Message!`;
                }
            }
        } else if (event === 'typing') {
            this.typingBadge.style.display = data.typing ? 'inline-block' : 'none';
        } else if (event === 'room_closed') {
            this.handleStrangerDisconnect();
        }
    }

    /**
     * Switch user to the next stranger chat room.
     */
    async triggerNextChat() {
        this.textareaEl.disabled = true;
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
                this.stop();
                this.app.onNextChatTriggered();
            }
        } catch (err) {
            console.error('[Chat] Next navigation error:', err);
            this.textareaEl.disabled = false;
            this.sendBtn.disabled = false;
        }
    }

    /**
     * Submit reports against abusive behavior.
     */
    async submitReport() {
        const reason = this.reportReasonSelect.value;
        if (!this.roomId) return;

        this.reportModal.classList.remove('active');
        this.textareaEl.disabled = true;
        this.sendBtn.disabled = true;

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
                this.stop();
                this.app.showToast('Stranger reported. Chat closed.', 'danger');
                this.app.onReportSubmitted();
            }
        } catch (err) {
            console.error('[Chat] Report submission error:', err);
            this.textareaEl.disabled = false;
            this.sendBtn.disabled = false;
        }
    }

    /**
     * Handle room closed notifications.
     */
    handleStrangerDisconnect() {
        this.stop();
        this.textareaEl.disabled = true;
        this.sendBtn.disabled = true;
        this.typingBadge.style.display = 'none';
        
        this.strangerStatusEl.style.backgroundColor = 'var(--accent-red)';
        this.strangerStatusEl.style.boxShadow = 'none';
        this.headerBar.classList.add('shake', 'pulse-red-border');

        this.renderSystemMessage('Stranger has disconnected from the chat.', true);
        this.app.showToast('Stranger disconnected.', 'danger');
    }

    /**
     * Create and inject message bubbles in DOM.
     */
    renderMessageBubble(content, isMe, timestamp) {
        const wrapper = document.createElement('div');
        wrapper.className = `message-wrapper ${isMe ? 'me' : 'stranger'}`;

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = content;

        const meta = document.createElement('div');
        meta.className = 'message-meta';
        
        // Extract time only
        const timeStr = timestamp ? timestamp.substring(11, 16) : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        meta.textContent = timeStr;

        wrapper.appendChild(bubble);
        wrapper.appendChild(meta);

        // Slide animation trigger
        bubble.style.transform = 'scale(0.8)';
        this.messagesEl.appendChild(wrapper);

        // Force browser layout repaint for smooth transition
        bubble.offsetHeight;
        bubble.style.transform = 'scale(1)';

        this.scrollToBottom();
    }

    /**
     * Render system notice messages.
     */
    renderSystemMessage(text, isError = false) {
        const msg = document.createElement('div');
        msg.className = `message-system ${isError ? 'error' : ''}`;
        msg.textContent = text;
        this.messagesEl.appendChild(msg);
        this.scrollToBottom();
    }

    /**
     * Force scroll to bottom.
     */
    scrollToBottom() {
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    }

    /**
     * Play chime using browser Web Audio API.
     */
    playNotificationChime() {
        if (!this.app.soundEnabled) return;
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.type = 'sine';
            
            // Double-note chime (D5 -> A5)
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
