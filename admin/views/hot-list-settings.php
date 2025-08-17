<?php
/**
 * Hot List Settings Page Template
 * 
 * Variables available:
 * - $is_admin: boolean - Whether current user is admin
 * - $client_account: object - Current client account data
 * - $all_clients: array - All clients (admin only)
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user = wp_get_current_user();

// Get client context for form
$selected_client_id = $client_account ? $client_account->account_id : null;
$selected_client_name = $client_account ? $client_account->client_name : 'No Client Selected';

// Mock settings data - will be replaced with database data in Iteration 2
$current_settings = array(
    'revenue' => array(),
    'company_size' => array(),
    'industry' => array(),
    'state' => array(),
    'required_matches' => 1
);

// Dashboard URL for back button
$dashboard_url = get_option( 'cpd_client_dashboard_url', home_url() );

?>

<div class="settings-container <?php echo $is_admin ? '' : 'full-width'; ?>">
    <?php if ( $is_admin && !empty( $all_clients ) ) : ?>
    <!-- Client Selection Panel (Admin Only) -->
    <div class="client-panel">
        <div class="client-panel-header">
            <h2><i class="fas fa-users"></i> Select Client</h2>
        </div>
        <ul class="client-list">
            <?php foreach ( $all_clients as $client ) : ?>
                <li class="client-list-item <?php echo ( $selected_client_id === $client->account_id ) ? 'active' : ''; ?>" 
                    data-client-id="<?php echo esc_attr( $client->account_id ); ?>">
                    <div class="client-name"><?php echo esc_html( $client->client_name ); ?></div>
                    <div class="client-id">ID: <?php echo esc_html( $client->account_id ); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Main Settings Content -->
    <div class="settings-main">
        <div class="settings-header">
            <h1><i class="fas fa-fire"></i> Hot List Settings</h1>
            <div class="header-actions">
                <?php if ( $client_account ) : ?>
                <div class="client-info">
                    <div class="client-name"><?php echo esc_html( $selected_client_name ); ?></div>
                    <div class="client-id">ID: <?php echo esc_html( $selected_client_id ); ?></div>
                </div>
                <?php endif; ?>
                <a href="<?php echo esc_url( $dashboard_url ); ?>" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <div class="settings-content">
            <?php if ( !$client_account ) : ?>
            <!-- No Client Selected State -->
            <div class="no-client-selected">
                <div class="empty-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>No Client Selected</h3>
                <p>Please select a client from the left panel to configure their Hot List settings.</p>
            </div>
            <?php else : ?>
            
            <!-- Settings Form -->
            <form id="hot-list-settings-form" class="settings-form" method="post">
                <?php wp_nonce_field( 'cpd_save_hot_list_settings', 'hot_list_nonce' ); ?>
                <input type="hidden" name="action" value="cpd_save_hot_list_settings">
                <input type="hidden" name="client_id" value="<?php echo esc_attr( $selected_client_id ); ?>">

                <!-- Filter Criteria Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-filter"></i> Filter Criteria
                    </div>

                    <div class="filter-grid">
                        <!-- Company Revenue -->
                        <div class="filter-group">
                            <div class="filter-title">Company Revenue</div>
                            <div class="checkbox-group">
                                <?php
                                $revenue_options = array(
                                    'any' => 'Any',
                                    'below-500k' => 'Below $500k',
                                    '500k-1m' => '$500k - $1M',
                                    '1m-5m' => '$1M - $5M',
                                    '5m-10m' => '$5M - $10M',
                                    '10m-20m' => '$10M - $20M',
                                    '20m-50m' => '$20M - $50M',
                                    'above-50m' => 'Above $50M'
                                );
                                foreach ( $revenue_options as $value => $label ) :
                                    $checked = in_array( $value, $current_settings['revenue'] ) ? 'checked' : '';
                                ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="revenue-<?php echo esc_attr( $value ); ?>" 
                                           name="revenue[]" value="<?php echo esc_attr( $value ); ?>" <?php echo $checked; ?>>
                                    <label for="revenue-<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Company Size -->
                        <div class="filter-group">
                            <div class="filter-title">Company Size</div>
                            <div class="checkbox-group">
                                <?php
                                $size_options = array(
                                    'any' => 'Any',
                                    '1-10' => '1-10',
                                    '11-20' => '11-20',
                                    '21-50' => '21-50',
                                    '51-200' => '51-200',
                                    '200-500' => '200-500',
                                    '500-1000' => '500-1000',
                                    '1000-5000' => '1000-5000',
                                    'above-5000' => 'Above 5000'
                                );
                                foreach ( $size_options as $value => $label ) :
                                    $checked = in_array( $value, $current_settings['company_size'] ) ? 'checked' : '';
                                ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="size-<?php echo esc_attr( $value ); ?>" 
                                           name="company_size[]" value="<?php echo esc_attr( $value ); ?>" <?php echo $checked; ?>>
                                    <label for="size-<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Industry Category -->
                        <div class="filter-group">
                            <div class="filter-title">Industry Category</div>
                            <div class="checkbox-group">
                                <?php
                                $industry_options = array(
                                    'any' => 'Any',
                                    'agriculture' => 'Agriculture',
                                    'automotive' => 'Automotive',
                                    'construction' => 'Construction',
                                    'creative' => 'Creative Arts and Entertainment',
                                    'education' => 'Education',
                                    'energy' => 'Energy',
                                    'finance' => 'Finance and Banking',
                                    'food' => 'Food and Beverage',
                                    'government' => 'Government and Public Administration',
                                    'health' => 'Health and Pharmaceuticals',
                                    'information-technology' => 'Information Technology',
                                    'manufacturing' => 'Manufacturing',
                                    'marketing' => 'Marketing & Advertising',
                                    'media' => 'Media and Publishing',
                                    'nonprofit' => 'Non-Profit and Social Services',
                                    'professional' => 'Professional and Business Services',
                                    'realestate' => 'Real Estate',
                                    'retail' => 'Retail',
                                    'telecommunications' => 'Telecommunications',
                                    'tourism' => 'Tourism and Hospitality',
                                    'transportation' => 'Transportation and Logistics',
                                    'utilities' => 'Utilities'
                                );
                                foreach ( $industry_options as $value => $label ) :
                                    $checked = in_array( $value, $current_settings['industry'] ) ? 'checked' : '';
                                ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="industry-<?php echo esc_attr( $value ); ?>" 
                                           name="industry[]" value="<?php echo esc_attr( $value ); ?>" <?php echo $checked; ?>>
                                    <label for="industry-<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- States -->
                        <div class="filter-group">
                            <div class="filter-title">States</div>
                            <div class="checkbox-group">
                                <?php
                                $state_options = array(
                                    'any' => 'Any',
                                    'alabama' => 'Alabama',
                                    'alaska' => 'Alaska',
                                    'arizona' => 'Arizona',
                                    'arkansas' => 'Arkansas',
                                    'california' => 'California',
                                    'colorado' => 'Colorado',
                                    'connecticut' => 'Connecticut',
                                    'delaware' => 'Delaware',
                                    'florida' => 'Florida',
                                    'georgia' => 'Georgia',
                                    'hawaii' => 'Hawaii',
                                    'idaho' => 'Idaho',
                                    'illinois' => 'Illinois',
                                    'indiana' => 'Indiana',
                                    'iowa' => 'Iowa',
                                    'kansas' => 'Kansas',
                                    'kentucky' => 'Kentucky',
                                    'louisiana' => 'Louisiana',
                                    'maine' => 'Maine',
                                    'maryland' => 'Maryland',
                                    'massachusetts' => 'Massachusetts',
                                    'michigan' => 'Michigan',
                                    'minnesota' => 'Minnesota',
                                    'mississippi' => 'Mississippi',
                                    'missouri' => 'Missouri',
                                    'montana' => 'Montana',
                                    'nebraska' => 'Nebraska',
                                    'nevada' => 'Nevada',
                                    'new-hampshire' => 'New Hampshire',
                                    'new-jersey' => 'New Jersey',
                                    'new-mexico' => 'New Mexico',
                                    'new-york' => 'New York',
                                    'north-carolina' => 'North Carolina',
                                    'north-dakota' => 'North Dakota',
                                    'ohio' => 'Ohio',
                                    'oklahoma' => 'Oklahoma',
                                    'oregon' => 'Oregon',
                                    'pennsylvania' => 'Pennsylvania',
                                    'rhode-island' => 'Rhode Island',
                                    'south-carolina' => 'South Carolina',
                                    'south-dakota' => 'South Dakota',
                                    'tennessee' => 'Tennessee',
                                    'texas' => 'Texas',
                                    'utah' => 'Utah',
                                    'vermont' => 'Vermont',
                                    'virginia' => 'Virginia',
                                    'washington' => 'Washington',
                                    'west-virginia' => 'West Virginia',
                                    'wisconsin' => 'Wisconsin',
                                    'wyoming' => 'Wyoming'
                                );
                                foreach ( $state_options as $value => $label ) :
                                    $checked = in_array( $value, $current_settings['state'] ) ? 'checked' : '';
                                ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="state-<?php echo esc_attr( $value ); ?>" 
                                           name="state[]" value="<?php echo esc_attr( $value ); ?>" <?php echo $checked; ?>>
                                    <label for="state-<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Matching Logic Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-bullseye"></i> Matching Logic
                    </div>
                    
                    <div class="required-matches-section">
                        <div class="matches-title">
                            <i class="fas fa-calculator"></i> Required Matches
                        </div>
                        <div class="matches-selector">
                            <label for="required-matches">Require at least</label>
                            <select id="required-matches" name="required_matches" class="matches-select">
                                <option value="1" <?php selected( $current_settings['required_matches'], 1 ); ?>>1</option>
                                <option value="2" <?php selected( $current_settings['required_matches'], 2 ); ?>>2</option>
                                <option value="3" <?php selected( $current_settings['required_matches'], 3 ); ?>>3</option>
                                <option value="4" <?php selected( $current_settings['required_matches'], 4 ); ?>>4</option>
                            </select>
                            <span id="matches-text">of <span id="active-filters-count">0</span> active filters to match</span>
                        </div>
                        <div class="matches-description">
                            By default 1 filter must match for the lead to be considered hot. You can change the required number of matching filters above.
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" id="reset-settings">
                        <i class="fas fa-undo"></i> Reset to Defaults
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Hot List Settings
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>