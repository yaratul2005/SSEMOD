/**
 * Client Arena Manager - handles online user list rendering, filters, search, and direct chat requests.
 */
class ArenaManager {
    constructor(app) {
        this.app = app;
        this.users = new Map(); // Store of active user cards: id -> profile
        this.sseManager = null;
        
        // Filter and Search States
        this.genderFilter = 'all'; // 'all' | 'F' | 'M'
        this.searchQuery = '';
        this.searchDebounce = null;
        
        // DOM Elements
        this.userListEl = document.getElementById('arena-user-list');
        this.searchInputEl = document.getElementById('user-search-input');
        this.filterChips = document.querySelectorAll('.filter-chip');
        this.onlineCounterEl = document.getElementById('arena-online-counter');
        this.panelsContainer = document.getElementById('arena-panels-container');
        
        // Dynamic Notification Bar
        this.notifyContainer = document.getElementById('direct-chat-notification');
        
        this.setupEvents();
    }

    /**
     * Start presence monitoring.
     */
    start() {
        this.sseManager = new SSEManager(
            'stream/arena-presence',
            (event, data) => this.handleEvent(event, data),
            (err) => console.error('[Arena] Stream error:', err),
            'ArenaPresenceStream'
        );
        this.sseManager.connect();
    }

    /**
     * Stop presence monitoring.
     */
    stop() {
        if (this.sseManager) {
            this.sseManager.disconnect();
            this.sseManager = null;
        }
    }

