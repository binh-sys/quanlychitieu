<header class="header">
    <div class="header-left">
        <button class="btn-menu" onclick="toggleSidebar()">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>

    <div class="header-right">
        <div class="user-menu">
            <div class="user-avatar">
                <?php 
                if (isset($user) && isset($user['full_name'])) {
                    echo strtoupper(substr($user['full_name'], 0, 2));
                } else {
                    echo 'AD';
                }
                ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo isset($user) && isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'Administrator'; ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
    </div>
</header>
