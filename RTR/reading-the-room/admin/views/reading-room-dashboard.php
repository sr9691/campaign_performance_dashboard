<?php
/**
 * Reading the Room Dashboard View
 * 
 * Main dashboard interface matching the mockup design
 * 
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$user_initials = strtoupper(substr($current_user->first_name, 0, 1) . substr($current_user->last_name, 0, 1));
$user_display_name = $current_user->display_name;
$user_role = !empty($current_user->roles) ? ucfirst($current_user->roles[0]) : 'User';

// Get premium clients for dropdown
global $wpdb;
$clients = $wpdb->get_results(
    "SELECT id, client_name 
     FROM {$wpdb->prefix}cpd_clients 
     WHERE subscription_tier = 'premium' 
     AND rtr_enabled = 1
     ORDER BY client_name ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>DirectReach - Reading the Room</title>
    <?php 
    // CSS already enqueued via enqueue_custom_assets
    wp_print_styles();
    ?>
</head>
<body>
    <div class="reading-room-container">
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div class="header-title">
                    <img src="<?php echo plugins_url('assets/MEMO_Seal.png', dirname(__FILE__, 2)); ?>" 
                         alt="DirectReach" 
                         onerror="this.src='data:image/svg+xml,%3Csvg width=\'180\' height=\'60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Crect width=\'180\' height=\'60\' fill=\'white\' rx=\'8\'/%3E%3Ctext x=\'90\' y=\'35\' font-family=\'Montserrat, sans-serif\' font-size=\'24\' font-weight=\'700\' text-anchor=\'middle\' fill=\'%232c435d\'%3EDirectReach%3C/text%3E%3C/svg%3E'" />
                    <div class="premium-badge">Premium</div>
                    <h1>Reading the Room Dashboard</h1>
                </div>
                <div class="header-controls">
                    <select class="date-filter">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    <button class="refresh-btn">
                        <i class="fas fa-sync-alt"></i>
                        Refresh
                    </button>
                    <div class="user-info">
                        <div class="user-avatar"><?php echo esc_html($user_initials); ?></div>
                        <div>
                            <div style="font-weight: 600; font-size: 0.9rem; color: #2c435d;">
                                <?php echo esc_html($user_display_name); ?>
                            </div>
                            <div style="font-size: 0.8rem; color: #666;">
                                <?php echo esc_html($user_role); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Pipeline Overview Header -->
                <div class="pipeline-header">
                    <div class="header-left">
                        <h2>Pipeline Overview</h2>
                    </div>
                    <div class="header-right">
                        <?php if (current_user_can('administrator') && count($clients) > 1): ?>
                        <div class="client-selector">
                            <label>Client:</label>
                            <select class="client-dropdown">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo esc_attr($client->id); ?>">
                                    <?php echo esc_html($client->client_name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Room Cards -->
                <div class="room-cards-container">
                    <!-- Problem Room -->
                    <div class="room-overview-card problem-room">
                        <div class="room-card-header">
                            <div class="room-circle">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="room-info">
                                <h3>Problem Room</h3>
                                <p>Attract Phase</p>
                            </div>
                            <button class="chart-btn" data-room="problem" title="View Campaign Charts">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                        <div class="room-content">
                            <div class="room-metrics-clean">
                                <div class="room-count" data-room="problem">0</div>
                                <div class="room-label">ACTIVE PROSPECTS</div>
                            </div>
                            <div class="room-stats-horizontal">
                                <div class="stat-item">
                                    <span class="stat-number">0</span>
                                    <span class="stat-label">New Today</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">0%</span>
                                    <span class="stat-label">Progress Rate</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Solution Room -->
                    <div class="room-overview-card solution-room">
                        <div class="room-card-header">
                            <div class="room-circle">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div class="room-info">
                                <h3>Solution Room</h3>
                                <p>Identify & Nurture</p>
                            </div>
                            <button class="chart-btn" data-room="solution" title="View Campaign Charts">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                        <div class="room-content">
                            <div class="room-metrics-clean">
                                <div class="room-count" data-room="solution">0</div>
                                <div class="room-label">ENGAGED VISITORS</div>
                            </div>
                            <div class="room-stats-horizontal">
                                <div class="stat-item">
                                    <span class="stat-number">0</span>
                                    <span class="stat-label">High Scores</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">0%</span>
                                    <span class="stat-label">Open Rate</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Offer Room -->
                    <div class="room-overview-card offer-room">
                        <div class="room-card-header">
                            <div class="room-circle">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="room-info">
                                <h3>Offer Room</h3>
                                <p>Invite & Close</p>
                            </div>
                            <button class="chart-btn" data-room="offer" title="View Campaign Charts">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                        <div class="room-content">
                            <div class="room-metrics-clean">
                                <div class="room-count" data-room="offer">0</div>
                                <div class="room-label">SALES READY</div>
                            </div>
                            <div class="room-stats-horizontal">
                                <div class="stat-item">
                                    <span class="stat-number">0</span>
                                    <span class="stat-label">This Week</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">0%</span>
                                    <span class="stat-label">Click Rate</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sales Room -->
                    <div class="room-overview-card sales-room">
                        <div class="room-card-header">
                            <div class="room-circle">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="room-info">
                                <h3>Sales Room</h3>
                                <p>Negotiate & Convert</p>
                            </div>
                            <button class="chart-btn" data-room="sales" title="View Campaign Charts">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                        </div>
                        <div class="room-content">
                            <div class="room-metrics-clean">
                                <div class="room-count" data-room="sales">0</div>
                                <div class="room-label">SALES HANDOFFS</div>
                            </div>
                            <div class="room-stats-horizontal">
                                <div class="stat-item">
                                    <span class="stat-number">0</span>
                                    <span class="stat-label">This Week</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number">0</span>
                                    <span class="stat-label">Avg Days</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Individual Room Sections -->
                <div class="room-details-section">
                    <!-- Room details will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Modal -->
    <div class="modal" id="chart-modal">
        <div class="modal-content chart-modal-content">
            <button class="close-modal">&times;</button>
            <div class="modal-header">
                <h2 id="chart-modal-title">Campaign Analytics</h2>
                <div class="chart-controls">
                    <select id="chart-timeframe" class="chart-select">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    <select id="chart-type" class="chart-select">
                        <option value="bar">Bar Chart</option>
                        <option value="pie">Pie Chart</option>
                        <option value="line">Trend Line</option>
                    </select>
                </div>
            </div>
            <div class="modal-body">
                <div class="chart-container">
                    <canvas id="campaign-chart"></canvas>
                </div>
                <div class="chart-summary">
                    <div class="summary-stats">
                        <div class="summary-item">
                            <div class="summary-value" id="total-prospects">0</div>
                            <div class="summary-label">Total Prospects</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value" id="active-campaigns">0</div>
                            <div class="summary-label">Active Campaigns</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value" id="top-campaign">-</div>
                            <div class="summary-label">Top Campaign</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Details Modal -->
    <div class="modal" id="email-details-modal">
        <div class="modal-content" style="max-width: 700px">
            <button class="close-modal">&times;</button>
            <div class="modal-header">
                <h2 id="email-modal-title">Email Details</h2>
            </div>
            <div class="modal-body">
                <div id="email-modal-content"></div>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div class="notification-container"></div>

    <?php 
    // JavaScript already enqueued via enqueue_custom_assets
    wp_print_scripts();
    ?>
</body>
</html>