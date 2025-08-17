<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables passed from CPD_Public::display_dashboard()
// $plugin_name
// $client_account (can be null for 'All Clients' in admin view)
// $summary_metrics
// $campaign_data
// $visitor_data
// $passed_selected_client_id_from_url <--- This variable is now passed
// $duration_param <--- This variable is now passed

$current_user = wp_get_current_user();
$is_admin = current_user_can( 'manage_options' ); // Check if the current user is an admin
$memo_logo_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Logo.png';
$memo_seal_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Seal.png';

// Use client_account->logo_url for the top-left logo, with a fallback
// For 'All Clients' (when $client_account is null), you might want a default generic logo
$client_logo_url = isset($client_account->logo_url) ? esc_url($client_account->logo_url) : esc_url($memo_logo_url); // Fallback to your main logo if no specific client logo

// Get all clients for the left sidebar (only needed if admin and sidebar is visible)
$data_provider = new CPD_Data_Provider();
$all_clients = $is_admin ? $data_provider->get_all_client_accounts() : []; // Only fetch if admin

// Define the URL for the admin management page
$admin_management_url = admin_url( 'admin.php?page=' . $plugin_name . '-management' );

?>

<div class="dashboard-container">
    <?php if ( $is_admin ) : ?>
    <div class="account-panel">
        <ul class="account-list">
            <li class="account-list-item <?php echo ( $passed_selected_client_id_from_url === 'all' || ( !$passed_selected_client_id_from_url && !$client_account ) ) ? 'active' : ''; ?>"
                data-client-id="all">
                All Clients
            </li>
            <?php if (!empty($all_clients)) : ?>
                <?php foreach ($all_clients as $client) : ?>
                    <li class="account-list-item <?php echo ( $passed_selected_client_id_from_url === $client->account_id ) ? 'active' : ''; ?>"
                        data-client-id="<?php echo esc_attr($client->account_id); ?>">
                        <?php echo esc_html( wp_unslash( $client->client_name ) ); ?>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <?php endif; ?>
        </ul>

        <div class="brand-bottom-section">
            <?php
            // The $report_problem_email variable will be passed from display_dashboard()
            $report_email = !empty($report_problem_email) ? esc_attr($report_problem_email) : 'support@example.com';
            ?>
            <a href="mailto:<?php echo $report_email; ?>" class="report-bug-button">
                <i class="fas fa-bug"></i> Report a Problem
            </a>
            <?php if ( $is_admin ) : // Link to Admin Management page for admins ?>
                <a href="<?php echo esc_url( $admin_management_url ); ?>" class="admin-link-button">
                    <i class="fas fa-cog"></i> Admin
                </a>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>

    <div class="main-content <?php echo $is_admin ? 'has-admin-sidebar' : 'no-admin-sidebar'; ?>">
        <div class="dashboard-header">
            <div class="left-header">
                <div class="header-title-section">
                    <h1>DirectReach Report</h1>
                    <div class="duration-select">
                        <span>Date Range:</span>
                        <select id="duration-selector">
                            <option value="campaign" <?php selected( $duration_param, 'campaign' ); ?>>Campaign Dates</option>
                            <option value="30" <?php selected( $duration_param, '30' ); ?>>Past 30 days</option>
                            <option value="7" <?php selected( $duration_param, '7' ); ?>>Past 7 days</option>
                            <option value="1" <?php selected( $duration_param, '1' ); ?>>Yesterday</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="client-logo-container">
                <img src="<?php echo esc_url( $client_logo_url ); ?>" alt="Client Logo">
            </div>
        </div>

        <h2>All Accounts</h2>

        <?php if ( ! empty( $summary_metrics ) ) : ?>
        <div class="summary-cards">
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['impressions'] ); ?></p>
                <p class="label" data-summary-key="impressions">Impressions</p> </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['reach'] ); ?></p>
                <p class="label" data-summary-key="reach">Reach</p> </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['ctr'] ); ?></p>
                <p class="label" data-summary-key="ctr">CTR</p> </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['new_contacts'] ); ?></p>
                <p class="label" data-summary-key="new_contacts">New Contacts</p> </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['crm_additions'] ); ?></p>
                <p class="label" data-summary-key="crm_additions">CRM Additions</p> </div>
        </div>
        <?php endif; ?>

        <div class="charts-section">
            <div class="chart-container" style="flex: 2;">
                <h3>Impressions Chart</h3>
                <canvas id="impressions-chart-canvas"></canvas>
            </div>
            <div class="chart-container" style="flex: 1;">
                <h3>Impressions by Group</h3>
                <canvas id="ad-group-chart-canvas"></canvas>
            </div>
        </div>

        <?php if ( ! empty( $campaign_data ) ) : ?>
        <div class="ad-group-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ad Group Name</th>
                        <th>Impressions</th>
                        <th>Reach</th>
                        <th>Clicks</th>
                        <th>CTR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $campaign_data as $ad_group ) : ?>
                    <tr>
                        <td><?php echo esc_html( ( $ad_group->ad_group_name ) ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->impressions ) ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->reach ) ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->clicks ) ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->ctr, 2 ) ); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="visitor-panel">
        <div class="visitor-header">
            <div class="visitor-tabs">
                <div class="visitor-tab hot-list-tab active" data-tab="hot-list">
                    <div class="tab-content-wrapper">
                        <span class="tab-icon">ðŸ”¥</span>
                        <span class="tab-label">Hot List</span>
                        <span class="tab-count">(0)</span>
                    </div>
                    <div class="tab-settings-icon" title="Hot List Settings">
                        <i class="fas fa-cog"></i>
                    </div>
                </div>
                <div class="visitor-tab all-visitors-tab" data-tab="all-visitors">
                    <div class="tab-content-wrapper">
                        <span class="tab-icon"><i class="fas fa-users"></i></span>
                        <span class="tab-label">All Visitors</span>
                        <span class="tab-count">(0)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="visitor-list">

            <!-- Hot List Tab Content -->
            <div class="tab-content active" id="hot-list-content">
                <div class="no-data">Loading hot list...</div>
            </div>

            <!-- All Visitors Tab Content -->
            <div class="tab-content" id="all-visitors-content">
                <div class="no-data">Loading visitors...</div>
            </div>
            
<!-- REMOVED FROM HERE -->
        </div>
    </div>
</div>

<div id="visitor-info-modal" class="modal">
    <!-- Modal content will be dynamically generated by JavaScript -->
    <!-- This ensures we have the latest visitor data and proper styling -->
</div>

