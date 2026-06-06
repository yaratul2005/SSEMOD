<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>ChatArena — Live Stranger Browser</title>
    <?php
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseUrl = rtrim(dirname($scriptName), '/\\');
    ?>
    <script>
        window.BASE_URL = '<?php echo $baseUrl; ?>';
        (function() {
            const originalFetch = window.fetch;
            window.fetch = function(resource, init) {
                if (typeof resource === 'string' && !resource.startsWith('http://') && !resource.startsWith('https://') && !resource.startsWith('//') && !resource.startsWith(window.BASE_URL)) {
                    const cleanResource = resource.startsWith('/') ? resource : '/' + resource;
                    resource = window.BASE_URL + cleanResource;
                }
                return originalFetch(resource, init);
            };
        })();
    </script>
    
    <!-- CSS Design Sheets -->
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/arena.css">
    <link rel="stylesheet" href="assets/css/modal.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>

    <!-- Particle Background -->
    <div class="particle-background" id="particle-bg"></div>

    <div class="app-container">
        <!-- HEADER REDESIGN -->
        <header>
            <div class="logo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Chat<span style="color: var(--accent-green);">Arena</span></span>
            </div>
            
            <nav class="nav-links">
                <button class="nav-btn active" id="nav-btn-browse">Browse</button>
                <button class="nav-btn" id="nav-btn-friends">Friends</button>
                
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'registered'): ?>
                    <a href="inbox" class="nav-link-anchor"><button class="nav-btn" id="nav-btn-inbox">Inbox</button></a>
                    <a href="dashboard" class="nav-link-anchor"><button class="nav-btn" id="nav-btn-dashboard">Dashboard</button></a>
                <?php else: ?>
                    <button class="nav-btn guest-lock-feature" id="nav-btn-inbox-locked">Inbox</button>
                <?php endif; ?>
                
                <!-- RandomChat Highlighted Button -->
                <button class="random-chat-badge" id="btn-random-chat-trigger" title="Launch Omegle-style Matchmaker">
                    <span class="pulse-dot"></span>
                    <span>RandomChat</span>
                </button>
            </nav>
            
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <div class="presence-badge">
                    <span class="presence-dot"></span>
                    <span id="arena-online-counter">0 online</span>
                </div>

                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'guest'): ?>
                    <div class="guest-badge-container" id="header-guest-badge">
                        <span class="guest-badge">Guest</span>
                        <div class="guest-tooltip">
                            You're browsing as a guest.<br>
                            Register for inbox, profile photo & more.<br>
                            <button class="btn btn-primary btn-small" id="btn-upgrade-from-badge" style="margin-top: 0.4rem; font-size: 0.75rem; padding: 0.2rem 0.5rem; width: 100%;">Get Started</button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button class="sound-toggle" id="sound-toggle-btn" title="Toggle Sound">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" id="volume-icon">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                    </svg>
                </button>
                
                <button class="settings-btn" id="btn-settings-trigger" title="Edit Profile">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </button>

                <?php if (isset($_SESSION['user_type'])): ?>
                    <div class="user-avatar-header-wrapper" style="position: relative; margin-left: 0.25rem;">
                        <?php if ($_SESSION['user_type'] === 'registered'): ?>
                            <a href="dashboard">
                        <?php endif; ?>
                        <img class="header-user-avatar <?php echo $_SESSION['user_type'] === 'guest' ? 'guest-avatar-locked' : ''; ?>" 
                             src="avatar/<?php echo htmlspecialchars($userId); ?>" 
                             alt="Avatar" 
                             style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); cursor: pointer;"
                             id="header-user-avatar-btn">
                        <?php if ($_SESSION['user_type'] === 'guest'): ?>
                            <span class="avatar-lock-badge" id="avatar-lock-icon-btn">🔒</span>
                        <?php endif; ?>
                        <?php if ($_SESSION['user_type'] === 'registered'): ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- TWO-PANEL ARENA LAYOUT -->
        <div class="arena-layout" id="arena-panels-container">
            
            <!-- LEFT PANEL: USER BROWSER -->
            <aside class="left-panel" id="panel-left">
                <!-- Filter bar chips -->
                <div class="filter-bar">
                    <button class="filter-chip active" data-filter="all">All</button>
                    <button class="filter-chip" data-filter="F">♀ Female</button>
                    <button class="filter-chip" data-filter="M">♂ Male</button>
                </div>
                
                <!-- Search bar -->
                <div class="search-container">
                    <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input class="search-input" type="text" id="user-search-input" placeholder="Search username..." autocomplete="off">
                </div>
                
                <!-- User rows list -->
                <div class="user-list" id="arena-user-list">
                    <!-- Dynamic user cards injected here -->
                    <div class="user-list-empty">No users online</div>
                </div>
            </aside>

            <!-- RIGHT PANEL: ACTIVE CHAT -->
            <section class="right-panel" id="panel-right">
                
                <!-- 1. EMPTY STATE (Default) -->
                <div class="chat-empty-state" id="chat-empty-state">
                    <div class="empty-icon-circle">
                        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h2>Select someone to start chatting</h2>
                    <p>Or hit RandomChat to meet a stranger instantly</p>
                    <button class="btn btn-primary btn-pulse" id="btn-empty-state-random" style="border-radius: 50px;">
                        <span>RandomChat</span>
                    </button>
                </div>

                <!-- 2. ACTIVE CHAT VIEWPORT -->
                <div class="chat-viewport" id="chat-viewport" style="display: none;">
                    <!-- Chat Header -->
                    <div class="chat-header" id="arena-chat-header">
                        <div class="chat-stranger-info">
                            <button class="back-btn" id="btn-chat-back-mobile" title="Back to User List">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                            </button>
                            <div class="avatar-circle-wrapper">
                                <div class="avatar-circle" id="chat-header-avatar">?</div>
                                <span class="status-indicator" id="chat-header-status"></span>
                            </div>
                            <div class="chat-header-meta">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <strong style="font-size: 0.95rem;" id="chat-header-username">Stranger</strong>
                                    <span id="direct-typing-badge" style="display: none; font-size: 0.7rem; color: var(--accent-yellow); font-weight: 500;">typing...</span>
                                </div>
                                <div class="chat-header-submeta" id="chat-header-submeta-text">♀ 24 Y</div>
                            </div>
                        </div>
                        
                        <div class="chat-controls">
                            <button class="chat-header-action-btn" id="btn-add-friend" title="Add to Friends">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                            </button>
                            <button class="chat-header-action-btn" id="btn-report-direct" title="Report Stranger" style="color: var(--accent-red);">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div class="chat-messages" id="direct-message-list">
                        <!-- Messages dynamic -->
                    </div>

                    <!-- Attachment Preview Container -->
                    <div class="attachment-preview-container" id="direct-attachment-preview" style="display: none; padding: 0.5rem 1rem; background: var(--bg-tertiary); border-top: 1px solid var(--border); display: flex; align-items: center; gap: 0.75rem; position: relative;">
                        <!-- Preview Thumbnail / File Icon -->
                        <div class="attachment-thumbnail-wrapper" style="position: relative; width: 44px; height: 44px; border-radius: 6px; overflow: hidden; border: 1px solid var(--border); background: var(--bg-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <img id="direct-attachment-thumb" src="" alt="preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <span id="direct-attachment-icon" style="font-size: 1.5rem; display: none;">📁</span>
                        </div>
                        <!-- File Name and Size Info -->
                        <div style="flex: 1; min-width: 0;">
                            <div id="direct-attachment-name" style="font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary);">filename.png</div>
                            <div id="direct-attachment-size" style="font-size: 0.75rem; color: var(--text-secondary);">1.2 MB</div>
                        </div>
                        <!-- Cancel Button -->
                        <button class="attachment-cancel-btn" id="btn-cancel-attachment" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 0.25rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: color 0.2s;" title="Remove attachment" onmouseover="this.style.color='var(--accent-red)'" onmouseout="this.style.color='var(--text-secondary)'">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>

                    <!-- Input Footer -->
                    <div class="chat-footer">
                        <div class="chat-input-wrapper">
                            <button class="attach-btn" id="btn-attach" title="Attach file (Max 25MB)">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            </button>
                            <input type="file" id="direct-chat-attachment" style="display: none;">
                            <textarea class="chat-textarea" id="direct-message-input" placeholder="type here....." maxlength="500" style="padding-left: 2.8rem;"></textarea>
                            <span class="char-counter" id="direct-char-counter-text" style="display: none;">500</span>
                        </div>
                        <button class="btn-send" id="btn-send-direct">
                            <svg viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- PROFILE SETTINGS MODAL -->
    <div class="modal-overlay" id="settings-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 style="color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Profile Settings
                </h3>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="input-group">
                    <label class="input-label" for="settings-username">Username</label>
                    <input class="text-input" type="text" id="settings-username" value="<?php echo htmlspecialchars($userProfile['username'] ?? ''); ?>" maxlength="20">
                </div>
                
                <div class="input-group">
                    <label class="input-label" for="settings-gender">Gender</label>
                    <select class="text-input" id="settings-gender" style="background-color: var(--bg-tertiary);">
                        <option value="M" <?php echo ($userProfile['gender'] ?? 'M') === 'M' ? 'selected' : ''; ?>>♂ Male</option>
                        <option value="F" <?php echo ($userProfile['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>♀ Female</option>
                    </select>
                </div>
                
                <div class="input-group">
                    <label class="input-label" for="settings-age">Age</label>
                    <input class="text-input" type="number" id="settings-age" value="<?php echo htmlspecialchars((string)($userProfile['age'] ?? 18)); ?>" min="18" max="99">
                </div>

                <div class="input-group">
                    <label class="input-label" for="settings-interests">Interest tags (max 3, max 12 chars each, comma-separated)</label>
                    <input class="text-input" type="text" id="settings-interests" value="<?php echo htmlspecialchars(implode(',', $userProfile['interests'] ?? [])); ?>" placeholder="e.g. music, coding">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="btn-settings-cancel" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Cancel</button>
                <button class="btn btn-primary" id="btn-settings-save" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- RANDOM CHAT MODAL OVERLAY -->
    <div class="modal-overlay" id="random-chat-modal" style="background-color: rgba(0, 0, 0, 0.9);">
        <div class="modal" id="random-modal-card" style="max-width: 480px; width: 100%; min-height: 400px; display: flex; flex-direction: column;">
            
            <!-- Modal Close Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.8rem; margin-bottom: 0.5rem;">
                <h4 style="font-size: 1.1rem; color: var(--accent-green);">RandomChat Arena</h4>
                <button class="sound-toggle" id="btn-random-modal-close" style="padding: 0.2rem; font-size: 1.5rem; color: var(--text-secondary);" title="Close and return to Arena">×</button>
            </div>

            <!-- PHASE 1: WAITING -->
            <div class="random-phase-view" id="random-phase-waiting" style="display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; gap: 1.5rem; text-align: center;">
                <div class="spinner"></div>
                <h2 style="font-size: 1.4rem;">Looking for a stranger...</h2>
                <p style="color: var(--text-secondary); font-size: 0.9rem;" id="random-wait-status-text">Estimated wait time: calculating...</p>
                <button class="btn btn-secondary btn-small" id="btn-random-cancel-queue">Cancel</button>
            </div>

            <!-- PHASE 2: MATCHED (Inner Chat window) -->
            <div class="random-phase-view" id="random-phase-matched" style="display: none; flex-direction: column; flex: 1; min-height: 350px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <span class="status-indicator"></span>
                        <strong id="random-stranger-title">Stranger</strong>
                        <span id="random-typing-badge" style="display: none; font-size: 0.75rem; color: var(--accent-yellow);">typing...</span>
                    </div>
                    <div style="display: flex; gap: 0.4rem;">
                        <button class="btn btn-danger" id="btn-random-report" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">Report</button>
                        <button class="btn btn-secondary" id="btn-random-next" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; color: var(--accent-blue); border-color: var(--accent-blue);">Next</button>
                    </div>
                </div>
                
                <!-- Inner Messages Thread -->
                <div class="chat-messages" id="random-message-list" style="background-color: #0c0c0c; flex: 1; max-height: 250px; min-height: 200px;">
                    <!-- Messages dynamic -->
                </div>
                
                <!-- Inner Input Footer -->
                <div style="display: flex; padding-top: 0.5rem; gap: 0.5rem; border-top: 1px solid var(--border); margin-top: 0.5rem;">
                    <input class="text-input" type="text" id="random-message-input" placeholder="Type a message..." maxlength="500" style="border-radius: 20px; padding: 0.5rem 1rem;">
                    <button class="btn-send" id="btn-random-send" style="width: 38px; height: 38px;">
                        <svg viewBox="0 0 24 24" style="width: 14px; height: 14px;"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
            </div>

            <!-- PHASE 3: DISCONNECTED -->
            <div class="random-phase-view" id="random-phase-disconnected" style="display: none; flex-direction: column; align-items: center; justify-content: center; flex: 1; gap: 1.5rem; text-align: center;">
                <div class="empty-icon-circle" style="background-color: rgba(255, 23, 68, 0.1); border-color: var(--accent-red); color: var(--accent-red);">!</div>
                <h3 style="font-size: 1.25rem;">Stranger disconnected.</h3>
                <div style="display: flex; gap: 0.75rem;">
                    <button class="btn btn-primary" id="btn-random-next-requeue">Find Another</button>
                    <button class="btn btn-secondary" id="btn-random-close-return">Close</button>
                </div>
            </div>

        </div>
    </div>

    <!-- DIRECT INCOMING CHAT NOTIFICATION BAR -->
    <div class="toast-container" id="direct-chat-notification" style="z-index: 3000;">
        <!-- Notification injected here -->
    </div>

    <?php if (empty($_SESSION['user_type'])): ?>
        <?php require __DIR__ . '/auth_modal.php'; ?>
    <?php endif; ?>

    <!-- Inject Client Context -->
    <script>
        window.USER_ID = "<?php echo $userId; ?>";
    </script>

    <!-- Import Engine Scripts -->
    <script src="assets/js/sse-engine.js"></script>
    <script src="assets/js/presence.js"></script>
    <script src="assets/js/arena.js"></script>
    <script src="assets/js/direct-chat.js"></script>
    <script src="assets/js/random-modal.js"></script>
    <script src="assets/js/guest-modal.js"></script>
    <script src="assets/js/register-flow.js"></script>
    <script src="assets/js/privilege-ui.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
