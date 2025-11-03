<?php
/**
 * Reusable Admin Header Partial
 * 
 * Used across Campaign Builder, Room Thresholds, Scoring Rules, AI Settings, and Global Templates
 * 
 * @package DirectReach
 * @subpackage Admin\Views
 * 
 * @param array $args {
 *     Header configuration
 *     @type string $page_badge      Badge text (e.g., "Campaign Builder", "AI Configuration")
 *     @type string $active_page     Current page slug for highlighting menu item
 *     @type bool   $show_back_btn   Whether to show "Back to Campaign Builder" button (default: false)
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract args with defaults
$page_badge = isset($args['page_badge']) ? $args['page_badge'] : 'DirectReach';
$active_page = isset($args['active_page']) ? $args['active_page'] : '';
$show_back_btn = isset($args['show_back_btn']) ? $args['show_back_btn'] : false;

// Get current user info
$current_user = wp_get_current_user();
$user_initials = strtoupper(substr($current_user->display_name, 0, 2));
$user_role = !empty($current_user->roles) ? ucfirst($current_user->roles[0]) : 'User';

// Determine logo path - works from any location
$logo_url = plugins_url('assets/MEMO_Seal.png', dirname(dirname(dirname(__FILE__))));
?>

<header class="admin-header">
    <div class="header-content">
        <div class="header-left">
            <img src="<?php echo esc_url($logo_url); ?>" 
                 alt="DirectReach" 
                 class="header-logo">
            <span class="admin-badge"><?php echo esc_html($page_badge); ?></span>
        </div>
        
        <div class="header-right">
            <?php if ($show_back_btn): ?>
            <!-- Back to Campaign Builder -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=dr-campaign-builder')); ?>" 
               class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Campaign Builder
            </a>
            <?php endif; ?>
            
            <!-- Settings Dropdown -->
            <div class="settings-dropdown" id="settings-dropdown">
                <button class="settings-toggle" id="settings-toggle-btn">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="settings-menu" id="settings-menu">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dr-room-thresholds')); ?>" 
                       class="settings-item<?php echo $active_page === 'dr-room-thresholds' ? ' active' : ''; ?>">
                        <i class="fas fa-sliders-h"></i>
                        <span>Room Thresholds</span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dr-scoring-rules')); ?>" 
                       class="settings-item<?php echo $active_page === 'dr-scoring-rules' ? ' active' : ''; ?>">
                        <i class="fas fa-calculator"></i>
                        <span>Scoring Rules</span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dr-global-templates')); ?>" 
                       class="settings-item<?php echo $active_page === 'dr-global-templates' ? ' active' : ''; ?>">
                        <i class="fas fa-envelope-open-text"></i>
                        <span>Global Email Templates</span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=dr-ai-settings')); ?>" 
                       class="settings-item<?php echo $active_page === 'dr-ai-settings' ? ' active' : ''; ?>">
                        <i class="fas fa-robot"></i>
                        <span>AI Configuration</span>
                    </a>
                </div>
            </div>
            
            <!-- User Info -->
            <div class="admin-user-info">
                <div class="user-avatar"><?php echo esc_html($user_initials); ?></div>
                <div class="user-details">
                    <div class="user-name"><?php echo esc_html($current_user->display_name); ?></div>
                    <div class="user-role"><?php echo esc_html($user_role); ?></div>
                </div>
            </div>
            
            <!-- Logout -->
            <button class="logout-btn" onclick="window.location.href='<?php echo esc_url(wp_logout_url()); ?>'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
    </div>
</header>

<!-- Settings Dropdown Toggle Script -->
<script>
(function() {
    'use strict';
    
    const dropdown = document.getElementById('settings-dropdown');
    const toggleBtn = document.getElementById('settings-toggle-btn');
    const menu = document.getElementById('settings-menu');

    if (dropdown && dropdown.hasAttribute('data-initialized')) {
        return; // Skip if already initialized
    }    
    
    if (toggleBtn && dropdown) {
        // Toggle dropdown on button click
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
        
        // Prevent closing when clicking inside menu
        if (menu) {
            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }

    dropdown.setAttribute('data-initialized', 'true');    
})();
</script>