<?php
/**
 * Updated HTML template for the public-facing campaign performance dashboard.
 * This creates the three-column layout: Left sidebar, Main content, Right sidebar
 *
 * This version conditionally displays the left sidebar based on user role
 * and adds a link to the admin management page for administrators.
 */

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
        <div class="logo-container">
            <img src="<?php echo esc_url( $client_logo_url ); ?>" alt="Client Logo">
        </div>
        
        <ul class="account-list">
            <li class="account-list-item <?php echo ( $passed_selected_client_id_from_url === 'all' || ( !$passed_selected_client_id_from_url && !$client_account ) ) ? 'active' : ''; ?>" 
                data-client-id="all">
                All Clients
            </li>
            <?php if (!empty($all_clients)) : ?>
                <?php foreach ($all_clients as $client) : ?>
                    <li class="account-list-item <?php echo ( $passed_selected_client_id_from_url === $client->account_id ) ? 'active' : ''; ?>" 
                        data-client-id="<?php echo esc_attr($client->account_id); ?>">
                        <?php echo esc_html($client->client_name); ?>
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
                <div class="client-logo-container">
                    <img src="<?php echo esc_url( $client_logo_url ); ?>" alt="Client Logo">
                </div>
                <div class="header-title-section">
                    <h1>Digital Marketing Report</h1>
                    <div class="duration-select">
                        <span>Campaign Duration:</span>
                        <select id="duration-selector">
                            <option value="campaign">Campaign Duration</option>
                            <option value="30">30 days</option>
                            <option value="7">7 days</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="right-header">
                <div class="client-brand-logo">
                    <img src="<?php echo esc_url( $memo_logo_url ); ?>" alt="MEMO Marketing Group">
                </div>
            </div>
        </div>

        <h2>All Accounts</h2>

        <?php if ( ! empty( $summary_metrics ) ) : ?>
        <div class="summary-cards">
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['impressions'] ); ?></p>
                <p class="label">Impressions</p>
            </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['reach'] ); ?></p>
                <p class="label">Reach</p>
            </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['ctr'] ); ?></p>
                <p class="label">CTR</p>
            </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['new_contacts'] ); ?></p>
                <p class="label">New Contacts</p>
            </div>
            <div class="summary-card">
                <p class="value"><?php echo esc_html( $summary_metrics['crm_additions'] ); ?></p>
                <p class="label">CRM Additions</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="charts-section">
            <div class="chart-container" style="flex: 2;">
                <h3>Impressions Chart</h3>
                <canvas id="impressions-chart-canvas"></canvas>
            </div>
            <div class="chart-container" style="flex: 1;">
                <h3>Impressions by Ad Group</h3>
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
                        <th>CTR</th>
                        <th>Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $campaign_data as $ad_group ) : ?>
                    <tr>
                        <td><?php echo esc_html( $ad_group->ad_group_name ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->impressions ) ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->reach ) ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->ctr, 2 ) ); ?>%</td>
                        <td><?php echo esc_html( number_format( $ad_group->clicks ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( ! empty( $visitor_data ) ) : ?>
    <div class="visitor-panel">
        <div class="header">All Visitors</div>
        <div class="visitor-list">
            <?php foreach ( $visitor_data as $visitor ) : ?>
            <div class="visitor-card" data-visitor-id="<?php echo esc_attr( $visitor->visitor_id ); ?>">
                <div class="visitor-logo">
                    <img src="<?php echo esc_url( $memo_seal_url ); ?>" alt="Referrer Logo">
                </div>
                <div class="visitor-details">
                    <p class="visitor-name">
                        <?php 
                        $full_name = '';
                        if ( ! empty( $visitor->first_name ) ) {
                            $full_name .= $visitor->first_name;
                        }
                        if ( ! empty( $visitor->last_name ) ) {
                            if ( ! empty( $full_name ) ) {
                                $full_name .= ' ';
                            }
                            $full_name .= $visitor->last_name;
                        }
                        echo esc_html( ! empty( $full_name ) ? $full_name : 'Unknown Visitor' );
                        ?>
                    </p>
                    <div class="visitor-info">
                        <p><i class="fas fa-briefcase"></i> <?php echo esc_html( $visitor->job_title ?? 'Unknown' ); ?></p>
                        <p><i class="fas fa-building"></i> <?php echo esc_html( $visitor->company_name ?? 'Unknown' ); ?></p>
                        <p>
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php 
                            $location_parts = [];
                            if ( ! empty( $visitor->city ) ) {
                                $location_parts[] = $visitor->city;
                            }
                            if ( ! empty( $visitor->state ) ) {
                                $location_parts[] = $visitor->state;
                            }
                            if ( ! empty( $visitor->zipcode ) ) {
                                $location_parts[] = $visitor->zipcode;
                            }
                            echo esc_html( ! empty( $location_parts ) ? implode(', ', $location_parts) : 'Unknown' );
                            ?>
                        </p>
                        <p><i class="fas fa-envelope"></i> <?php echo esc_html( $visitor->email ?? 'Unknown' ); ?></p>
                    </div>
                </div>
                <div class="visitor-actions">
                    <span class="icon add-crm-icon" title="Add to CRM">
                        <i class="fas fa-plus-square"></i>
                    </span>
                    <span class="icon delete-icon" title="Archive">
                        <i class="fas fa-trash-alt"></i>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>