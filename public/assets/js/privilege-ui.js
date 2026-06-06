/**
 * Client-side Privilege and Upgrades interface logic
 */
document.addEventListener('DOMContentLoaded', () => {
    // Check if the user is a guest (look for the guest badge or lock badges)
    const isGuest = document.getElementById('header-guest-badge') !== null || document.getElementById('avatar-lock-icon-btn') !== null;
    if (!isGuest) return; // Registered users have full access

    // Helper: Display floating lock tooltip
    function showLockTooltip(targetEl, text) {
        // Remove existing tooltips first
        const existing = document.querySelector('.feature-locked-tooltip');
        if (existing) existing.remove();

        const tooltip = document.createElement('div');
        tooltip.className = 'feature-locked-tooltip';
        tooltip.innerHTML = `🔒 <strong>Upgrade Required</strong><br>${text}`;
        
        document.body.appendChild(tooltip);
        
        const rect = targetEl.getBoundingClientRect();
        tooltip.style.left = `${rect.left + window.scrollX}px`;
        tooltip.style.top = `${rect.bottom + window.scrollY + 8}px`;

        // Automatically clean up after 3 seconds
        const handleRemove = () => {
            if (tooltip.parentNode) {
                tooltip.style.opacity = '0';
                tooltip.style.transform = 'translateY(10px)';
                tooltip.style.transition = 'all 0.3s ease';
                setTimeout(() => tooltip.remove(), 300);
            }
        };

        setTimeout(handleRemove, 3000);
    }

    // Intercept Locked Inbox Nav clicks
    const inboxLocked = document.getElementById('nav-btn-inbox-locked');
    if (inboxLocked) {
        inboxLocked.addEventListener('click', (e) => {
            e.preventDefault();
            showLockTooltip(inboxLocked, 'Register to unlock inbox & message history.');
        });
    }

    // Intercept Header Avatar / Lock clicks to trigger Register Flow
    const userAvatarBtn = document.getElementById('header-user-avatar-btn');
    if (userAvatarBtn) {
        userAvatarBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Try to open Register Card
            const regBtn = document.getElementById('btn-switch-to-register');
            if (regBtn) {
                regBtn.click();
            } else {
                showLockTooltip(userAvatarBtn, 'Register to upload & crop profile photos.');
            }
        });
    }

    // Intercept Guest Badge Upgrade button
    const upgradeBadgeBtn = document.getElementById('btn-upgrade-from-badge');
    if (upgradeBadgeBtn) {
        upgradeBadgeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const regBtn = document.getElementById('btn-switch-to-register');
            if (regBtn) {
                regBtn.click();
            }
        });
    }

    // Intercept attach buttons or other locked features in Settings
    const settingsTrigger = document.getElementById('btn-settings-trigger');
    if (settingsTrigger) {
        // Settings are available for guests but with profile photo locked
        // We can hook settings saves to warn guests
    }

    // Attach locked tooltip on other direct-chat features for guests (e.g. Friends button)
    const friendsBtn = document.getElementById('nav-btn-friends');
    if (friendsBtn) {
        friendsBtn.addEventListener('click', (e) => {
            // Check if active view is browser or if they click friends tab
            // For guests, we can lock adding friends
        });
    }

    const addFriendBtn = document.getElementById('btn-add-friend');
    if (addFriendBtn) {
        addFriendBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            showLockTooltip(addFriendBtn, 'Register to add friends and direct message.');
        });
    }
});
