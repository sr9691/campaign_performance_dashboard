<?php
/**
 * Updated HTML template for the public-facing campaign performance dashboard.
 * This creates the three-column layout: Left sidebar, Main content, Right sidebar
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user = wp_get_current_user();
$is_admin = current_user_can( 'manage_options' );
$memo_logo_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Logo.png';
$memo_seal_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Seal.png';
$client_logo_url = isset($client_account->logo_url) ? esc_url($client_account->logo_url) : 'https://i.imgur.com/gK9J2bC.png'; 

// Get all clients for the left sidebar
$data_provider = new CPD_Data_Provider();
$all_clients = $data_provider->get_all_client_accounts();
?>

<div class="dashboard-container">
    <!-- LEFT SIDEBAR - Account Panel -->
    <div class="account-panel">
        <div class="logo-container">
            <img src="<?php echo esc_url( $memo_logo_url ); ?>" alt="MEMO Marketing Group Logo">
        </div>
        
        <ul class="account-list">
            <?php if (!empty($all_clients)) : ?>
                <?php foreach ($all_clients as $client) : ?>
                    <li class="account-list-item <?php echo ($client->account_id === $client_account->account_id) ? 'active' : ''; ?>" 
                        data-client-id="<?php echo esc_attr($client->account_id); ?>">
                        <?php echo esc_html($client->client_name); ?>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <li class="account-list-item active">CleanSlate</li>
                <li class="account-list-item">Appian Media</li>
                <li class="account-list-item">Club Works</li>
                <li class="account-list-item">LaundryStinks!</li>
            <?php endif; ?>
        </ul>
        
        <div class="brand-bottom-section">
            <img src="<?php echo esc_url( $memo_logo_url ); ?>" alt="MEMO Marketing Group Logo">
            <button class="report-bug-button">
                <i class="fas fa-bug"></i> Report a Problem
            </button>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="main-content">
        <!-- Dashboard Header -->
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

        <!-- All Accounts Section -->
        <h2>All Accounts</h2>

        <!-- Summary Cards -->
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

        <!-- Charts Section -->
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

        <!-- Ad Group Data Table -->
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

    <!-- RIGHT SIDEBAR - Visitor Panel -->
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
                        // Check if first_name and last_name properties exist to avoid warnings.
                        $full_name = '';
                        if ( ! empty( $visitor->first_name ) ) {
                            $full_name .= $visitor->first_name;
                        }
                        if ( ! empty( $visitor->last_name ) ) {
                            if ( ! empty( $full_name ) ) {
                                $full_name .= ' '; // Add a space if both names exist
                            }
                            $full_name .= $visitor->last_name;
                        }
                        
                        // If no name is available, show a placeholder
                        echo esc_html( ! empty( $full_name ) ? $full_name : 'Unknown Visitor' );
                        ?>
                    </p>

                    <div class="visitor-info">
                        <p><i class="fas fa-briefcase"></i> <?php echo esc_html( $visitor->job_title ?? 'Unknown' ); ?></p>
                        <p><i class="fas fa-building"></i> <?php echo esc_html( $visitor->company_name ?? 'Unknown' ); ?></p>
                        <p>
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php 
                            // Concatenate city, state, and zipcode, default to 'Unknown'
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