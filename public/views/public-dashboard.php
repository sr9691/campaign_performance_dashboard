<?php
/**
 * HTML template for the public-facing campaign performance dashboard.
 * This file is included by the CPD_Public class to render the dashboard.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables passed from the public class:
// $client_account
// $summary_metrics
// $campaign_data
// $visitor_data

$current_user = wp_get_current_user();
$is_admin = current_user_can( 'manage_options' );
$memo_logo_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Logo.png';
$memo_seal_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Seal.png';
$client_logo_url = isset($client_account->logo_url) ? esc_url($client_account->logo_url) : 'https://i.imgur.com/gK9J2bC.png'; 
?>

<div class="dashboard-container">

    <?php if ( $is_admin ) : ?>
    <div class="account-panel">
        <div class="logo-container">
            <img src="<?php echo esc_url( $client_logo_url ); ?>" alt="Client Logo">
        </div>
        <ul class="account-list">
            <?php 
                // This section will be made dynamic in the Admin dashboard update.
                // For now, it remains a static mockup.
            ?>
            <li class="account-list-item">Appian Media</li>
            <li class="account-list-item active">CleanSlate</li>
            <li class="account-list-item">Club Works</li>
            <li class="account-list-item">LaundryStinks!</li>
        </ul>
        <div class="brand-bottom-section">
            <img src="<?php echo esc_url( $memo_logo_url ); ?>" alt="MEMO Marketing Group Logo" style="max-width: 150px;">
            <button class="report-bug-button">
                <i class="fas fa-bug"></i> Report a Problem
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-content">
        <div class="dashboard-header">
            <div class="left-header">
                <div class="client-logo-container">
                    <img src="<?php echo esc_url( $client_logo_url ); ?>" alt="Client Logo" style="max-width: 100%; max-height: 100%;">
                </div>
                <div class="header-title-section">
                    <h1>Digital Marketing Report</h1>
                    <div class="duration-select">
                        <span>Campaign Duration:</span>
                        <select>
                            <option>Campaign Duration</option>
                            <option>30 days</option>
                            <option>7 days</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php if ( ! $is_admin ) : ?>
            <div class="right-header">
                <div class="client-brand-logo">
                    <img class="logo-img" src="<?php echo esc_url( $memo_logo_url ); ?>" alt="MEMO Marketing Group">
                </div>
            </div>
            <?php endif; ?>
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
                        <td><?php echo esc_html( number_format( $ad_group->clicks ) ); ?></td>
                        <td><?php echo esc_html( number_format( $ad_group->ctr, 2 ) ); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $visitor_data ) ) : ?>
        <div class="visitor-panel">
            <div class="header">All Visitors</div>
            <div class="visitor-list">
                <?php foreach ( $visitor_data as $visitor ) : ?>
                <div class="visitor-card" data-visitor-id="<?php echo esc_attr( $visitor->visitor_id ); ?>">
                    <div class="visitor-logo"><img src="<?php echo esc_url( CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Seal.png' ); ?>" alt="Referrer Logo"></div>
                    <div class="visitor-details">
                        <p class="visitor-name"><?php echo esc_html( $visitor->name ); ?></p>
                        <div class="visitor-info">
                            <?php if ( ! empty( $visitor->job_title ) ) : ?><p><i class="fas fa-briefcase"></i> <?php echo esc_html( $visitor->job_title ); ?></p><?php endif; ?>
                            <?php if ( ! empty( $visitor->company_name ) ) : ?><p><i class="fas fa-building"></i> <?php echo esc_html( $visitor->company_name ); ?></p><?php endif; ?>
                            <?php if ( ! empty( $visitor->location ) ) : ?><p><i class="fas fa-map-marker-alt"></i> <?php echo esc_html( $visitor->location ); ?></p><?php endif; ?>
                            <?php if ( ! empty( $visitor->email ) ) : ?><p><i class="fas fa-envelope"></i> <?php echo esc_html( $visitor->email ); ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="visitor-actions"><span class="icon add-crm-icon"><i class="fas fa-plus-square"></i></span><span class="icon delete-icon"><i class="fas fa-trash-alt"></i></span></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>