    /**
     * Wire search inputs and gender filter chips.
     */
    setupEvents() {
        // Gender filter chips click handlers
        this.filterChips.forEach(chip => {
            chip.addEventListener('click', () => {
                this.filterChips.forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                
                this.genderFilter = chip.getAttribute('data-filter') || 'all';
                this.applyFilters();
            });
        });

        // Search input key events
        this.searchInputEl.addEventListener('input', () => {
            if (this.searchDebounce) {
                clearTimeout(this.searchDebounce);
            }
            this.searchDebounce = setTimeout(() => {
                this.searchQuery = this.searchInputEl.value.trim().toLowerCase();
                this.applyFilters();
            }, 300);
        });

        this.searchInputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.searchInputEl.value = '';
                this.searchQuery = '';
                this.applyFilters();
                this.searchInputEl.blur();
            }
        });
    }

    /**
     * Process incoming presence and request events.
     */
    handleEvent(event, data) {
        if (event === 'presence') {
            const joined = data.joined || [];
            const left = data.left || [];
            const total = data.total || 0;

            // Update header online badge
            this.onlineCounterEl.textContent = `${total} online`;

            // Process departures first
            left.forEach(userId => {
                this.removeUserRow(userId);
            });

            // Process new joins / status updates
            joined.forEach(user => {
                this.updateUserRow(user);
            });

            this.applyFilters();
        } else if (event === 'chat_request') {
            this.showChatRequestNotification(data);
        }
    }

    /**
     * Add or update user card in list.
     */
    updateUserRow(user) {
        this.users.set(user.id, user);
        
        let row = document.getElementById(`user-row-${user.id}`);
        const isNew = !row;

        if (isNew) {
            row = document.createElement('div');
            row.id = `user-row-${user.id}`;
            row.className = 'user-row';
            row.addEventListener('click', () => this.selectUser(user.id));
        }

        const isFemale = user.gender === 'F';
        const genderSymbol = isFemale ? '♀' : '♂';
        const avatarClass = isFemale ? 'avatar-pink' : 'avatar-blue';
        const initial = user.username ? user.username.substring(0, 2) : '?';
        const statusClass = user.online ? 'status-online' : 'status-offline';

        // Tag badges markup
        let tagsHtml = '';
        const tags = user.tags || [];
        tags.forEach(tag => {
            tagsHtml += `<span class="interest-tag-pill">${tag}</span>`;
        });

        // Set inner row HTML structure
        row.innerHTML = `
            <div class="avatar-circle-wrapper">
                <div class="avatar-circle ${avatarClass}">${initial}</div>
                <span class="status-indicator ${statusClass}"></span>
            </div>
            <div class="user-info">
                <div class="user-row-title">
                    <span class="user-name">${user.username}</span>
                </div>
                <div class="user-meta-sub">
                    <span class="gender-icon gender-${user.gender}">${genderSymbol}</span>
                    <span class="country-flag-icon" style="margin-left: 0.25rem; font-size: 0.95rem; vertical-align: middle;">${user.country_flag || '🏳️'}</span>
                    <span style="margin-left: 0.25rem;">${user.age} Y</span>
                    ${tagsHtml}
                </div>
            </div>
        `;

        if (isNew) {
            // Apply slide animation
            row.style.animation = 'fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            this.userListEl.appendChild(row);
        }

        // Apply gray out class if offline
        if (!user.online) {
            row.style.opacity = '0.55';
        } else {
            row.style.opacity = '1';
        }
    }

    /**
     * Remove user card from list.
     */
    removeUserRow(userId) {
        this.users.delete(userId);
        const row = document.getElementById(`user-row-${userId}`);
        if (row) {
            row.style.transform = 'translateX(-20px)';
            row.style.opacity = '0';
            setTimeout(() => {
                if (row.parentNode) {
                    row.parentNode.removeChild(row);
                }
                this.checkEmptyList();
            }, 300);
        }
    }

    /**
     * Filter active user cards list.
     */
    applyFilters() {
        let visibleCount = 0;
        this.users.forEach((user, userId) => {
            const row = document.getElementById(`user-row-${userId}`);
            if (!row) return;

            // 1. Filter by gender
            const matchGender = (this.genderFilter === 'all' || user.gender === this.genderFilter);

            // 2. Filter by search query
            const username = (user.username || '').toLowerCase();
            const matchSearch = (this.searchQuery === '' || username.includes(this.searchQuery));

            if (matchGender && matchSearch) {
                row.style.display = 'flex';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        this.checkEmptyList(visibleCount);
    }

    /**
     * Toggle empty list banner.
     */
    checkEmptyList(count = null) {
        if (count === null) {
            count = 0;
            this.users.forEach((user, userId) => {
                const row = document.getElementById(`user-row-${userId}`);
                if (row && row.style.display !== 'none') count++;
            });
        }

        let emptyEl = this.userListEl.querySelector('.user-list-empty');
        if (count === 0) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'user-list-empty';
                emptyEl.textContent = 'No online users match your search';
                this.userListEl.appendChild(emptyEl);
            }
        } else if (emptyEl) {
            emptyEl.parentNode.removeChild(emptyEl);
        }
    }

    /**
     * Active user selection action.
     */
    selectUser(userId) {
        const user = this.users.get(userId);
        if (!user) return;

        // Highlight selected user row in DOM
        document.querySelectorAll('.user-row').forEach(row => {
            row.classList.remove('active');
        });
        const activeRow = document.getElementById(`user-row-${userId}`);
        if (activeRow) activeRow.classList.add('active');

        // Slide viewport on mobile
        this.panelsContainer.classList.add('chat-open');

        // Start direct chat session
        this.app.startDirectChat(userId, user);
    }

    /**
     * Switch view back on mobile back click.
     */
    closeChatMobile() {
        this.panelsContainer.classList.remove('chat-open');
        document.querySelectorAll('.user-row').forEach(row => {
            row.classList.remove('active');
        });
    }

    /**
     * Toast bar alert trigger for direct requests.
     */
    showChatRequestNotification(data) {
        this.notifyContainer.innerHTML = `
            <div class="incoming-chat-notification-toast">
                <span>Incoming chat request from <strong>${data.from_username}</strong></span>
                <button class="btn btn-primary btn-pulse" id="btn-accept-direct-toast" style="padding: 0.35rem 0.8rem; font-size: 0.75rem; border-radius: 4px;">Accept</button>
            </div>
        `;
        this.notifyContainer.classList.add('active');

        const btn = document.getElementById('btn-accept-direct-toast');
        if (btn) {
            btn.addEventListener('click', () => {
                this.notifyContainer.classList.remove('active');
                this.selectUser(data.from);
            });
        }

        setTimeout(() => {
            this.notifyContainer.classList.remove('active');
        }, 8000);
    }
}
