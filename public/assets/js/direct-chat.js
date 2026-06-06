/**
 * Client Direct Chat Manager - handles right-panel direct messaging context, headers, and inputs.
 */
class DirectChatManager {
    constructor(app) {
        this.app = app;
        this.roomId = null;
        this.strangerId = null;
        this.strangerProfile = null;
        this.sseManager = null;

        // DOM elements
        this.viewportEl = document.getElementById('chat-viewport');
        this.emptyEl = document.getElementById('chat-empty-state');
        this.messagesEl = document.getElementById('direct-message-list');
        this.textareaEl = document.getElementById('direct-message-input');
        this.sendBtn = document.getElementById('btn-send-direct');
        this.charCounterEl = document.getElementById('direct-char-counter-text');
        this.typingBadge = document.getElementById('direct-typing-badge');
        
        // Header info elements
        this.headerUsernameEl = document.getElementById('chat-header-username');
        this.headerSubmetaEl = document.getElementById('chat-header-submeta-text');
        this.headerAvatarEl = document.getElementById('chat-header-avatar');
        this.headerStatusEl = document.getElementById('chat-header-status');
        
        // Controls
        this.backBtn = document.getElementById('btn-chat-back-mobile');
        this.reportBtn = document.getElementById('btn-report-direct');
        
        // Attachment elements
        this.btnAttach = document.getElementById('btn-attach');
        this.attachmentInput = document.getElementById('direct-chat-attachment');
        this.attachmentPreview = document.getElementById('direct-attachment-preview');
        this.attachmentThumb = document.getElementById('direct-attachment-thumb');
        this.attachmentIcon = document.getElementById('direct-attachment-icon');
        this.attachmentName = document.getElementById('direct-attachment-name');
        this.attachmentSize = document.getElementById('direct-attachment-size');
        this.btnCancelAttachment = document.getElementById('btn-cancel-attachment');
        this.selectedFile = null;
        
        this.typingTimeout = null;
        this.setupEvents();
    }

