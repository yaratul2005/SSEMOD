<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Dashboard — ChatArena</title>
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
    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        .dashboard-container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
        }
        .dashboard-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-main);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .dashboard-header-title {
            color: var(--accent-blue);
            font-size: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            text-align: center;
        }
        .stat-item {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem 0.5rem;
        }
        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--accent-green);
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }
        .inbox-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .inbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }
        .inbox-item:hover {
            border-color: var(--accent-blue);
            transform: translateX(3px);
        }
        .inbox-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border);
        }
        .inbox-info {
            flex: 1;
            min-width: 0;
        }
        .inbox-name-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.15rem;
        }
        .inbox-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .inbox-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        .inbox-message {
            font-size: 0.8rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .danger-zone {
            border-color: rgba(255, 23, 68, 0.4);
            background: rgba(255, 23, 68, 0.02);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header>
            <div class="logo">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Chat<span style="color: var(--accent-green);">Arena</span></span>
            </div>
            <nav class="nav-links">
                <a href="arena" class="nav-link-anchor"><button class="nav-btn" id="nav-btn-browse">Arena</button></a>
                <a href="inbox" class="nav-link-anchor"><button class="nav-btn" id="nav-btn-inbox">Inbox</button></a>
                <button class="nav-btn active" id="nav-btn-dashboard">Dashboard</button>
            </nav>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <form action="auth/logout" method="POST" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button class="btn btn-secondary btn-small" type="submit">Sign Out</button>
                </form>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- LEFT PANEL: PROFILE CARD & IMAGE -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <div class="dashboard-card" style="align-items: center; text-align: center;">
                    <div style="position: relative;">
                        <img id="dashboard-avatar" src="avatar/<?php echo htmlspecialchars($userId); ?>?t=<?php echo time(); ?>" alt="Avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--border);">
                        <label for="dashboard-avatar-upload" style="position: absolute; bottom: 0; right: 0; background: var(--accent-blue); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--bg-secondary);" title="Upload Profile Photo">
                            <span style="font-size: 1.1rem; font-weight: bold; color: white;">+</span>
                        </label>
                        <input type="file" id="dashboard-avatar-upload" accept="image/jpeg,image/png,image/webp" style="display: none;">
                    </div>

                    <div>
                        <h3 id="dashboard-display-name" style="font-size: 1.35rem; color: var(--text-primary);"><?php echo htmlspecialchars($user['display_name'] ?? ''); ?></h3>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.1rem;">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></p>
                        <p style="font-size: 0.85rem; color: var(--accent-blue); margin-top: 0.4rem; font-weight: 500;">
                            <?php echo ($user['gender'] === 'F') ? '♀ Female' : (($user['gender'] === 'M') ? '♂ Male' : '⊘ Other'); ?>, <?php echo (int)($user['age'] ?? 18); ?> Y
                        </p>
                    </div>

                    <?php if (!empty($user['bio'])): ?>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); font-style: italic; border-top: 1px solid var(--border); padding-top: 0.75rem; width: 100%;">
                            "<?php echo htmlspecialchars($user['bio']); ?>"
                        </p>
                    <?php endif; ?>

                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: center; width: 100%; border-top: 1px solid var(--border); padding-top: 0.75rem;">
                        <?php foreach (($user['interests'] ?? []) as $tag): ?>
                            <span class="filter-chip active" style="font-size: 0.75rem; padding: 0.15rem 0.5rem; background: var(--bg-tertiary); cursor: default; margin: 0;"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($user['interests'])): ?>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);">No tags added</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- STATS CARD -->
                <div class="dashboard-card">
                    <h3 class="dashboard-header-title">Stats</h3>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_chats']; ?></div>
                            <div class="stat-label">Total Chats</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['messages_sent']; ?></div>
                            <div class="stat-label">Sent</div>
                        </div>
                        <div class="stat-item" style="grid-column: span 3; margin-top: 0.25rem;">
                            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);"><?php echo $stats['member_since']; ?></div>
                            <div class="stat-label">Member Since</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: EDIT PROFILE & DANGER ZONE -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <!-- EDIT PROFILE CARD -->
                <div class="dashboard-card">
                    <h3 class="dashboard-header-title">Edit Profile</h3>
                    <form id="edit-profile-form" onsubmit="return false;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <div class="input-group">
                            <label class="input-label" for="edit-display-name">Display Name</label>
                            <input class="text-input" type="text" id="edit-display-name" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" minlength="3" maxlength="20" required>
                        </div>
                        
                        <div class="input-group">
                            <label class="input-label" for="edit-bio">Bio</label>
                            <textarea class="text-input" id="edit-bio" name="bio" maxlength="160" rows="2"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="input-group">
                                <label class="input-label" for="edit-age">Age</label>
                                <input class="text-input" type="number" id="edit-age" name="age" value="<?php echo (int)($user['age'] ?? 18); ?>" min="13" max="99" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="edit-gender">Gender</label>
                                <select class="text-input" id="edit-gender" name="gender" style="background-color: var(--bg-tertiary);">
                                    <option value="F" <?php echo ($user['gender'] ?? 'O') === 'F' ? 'selected' : ''; ?>>♀ Female</option>
                                    <option value="M" <?php echo ($user['gender'] ?? 'O') === 'M' ? 'selected' : ''; ?>>♂ Male</option>
                                    <option value="O" <?php echo ($user['gender'] ?? 'O') === 'O' ? 'selected' : ''; ?>>⊘ Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="input-group">
                            <label class="input-label" for="edit-interests">Interest tags (max 5 tags, max 12 chars each, comma-separated)</label>
                            <input class="text-input" type="text" id="edit-interests" name="interests" value="<?php echo htmlspecialchars(implode(',', $user['interests'] ?? [])); ?>" placeholder="e.g. music, coding">
                        </div>

                        <!-- Update email and password section -->
                        <h4 style="color: var(--accent-blue); font-size: 1rem; border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 1.5rem; margin-bottom: 0.75rem;">Account Credentials</h4>
                        
                        <div class="input-group">
                            <label class="input-label" for="edit-email">Email address (changing email resets verification status)</label>
                            <input class="text-input" type="email" id="edit-email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required autocomplete="email">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                            <div class="input-group">
                                <label class="input-label" for="edit-current-password">Current Password (needed to change password)</label>
                                <input class="text-input" type="password" id="edit-current-password" name="current_password" placeholder="Current password" autocomplete="current-password">
                            </div>
                            <div class="input-group">
                                <label class="input-label" for="edit-new-password">New Password</label>
                                <input class="text-input" type="password" id="edit-new-password" name="new_password" placeholder="New password" autocomplete="new-password">
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                            <button class="btn btn-primary" id="btn-save-profile" type="submit">Save Settings</button>
                        </div>
                    </form>
                </div>

                <!-- INBOX PREVIEW CARD -->
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
                        <h3 style="color: var(--accent-blue); font-size: 1.5rem; margin: 0;">Recent Conversations</h3>
                        <a href="inbox" style="font-size: 0.85rem; color: var(--accent-green); font-weight: 500;">View All →</a>
                    </div>
                    
                    <div class="inbox-list">
                        <?php foreach ($conversations as $conv): ?>
                            <a href="arena?room=<?php echo htmlspecialchars($conv['room_id']); ?>" class="inbox-item">
                                <img class="inbox-avatar" src="avatar/<?php echo htmlspecialchars($conv['other_id']); ?>" alt="Avatar">
                                <div class="inbox-info">
                                    <div class="inbox-name-row">
                                        <span class="inbox-name"><?php echo htmlspecialchars($conv['other_name']); ?></span>
                                        <span class="inbox-time"><?php echo $conv['last_message_time'] ? date('M j, g:i a', strtotime($conv['last_message_time'])) : 'No messages'; ?></span>
                                    </div>
                                    <div class="inbox-message">
                                        <?php echo $conv['last_message'] ? htmlspecialchars($conv['last_message']) : '<i>Start direct chat...</i>'; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($conversations)): ?>
                            <div style="text-align: center; color: var(--text-secondary); font-size: 0.85rem; padding: 1.5rem;">
                                No recent conversations found. Go to the Arena and click a user to start chatting!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DANGER ZONE CARD -->
                <div class="dashboard-card danger-zone">
                    <h3 style="color: var(--accent-red); font-size: 1.25rem; border-bottom: 1px solid rgba(255, 23, 68, 0.2); padding-bottom: 0.5rem; margin-bottom: 0.5rem;">Danger Zone</h3>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">Deleting your account is permanent. Any message archives or direct relations will be deleted after a 30-day soft recovery grace window.</p>
                    <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                        <button class="btn btn-danger btn-small" id="btn-delete-account">Delete Account</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Toasts -->
    <div class="toast-container" id="dashboard-toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>

    <script>
        // Form post and profile logic
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Toast Helper
        function showToast(message, isError = false) {
            const container = document.getElementById('dashboard-toast-container');
            const toast = document.createElement('div');
            toast.style.background = isError ? 'var(--accent-red)' : 'var(--accent-green)';
            toast.style.color = isError ? '#ffffff' : '#0c0c0c';
            toast.style.padding = '0.75rem 1.25rem';
            toast.style.borderRadius = '8px';
            toast.style.boxShadow = 'var(--shadow-main)';
            toast.style.fontSize = '0.85rem';
            toast.style.fontWeight = '500';
            toast.style.marginBottom = '0.5rem';
            toast.style.animation = 'fadeIn 0.3s ease';
            toast.textContent = message;
            
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Handle profile settings submission
        document.getElementById('edit-profile-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            const btn = document.getElementById('btn-save-profile');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch('profile/update', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    }
                });
                
                const data = await res.json();
                if (res.ok && data.success) {
                    showToast('Settings saved successfully!');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.error || 'Failed to save settings.', true);
                }
            } catch (err) {
                showToast('A network error occurred.', true);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Settings';
            }
        });

        // Handle Avatar File Upload
        document.getElementById('dashboard-avatar-upload').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('avatar', file);

            showToast('Uploading avatar...');

            try {
                const res = await fetch('profile/avatar', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    }
                });
                
                const data = await res.json();
                if (res.ok && data.success) {
                    document.getElementById('dashboard-avatar').src = data.avatar_url;
                    showToast('Avatar updated successfully!');
                } else {
                    showToast(data.error || 'Failed to upload avatar.', true);
                }
            } catch (err) {
                showToast('Avatar upload failed.', true);
            }
        });

        // Delete account notice
        document.getElementById('btn-delete-account').addEventListener('click', () => {
            if (confirm('Are you absolutely sure you want to delete your account? You will have a 30-day window to recover it by logging back in.')) {
                showToast('This action is disabled in the preview, but soft deletion would occur.', true);
            }
        });
    </script>
</body>
</html>
