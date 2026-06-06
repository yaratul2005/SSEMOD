<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title>Inbox — ChatArena</title>
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
    <style>
        .inbox-container {
            max-width: 800px;
            margin: 2.5rem auto;
            padding: 0 1rem;
        }
        .inbox-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow-main);
        }
        .inbox-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .inbox-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: all 0.25s ease;
        }
        .inbox-item:hover {
            border-color: var(--accent-blue);
            transform: translateY(-2px);
            box-shadow: var(--glow-blue);
        }
        .inbox-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid var(--border);
        }
        .inbox-info {
            flex: 1;
            min-width: 0;
        }
        .inbox-name-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        .inbox-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .inbox-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .inbox-message {
            font-size: 0.85rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                <button class="nav-btn active" id="nav-btn-inbox">Inbox</button>
                <a href="dashboard" class="nav-link-anchor"><button class="nav-btn" id="nav-btn-dashboard">Dashboard</button></a>
            </nav>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <form action="auth/logout" method="POST" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button class="btn btn-secondary btn-small" type="submit">Sign Out</button>
                </form>
            </div>
        </header>

        <div class="inbox-container">
            <div class="inbox-card">
                <h2 style="font-size: 1.8rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; color: var(--accent-blue);">Your Conversations</h2>
                
                <div class="inbox-list">
                    <?php foreach ($conversations as $conv): ?>
                        <a href="arena?room=<?php echo htmlspecialchars($conv['room_id']); ?>" class="inbox-item">
                            <img class="inbox-avatar" src="avatar/<?php echo htmlspecialchars($conv['other_id']); ?>" alt="Avatar">
                            <div class="inbox-info">
                                <div class="inbox-name-row">
                                    <span class="inbox-name"><?php echo htmlspecialchars($conv['other_name']); ?></span>
                                    <span class="inbox-time"><?php echo $conv['last_message_time'] ? date('M j, Y, g:i a', strtotime($conv['last_message_time'])) : 'No messages'; ?></span>
                                </div>
                                <div class="inbox-message">
                                    <?php echo $conv['last_message'] ? htmlspecialchars($conv['last_message']) : '<i>Start direct chat...</i>'; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($conversations)): ?>
                        <div style="text-align: center; color: var(--text-secondary); padding: 3rem 1.5rem; font-size: 0.95rem;">
                            No messages exchanged yet. Use the <a href="arena" style="color: var(--accent-green); font-weight: 500;">Arena browser</a> or hit RandomChat to start your first conversation!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
