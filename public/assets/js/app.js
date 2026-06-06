/**
 * Core Application Manager - coordinates views, sound, settings submissions, and background particles
 */
class Application {
    constructor() {
        this.userId = window.USER_ID || '';
        this.csrfToken = '';
        this.soundEnabled = localStorage.getItem('sound_enabled') !== 'false';

        // Managers
        this.arenaManager = new ArenaManager(this);
        this.directChatManager = new DirectChatManager(this);
        this.randomModalManager = new RandomModalManager(this);

        // Header controls
        this.soundBtn = document.getElementById('sound-toggle-btn');
        this.volumeIcon = document.getElementById('volume-icon');
        this.settingsTrigger = document.getElementById('btn-settings-trigger');
        this.randomChatTrigger = document.getElementById('btn-random-chat-trigger');
        this.emptyStateRandomBtn = document.getElementById('btn-empty-state-random');

        // Navigation
        this.browseTab = document.getElementById('nav-btn-browse');
        this.friendsTab = document.getElementById('nav-btn-friends');

        // Modals
        this.settingsModal = document.getElementById('settings-modal');
        this.settingsCancelBtn = document.getElementById('btn-settings-cancel');
        this.settingsSaveBtn = document.getElementById('btn-settings-save');

        // Settings Fields
        this.settingsUsername = document.getElementById('settings-username');
        this.settingsGender = document.getElementById('settings-gender');
        this.settingsAge = document.getElementById('settings-age');
        this.settingsInterests = document.getElementById('settings-interests');

        this.toastContainer = this.createToastContainer();
        this.init();
    }
    
    /**
     * Start the application and subscribe to presence.
     */
    init() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        this.csrfToken = meta ? meta.getAttribute('content') : '';

        // Start Arena presences tracking
        this.arenaManager.start();
        this.updateSoundIcon();
        this.setupEvents();
        this.generateParticles();
    }

    /**
     * Set up DOM action triggers.
     */
    setupEvents() {
        // Sound controls
        this.soundBtn.addEventListener('click', () => {
            this.soundEnabled = !this.soundEnabled;
            localStorage.setItem('sound_enabled', String(this.soundEnabled));
            this.updateSoundIcon();
            this.showToast(this.soundEnabled ? 'Chime sound enabled' : 'Chime sound muted', 'info');
        });

        // Settings modal trigger
        this.settingsTrigger.addEventListener('click', () => {
            this.settingsModal.classList.add('active');
        });

        this.settingsCancelBtn.addEventListener('click', () => {
            this.settingsModal.classList.remove('active');
        });

        this.settingsSaveBtn.addEventListener('click', () => this.saveSettings());

        // RandomChat Trigger (header badge click)
        this.randomChatTrigger.addEventListener('click', () => {
            this.randomModalManager.open();
        });

        // RandomChat Trigger (empty state center button click)
        this.emptyStateRandomBtn.addEventListener('click', () => {
            this.randomModalManager.open();
        });

        // Browse & Friends switchers
        this.browseTab.addEventListener('click', () => {
            this.browseTab.classList.add('active');
            this.friendsTab.classList.remove('active');
            this.showToast('Browsing active users', 'info');
        });

        this.friendsTab.addEventListener('click', () => {
            this.friendsTab.classList.add('active');
            this.browseTab.classList.remove('active');
            this.showToast('Friends list feature is coming soon!', 'info');
        });
    }

    /**
     * Open direct chat window with target user.
     */
    startDirectChat(strangerId, strangerProfile) {
        this.directChatManager.start(strangerId, strangerProfile);
    }

    /**
     * Submit profile setting updates to the backend.
     */
    async saveSettings() {
        const username = this.settingsUsername.value.trim();
        const gender = this.settingsGender.value;
        const age = parseInt(this.settingsAge.value, 10);
        const interests = this.settingsInterests.value.trim();

        if (username.length < 3) {
            this.showToast('Username must be at least 3 characters.', 'warning');
            return;
        }

        if (isNaN(age) || age < 18 || age > 99) {
            this.showToast('Age must be between 18 and 99.', 'warning');
            return;
        }

        this.settingsSaveBtn.disabled = true;

        try {
            const response = await fetch('api/settings/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': this.csrfToken
                },
                body: `username=${encodeURIComponent(username)}&gender=${encodeURIComponent(gender)}&age=${age}&interests=${encodeURIComponent(interests)}`
            });

            if (response.ok) {
                const data = await response.json();
                this.settingsModal.classList.remove('active');
                this.showToast('Profile settings saved successfully!', 'success');
                
                if (data.user) {
                    this.arenaManager.users.set(this.userId, data.user);
                }
            } else {
                const data = await response.json();
                this.showToast(data.error || 'Failed to save settings.', 'danger');
            }
        } catch (err) {
            console.error('[App] Save settings error:', err);
            this.showToast('Connection error occurred.', 'danger');
        } finally {
            this.settingsSaveBtn.disabled = false;
        }
    }

    getUserId() { 
        return this.userId; 
    }
    
    getCsrfToken() { 
        return this.csrfToken; 
    }

    /**
     * Volume Icon renderer.
     */
    updateSoundIcon() {
        if (this.soundEnabled) {
            this.volumeIcon.innerHTML = `
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
            `;
        } else {
            this.volumeIcon.innerHTML = `
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                <line x1="23" y1="9" x2="17" y2="15"></line>
                <line x1="17" y1="9" x2="23" y2="15"></line>
            `;
        }
    }

    /**
     * Helper to create toast element.
     */
    createToastContainer() {
        const wrapper = document.createElement('div');
        wrapper.className = 'toast-container';
        document.body.appendChild(wrapper);
        return wrapper;
    }

    /**
     * Show premium status alerts.
     */
    showToast(message, type = 'info') {
        this.toastContainer.innerHTML = `
            <div class="toast toast-${type}">
                <span>${message}</span>
            </div>
        `;
        this.toastContainer.classList.add('active');

        if (this.toastTimeout) {
            clearTimeout(this.toastTimeout);
        }

        this.toastTimeout = setTimeout(() => {
            this.toastContainer.classList.remove('active');
        }, 3000);
    }

    /**
     * Generate floating bubbles.
     */
    generateParticles() {
        const bg = document.getElementById('particle-bg');
        if (!bg) return;

        const count = 20;
        for (let i = 0; i < count; i++) {
            const particle = document.createElement('span');
            particle.className = 'particle';
            const size = Math.random() * 60 + 10;
            const left = Math.random() * 100;
            const delay = Math.random() * 10;
            const duration = Math.random() * 15 + 10;

            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.left = `${left}%`;
            particle.style.animationDelay = `${delay}s`;
            particle.style.animationDuration = `${duration}s`;

            bg.appendChild(particle);
        }
    }
}

// Bootstrap
window.addEventListener('DOMContentLoaded', () => {
    window.app = new Application();
});
