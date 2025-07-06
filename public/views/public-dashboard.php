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
        <div class="header">All Visitors</div>
        <div class="visitor-list">
            <?php // The JavaScript will dynamically load content here ?>
            <div class="no-data">Loading visitor data...</div>
            <?php
            // Initial render: If $visitor_data is not empty, display it.
            // This is primarily for the first page load before AJAX updates.
            if ( ! empty( $visitor_data ) ) :
                foreach ( $visitor_data as $visitor ) :
                // error_log('Debug: Visitor ID before HTML generation: ' . (isset($visitor->id) ? $visitor->id : 'ID NOT SET') . ' | Visitor object: ' . print_r($visitor, true));
                ?>
                    <div class="visitor-card"
                        data-visitor-id="<?php echo esc_attr( $visitor->id ); ?>"
                        data-last-seen-at="<?php echo esc_attr( $visitor->last_seen_at ?? 'N/A' ); ?>"
                        data-recent-page-count="<?php echo esc_attr( $visitor->recent_page_count ?? '0' ); ?>"
                        data-recent-page-urls="<?php
                            $recent_urls = [];
                            if (!empty($visitor->recent_page_urls)) {
                                // If it's already an array (e.g., from json_decode by data provider), use it directly.
                                if (is_array($visitor->recent_page_urls)) {
                                    $recent_urls = $visitor->recent_page_urls;
                                } elseif (is_string($visitor->recent_page_urls)) {
                                    // Attempt to decode as JSON first, in case it's a JSON string from the database
                                    $decoded = json_decode($visitor->recent_page_urls, true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                        $recent_urls = $decoded;
                                    } else {
                                        // Fallback: If not valid JSON, treat as comma-separated string
                                        $recent_urls = array_map('trim', explode(',', $visitor->recent_page_urls));
                                        $recent_urls = array_filter($recent_urls); // Remove any empty string elements
                                    }
                                }
                            }
                            // Ensure all elements are strings and re-index the array numerically (important for JSON arrays)
                            $recent_urls = array_map('strval', array_values($recent_urls));
                            // Finally, JSON encode the array and then escape it for the HTML attribute
                            echo esc_attr( json_encode( $recent_urls ) );
                        ?>"
                    >
                    <div class="visitor-top-row">
                        <div class="visitor-logo">
                            <img src="<?php echo esc_url( CPD_Referrer_Logo::get_logo_for_visitor( $visitor ) ); ?>" 
                                alt="<?php echo esc_attr( CPD_Referrer_Logo::get_alt_text_for_visitor( $visitor ) ); ?>"
                                title="<?php echo esc_attr( CPD_Referrer_Logo::get_referrer_url_for_visitor( $visitor ) ); ?>">
                        </div>
                        <div class="visitor-actions">
                            <span class="icon add-crm-icon" title="Add to CRM">
                                <i class="fas fa-plus-square"></i>
                            </span>
                            <?php if (!empty($visitor->linkedin_url)) : // Only show LinkedIn icon if URL exists ?>
                                <a href="<?php echo esc_url( $visitor->linkedin_url ); ?>" target="_blank" class="icon linkedin-icon" title="View LinkedIn Profile">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                            <?php endif; ?>
                            <span class="icon info-icon" title="More Info">
                                <i class="fas fa-info-circle"></i>
                            </span>
                            <span class="icon delete-icon" title="Archive">
                                <i class="fas fa-trash-alt"></i>
                            </span>
                        </div>
                    </div>

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
                        echo esc_html( ! empty( $full_name ) ? $full_name : 'Company Visit' );
                        ?>
                    </p>
                    <p class="visitor-company-main"><?php echo esc_html( $visitor->company_name ?? 'Unknown Company' ); ?></p>


                    <div class="visitor-details-body">
                        <p><i class="fas fa-briefcase"></i> <?php echo esc_html( $visitor->job_title ?? 'Unknown Title' ); ?></p>
                        <?php if ( !empty($visitor->company_name) ) : // Assuming full company name or specific detail needed here ?>
                            <p><i class="fas fa-building"></i> <?php echo esc_html( $visitor->company_name ); ?></p>
                        <?php endif; ?>
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
                            echo esc_html( ! empty( $location_parts ) ? implode(', ', $location_parts) : 'Unknown Location' );
                            ?>
                        </p>
                        <p><i class="fas fa-envelope"></i> <?php echo esc_html( $visitor->email ?? 'Unknown Email' ); ?></p>
                    </div>
                </div>
                <?php endforeach;
            else : // Add this else block for initial "no data" message
                ?>
                <div class="no-data">No visitor data found for initial display.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="visitor-info-modal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Visitor Details</h2>
        <div class="modal-body">
            <p><strong>Last Seen At:</strong> <span id="modal-last-seen-at"></span></p>
            <p><strong>Recent Page Count:</strong> <span id="modal-recent-page-count"></span></p>
            <div id="modal-recent-page-urls-container">
                <strong>Recent Page URLs:</strong>
                <ul id="modal-recent-page-urls">
                    </ul>
            </div>
        </div>
    </div>
</div>