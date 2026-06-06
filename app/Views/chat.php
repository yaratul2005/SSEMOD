<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Stranger Chat — SSE Realtime Engine</title>
    
    <!-- Design stylesheets -->
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    <link rel="stylesheet" href="assets/css/animations.css">
</head>
<body>

    <!-- Particle Background -->
    <div class="particle-background" id="particle-bg"></div>

    <div class="app-container">
        <!-- Main Application Header -->
        <header>
            <div class="logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
                <span>StrangerChat</span>
            </div>
            
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <div class="presence-badge">
                    <span class="presence-dot"></span>
                    <span id="online-counter">0 online</span>
                </div>
                
                <button class="sound-toggle" id="sound-toggle-btn" title="Toggle Sound">
                    <!-- Volume speaker icon -->
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" id="volume-icon">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Dynamic Main Views -->
        <main>
            <!-- 1. LANDING SCREEN -->
            <section class="screen active" id="screen-landing">
                <div style="max-width: 500px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; align-items: center;">
                    <h1 style="font-size: 2.5rem; font-weight: 800; line-height: 1.1;">Talk to strangers.<br><span style="color: var(--accent-blue);">Instantly & anonymously.</span></h1>
                    <p style="color: var(--text-secondary); font-size: 1.05rem;">Connect with interesting minds worldwide. No registrations, no profiling. Just direct conversations.</p>
                    
                    <div class="input-group">
                        <label class="input-label" for="interest-tags">Interests (optional, comma-separated)</label>
                        <input class="text-input" type="text" id="interest-tags" placeholder="e.g. gaming, coding, music, movies" maxlength="100">
                    </div>

                    <button class="btn btn-primary btn-pulse" id="btn-start-chat" style="padding: 1rem 2.5rem; font-size: 1.1rem; border-radius: 50px;">
                        <span>Start Chatting</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </div>
            </section>

            <!-- 2. WAITING ROOM SCREEN -->
            <section class="screen" id="screen-queue">
                <div class="spinner-container">
                    <div class="spinner"></div>
                    <h2 style="font-size: 1.8rem; margin-top: 1rem;">Finding a stranger...</h2>
                    <p style="color: var(--text-secondary);" id="estimated-wait-text">Estimated wait time: calculating...</p>
                </div>
                
                <div style="background-color: var(--bg-tertiary); padding: 1rem 1.5rem; border-radius: 8px; border: 1px solid var(--border); max-width: 400px; width: 100%;">
                    <p style="font-size: 0.9rem; color: var(--accent-cyan); font-style: italic;" id="queue-wait-tip">"Matching you based on interests if possible."</p>
                </div>

                <button class="btn btn-secondary" id="btn-cancel-queue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    <span>Cancel</span>
                </button>
            </section>

            <!-- 3. ACTIVE CHAT SCREEN -->
            <section class="screen" id="screen-chat">
                <!-- Chat Control Header -->
                <div class="chat-header" id="chat-header-bar">
                    <div class="chat-stranger-info">
                        <span class="status-indicator" id="stranger-status"></span>
                        <strong style="font-size: 0.95rem;">Stranger</strong>
                        <span id="stranger-typing-badge" style="display: none; font-size: 0.75rem; color: var(--accent-yellow); font-weight: 500; margin-left: 0.5rem; border: 1px solid rgba(255, 234, 0, 0.2); padding: 0.15rem 0.4rem; border-radius: 4px; background: rgba(255, 234, 0, 0.05);">typing...</span>
                    </div>
                    
                    <div class="chat-controls">
                        <button class="btn btn-danger btn-small" id="btn-report" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Report</button>
                        <button class="btn btn-secondary btn-small" id="btn-next" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-color: var(--accent-blue); color: var(--accent-blue);">
                            <span>Next Chat</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </div>
                </div>

                <!-- Messages Window -->
                <div class="chat-messages" id="chat-message-list">
                    <!-- Dynamic messages get injected here -->
                </div>

                <!-- Input Send Bar -->
                <div class="chat-footer">
                    <div class="chat-input-wrapper">
                        <textarea class="chat-textarea" id="chat-message-input" placeholder="Type a message..." maxlength="500"></textarea>
                        <span class="char-counter" id="char-counter-text">500</span>
                    </div>
                    <button class="btn-send" id="btn-send-msg">
                        <svg viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </section>
        </main>
    </div>

    <!-- 4. REPORT MODAL -->
    <div class="modal-overlay" id="report-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Report Stranger</h3>
            </div>
            <div class="modal-body">
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.8rem;">Select a reason for reporting. Submitting closes the chat immediately.</p>
                <select id="report-reason-select">
                    <option value="Spam or advertising">Spam or advertising</option>
                    <option value="Harassment or abuse">Harassment or abuse</option>
                    <option value="Inappropriate/NSFW content">Inappropriate/NSFW content</option>
                    <option value="Hate speech">Hate speech</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="btn-report-cancel" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Cancel</button>
                <button class="btn btn-danger" id="btn-report-submit" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Submit Report</button>
            </div>
        </div>
    </div>

    <!-- Initialize Client Context -->
    <script>
        window.USER_ID = "<?php echo $_SESSION['user_id'] ?? ''; ?>";
    </script>

    <!-- Import Engine Scripts -->
    <script src="assets/js/sse-engine.js"></script>
    <script src="assets/js/presence.js"></script>
    <script src="assets/js/queue.js"></script>
    <script src="assets/js/chat.js"></script>
    <script src="assets/js/app.js"></script>

</body>
</html>