    /**
     * Start chat with target user, fetching/creating a direct room.
     */
    async start(strangerId, strangerProfile) {
        this.disconnect();
        
        this.strangerId = strangerId;
        this.strangerProfile = strangerProfile;
        
        // Render header profile metadata
        this.headerUsernameEl.textContent = strangerProfile.username;
        
        const genderSymbol = strangerProfile.gender === 'F' ? '♀' : '♂';
        const flagSymbol = strangerProfile.country_flag || '🏳️';
        let tagsText = (strangerProfile.tags || []).join(', ');
        if (tagsText !== '') tagsText = ` · ${tagsText}`;
        this.headerSubmetaEl.innerHTML = `${genderSymbol} <span style="font-size: 1.1rem; vertical-align: middle;">${flagSymbol}</span> ${strangerProfile.age} Y${tagsText}`;
        
        this.headerAvatarEl.textContent = strangerProfile.username.substring(0, 2);
        this.headerAvatarEl.className = `avatar-circle ${strangerProfile.gender === 'F' ? 'avatar-pink' : 'avatar-blue'}`;
        
        const statusClass = strangerProfile.online ? 'status-online' : 'status-offline';
        this.headerStatusEl.className = `status-indicator ${statusClass}`;
        
        this.messagesEl.innerHTML = '';
        this.textareaEl.value = '';
        this.textareaEl.disabled = false;
        this.sendBtn.disabled = false;
        this.charCounterEl.style.display = 'none';
        this.typingBadge.style.display = 'none';
        this.clearAttachment();
        
        // Toggle view container
        this.emptyEl.style.display = 'none';
        this.viewportEl.style.display = 'flex';
        this.textareaEl.focus();

        this.renderSystemMessage(`Chat started with ${strangerProfile.username} · Today`);

        try {
            // POST /api/chat/start/{user_id}
            const response = await fetch(`api/chat/start/${encodeURIComponent(strangerId)}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': this.app.getCsrfToken()
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.roomId = data.room_id;
                this.connectStream();
            } else {
                const data = await response.json();
                this.renderSystemMessage(data.error || 'Failed to open direct room.', true);
            }
        } catch (err) {
            console.error('[DirectChat] Start error:', err);
            this.renderSystemMessage('Network error occurred. Unable to initiate room.', true);
        }
    }

    /**
     * Shut down direct chat connection.
     */
    disconnect() {
        if (this.sseManager) {
            this.sseManager.disconnect();
            this.sseManager = null;
        }
        this.roomId = null;
        this.strangerId = null;
    }

    /**
     * Subscribe to direct message SSE channel.
     */
    connectStream() {
        if (!this.roomId) return;
        
        // Reuse stream/chat controller route as direct stream
        const url = `stream/direct/${encodeURIComponent(this.roomId)}`;
        
        this.sseManager = new SSEManager(
            url,
            (event, data) => this.handleEvent(event, data),
            (err) => console.error('[DirectChat] SSE error:', err),
            'DirectChatStream'
        );
        this.sseManager.connect();
    }

    /**
     * Wire DOM controls.
     */
    setupEvents() {
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
            if (length >= 400) {
                this.charCounterEl.style.display = 'block';
            } else {
                this.charCounterEl.style.display = 'none';
            }
        });

        this.sendBtn.addEventListener('click', () => this.submitMessage());

        // Attachments trigger
        if (this.btnAttach) {
            this.btnAttach.addEventListener('click', () => this.attachmentInput.click());
        }

        if (this.attachmentInput) {
            this.attachmentInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;

                const maxSize = 25 * 1024 * 1024; // 25MB
                if (file.size > maxSize) {
                    alert('Attachment exceeds the 25MB limit.');
                    this.clearAttachment();
                    return;
                }

                this.selectedFile = file;
                this.attachmentName.textContent = file.name;
                
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                this.attachmentSize.textContent = `${sizeMB} MB`;

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.attachmentThumb.src = e.target.result;
                        this.attachmentThumb.style.display = 'block';
                        this.attachmentIcon.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                } else {
                    this.attachmentThumb.style.display = 'none';
                    this.attachmentIcon.style.display = 'block';
                    if (file.type.startsWith('video/')) {
                        this.attachmentIcon.textContent = '🎥';
                    } else if (file.type.startsWith('audio/')) {
                        this.attachmentIcon.textContent = '🎵';
                    } else {
                        this.attachmentIcon.textContent = '📁';
                    }
                }

                this.attachmentPreview.style.display = 'flex';
            });
        }

        if (this.btnCancelAttachment) {
            this.btnCancelAttachment.addEventListener('click', () => {
                this.clearAttachment();
            });
        }

        this.backBtn.addEventListener('click', () => {
            this.disconnect();
            this.viewportEl.style.display = 'none';
            this.emptyEl.style.display = 'flex';
            this.app.arenaManager.closeChatMobile();
        });

        this.reportBtn.addEventListener('click', () => {
            // Forward report action directly to main report modal context
            const reportModal = document.getElementById('report-modal');
            const submitBtn = document.getElementById('btn-report-submit');
            
            reportModal.classList.add('active');
            
            // Re-bind submit button once to run reported logic for direct room
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
                        this.disconnect();
                        this.viewportEl.style.display = 'none';
                        this.emptyEl.style.display = 'flex';
                        this.app.showToast('User reported and chat room closed.', 'danger');
                    }
                } catch (err) {
                    console.error('[DirectChat] Report error:', err);
                }
            };
            
            submitBtn.addEventListener('click', triggerSubmit);
        });
    }

    /**
     * Clear active attachment status.
     */
    clearAttachment() {
        this.selectedFile = null;
        if (this.attachmentInput) {
            this.attachmentInput.value = '';
        }
        if (this.attachmentPreview) {
            this.attachmentPreview.style.display = 'none';
        }
        if (this.attachmentThumb) {
            this.attachmentThumb.src = '';
            this.attachmentThumb.style.display = 'none';
        }
        if (this.attachmentIcon) {
            this.attachmentIcon.style.display = 'none';
        }
    }

    /**
     * Post message.
     */
    async submitMessage() {
        const text = this.textareaEl.value.trim();
        if (text === '' && !this.selectedFile) return;
        if (!this.roomId) return;

        const fileToSend = this.selectedFile;
        const textToSend = text;

        this.textareaEl.value = '';
        this.charCounterEl.style.display = 'none';
        this.clearAttachment();
        this.textareaEl.focus();

        try {
            const formData = new FormData();
            formData.append('room_id', this.roomId);
            formData.append('content', textToSend);
            if (fileToSend) {
                formData.append('attachment', fileToSend);
            }

            const response = await fetch('api/message/send', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.app.getCsrfToken()
                },
                body: formData
            });

            if (!response.ok) {
                const data = await response.json();
                this.renderSystemMessage(data.error || 'Failed to deliver message.', true);
            }
        } catch (err) {
            console.error('[DirectChat] Send error:', err);
            this.renderSystemMessage('Network connection error.', true);
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
     * Route incoming event.
     */
    handleEvent(event, data) {
        if (event === 'message') {
            const isMe = data.sender_id === this.app.getUserId();
            this.renderMessageBubble(data.content, isMe, data.sent_at, data.attachment_path, data.attachment_type);
            
            if (!isMe) {
                this.playNotificationChime();
                if (!document.hasFocus()) {
                    document.title = '💬 New message in Arena!';
                }
            }
        } else if (event === 'typing') {
            this.typingBadge.style.display = data.typing ? 'inline-block' : 'none';
        } else if (event === 'room_closed') {
            this.renderSystemMessage('Stranger has disconnected from the chat.', true);
            this.textareaEl.disabled = true;
            this.sendBtn.disabled = true;
        }
    }

    /**
     * Inject message bubble.
     */
    renderMessageBubble(content, isMe, timestamp, attachmentPath = null, attachmentType = null) {
        const wrapper = document.createElement('div');
        wrapper.className = `message-wrapper ${isMe ? 'me' : 'stranger'}`;
        
        // Add small avatar circle beside stranger message bubbles
        if (!isMe && this.strangerProfile) {
            const avatar = document.createElement('div');
            avatar.className = `msg-avatar ${this.strangerProfile.gender === 'F' ? 'avatar-pink' : 'avatar-blue'}`;
            avatar.textContent = this.strangerProfile.username.substring(0, 2);
            wrapper.appendChild(avatar);
        }

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        
        // Render attachment if exists
        if (attachmentPath) {
            // Serve secure file url prepend base url helper dynamically if not present
            const fileUrl = `${window.BASE_URL || ''}/attachment/${encodeURIComponent(attachmentPath)}`;
            
            const attachmentWrapper = document.createElement('div');
            attachmentWrapper.className = 'message-attachment-wrapper';
            attachmentWrapper.style.marginBottom = content !== '' ? '0.5rem' : '0';
            attachmentWrapper.style.maxWidth = '100%';

            if (attachmentType === 'image') {
                const img = document.createElement('img');
                img.src = fileUrl;
                img.alt = 'Image attachment';
                img.style.maxWidth = '220px';
                img.style.maxHeight = '220px';
                img.style.borderRadius = '8px';
                img.style.cursor = 'pointer';
                img.style.display = 'block';
                img.addEventListener('click', () => window.open(fileUrl, '_blank'));
                attachmentWrapper.appendChild(img);
            } else if (attachmentType === 'video') {
                const video = document.createElement('video');
                video.src = fileUrl;
                video.controls = true;
                video.style.maxWidth = '220px';
                video.style.maxHeight = '220px';
                video.style.borderRadius = '8px';
                video.style.display = 'block';
                attachmentWrapper.appendChild(video);
            } else if (attachmentType === 'audio') {
                const audio = document.createElement('audio');
                audio.src = fileUrl;
                audio.controls = true;
                audio.style.maxWidth = '220px';
                audio.style.display = 'block';
                attachmentWrapper.appendChild(audio);
            } else {
                const downloadLink = document.createElement('a');
                downloadLink.href = fileUrl;
                // Try to strip uuid prefix if possible
                const originalFilename = attachmentPath.length > 32 ? attachmentPath.substring(32) : attachmentPath;
                downloadLink.download = originalFilename;
                downloadLink.style.display = 'flex';
                downloadLink.style.alignItems = 'center';
                downloadLink.style.gap = '0.5rem';
                downloadLink.style.color = isMe ? '#ffffff' : 'var(--accent-blue)';
                downloadLink.style.textDecoration = 'underline';
                downloadLink.style.fontSize = '0.85rem';
                downloadLink.style.fontWeight = '500';
                
                downloadLink.innerHTML = `
                    <span style="font-size: 1.25rem;">📁</span>
                    <span>Download File</span>
                `;
                attachmentWrapper.appendChild(downloadLink);
            }
            bubble.appendChild(attachmentWrapper);
        }

        if (content && content.trim() !== '') {
            const textSpan = document.createElement('span');
            textSpan.textContent = content;
            bubble.appendChild(textSpan);
        }

        const meta = document.createElement('div');
        meta.className = 'message-meta';
        const timeStr = timestamp ? timestamp.substring(11, 16) : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        meta.textContent = timeStr;

        wrapper.appendChild(bubble);
        wrapper.appendChild(meta);

        // Transition slide-in
        bubble.style.transform = 'scale(0.85)';
        this.messagesEl.appendChild(wrapper);

        bubble.offsetHeight; // trigger paint
        bubble.style.transform = 'scale(1)';

        this.scrollToBottom();
    }

    /**
     * Render notice tags.
     */
    renderSystemMessage(text, isError = false) {
        const msg = document.createElement('div');
        msg.className = `message-system ${isError ? 'error' : ''}`;
        msg.textContent = text;
        this.messagesEl.appendChild(msg);
        this.scrollToBottom();
    }

    scrollToBottom() {
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    }

    /**
     * Play volume beep chimes.
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
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); 
            osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.08); 
            
            gain.gain.setValueAtTime(0.04, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);

            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {
            console.warn('Audio chime failed:', e);
        }
    }
}
