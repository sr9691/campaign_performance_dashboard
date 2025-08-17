<?php
/**
 * Standalone Hot List Settings Page
 * File: public/hot-list-settings.php
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check user permissions
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url() );
    exit;
}

$current_user = wp_get_current_user();
$is_admin = current_user_can( 'manage_options' );


// Get data provider and database
require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-data-provider.php';
require_once CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-hot-list-database.php';

$data_provider = new CPD_Data_Provider();
$hot_list_db = new CPD_Hot_List_Database();

// Determine client context
$all_clients = array();
$client_account = null;

$dashboard_url = get_option('cpd_client_dashboard_url');
if (empty($dashboard_url)) {
    $dashboard_url = home_url();
}

// $safe_dashboard_url = !empty($dashboard_url) ? ltrim($dashboard_url, '/') : home_url();

if ( $is_admin ) {
    // Admin user - get all clients and selected client from URL
    $all_clients = $data_provider->get_all_client_accounts();
    
    $selected_client_id = isset( $_GET['client_id'] ) ? sanitize_text_field( $_GET['client_id'] ) : null;
    
    if ( $selected_client_id ) {
        foreach ( $all_clients as $client ) {
            if ( $client->account_id === $selected_client_id ) {
                $client_account = $client;
                break;
            }
        }
    }
} else {
    // Client user - get their associated client automatically
    $account_id = $data_provider->get_account_id_by_user_id( $current_user->ID );
    if ( $account_id ) {
        $client_account = $data_provider->get_client_by_account_id( $account_id );
    }
    
    if ( ! $client_account ) {
        wp_die( 'Access denied. No client account associated with your user. Please contact an administrator.' );
    }
}

// Handle form submission
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) && $_POST['action'] === 'cpd_save_hot_list_settings' ) {
    // Verify nonce
    if ( ! isset( $_POST['hot_list_nonce'] ) || ! wp_verify_nonce( $_POST['hot_list_nonce'], 'cpd_save_hot_list_settings' ) ) {
        wp_die( 'Security check failed.' );
    }
    
    // Check permissions
    if ( ! $client_account ) {
        wp_die( 'No client selected.' );
    }
    
    // For non-admin users, ensure they can only edit their own client's settings
    if ( ! $is_admin ) {
        // For non-admin users, ensure they can only edit their own client's settings
        $user_account_id = $data_provider->get_account_id_by_user_id( $current_user->ID );
        $user_client = $user_account_id ? $data_provider->get_client_by_account_id( $user_account_id ) : null;
        if ( ! $user_client || $user_client->account_id !== $client_account->account_id ) {
            wp_die( 'Access denied. You can only modify settings for your own client account.' );
        }
    } else {
        // For admin users editing on behalf of clients, verify the client exists
        $admin_selected_client = null;
        foreach ( $all_clients as $client ) {
            if ( $client->account_id === $client_account->account_id ) {
                $admin_selected_client = $client;
                break;
            }
        }
        if ( ! $admin_selected_client ) {
            wp_die( 'Invalid client selection.' );
        }
    }
    
    // Get and sanitize data
    $client_id = $client_account->account_id;
    $revenue = isset( $_POST['revenue'] ) ? array_map( 'sanitize_text_field', $_POST['revenue'] ) : array();
    $company_size = isset( $_POST['company_size'] ) ? array_map( 'sanitize_text_field', $_POST['company_size'] ) : array();
    $industry = isset( $_POST['industry'] ) ? array_map( 'sanitize_text_field', $_POST['industry'] ) : array();
    $state = isset( $_POST['state'] ) ? array_map( 'sanitize_text_field', $_POST['state'] ) : array();
    $required_matches = intval( $_POST['required_matches'] );
    
    // Prepare settings array
    $settings = array(
        'revenue' => $revenue,
        'company_size' => $company_size,
        'industry' => $industry,
        'state' => $state,
        'required_matches' => $required_matches
    );
    
    // Save settings
    $result = $hot_list_db->save_settings( $client_id, $settings, get_current_user_id() );
    
    if ( $result ) {
        $success_message = 'Hot List settings saved successfully.';
        
        // Clear any cached data related to this client's Hot List
        if ( class_exists( 'CPD_Hot_List' ) && method_exists( 'CPD_Hot_List', 'clear_client_cache' ) ) {
            $hot_list = CPD_Hot_List::get_instance();
            $hot_list->clear_client_cache( $client_id );
        }

        // Redirect to prevent resubmission
        $redirect_url = $_SERVER['REQUEST_URI'];
        $redirect_url = add_query_arg( 'saved', '1', $redirect_url );
        wp_redirect( $redirect_url );
        exit;
    } else {
        $error_message = 'Failed to save settings. Please try again.';
    }
}

// Check for success message from redirect
if ( isset( $_GET['saved'] ) && $_GET['saved'] === '1' ) {
    $success_message = 'Hot List settings saved successfully.';
}

// Get current settings from database
$current_settings = array(
    'revenue' => array(),
    'company_size' => array(),
    'industry' => array(),
    'state' => array(),
    'required_matches' => 1
);

if ( $client_account ) {
    $saved_settings = $hot_list_db->get_settings( $client_account->account_id );
    
    if ( $saved_settings ) {
        $current_settings = array(
            'revenue' => $saved_settings->revenue_filters ?: array(),
            'company_size' => $saved_settings->company_size_filters ?: array(),
            'industry' => $saved_settings->industry_filters ?: array(),
            'state' => $saved_settings->state_filters ?: array(),
            'required_matches' => $saved_settings->required_matches ?: 1
        );
        
    } else {
        error_log('No Hot List Settings found for client: ' . $client_account->account_id);
    }
}

// Enqueue styles and scripts
wp_enqueue_style( 'cpd-hot-list-settings', CPD_DASHBOARD_PLUGIN_URL . 'assets/css/cpd-hot-list-settings.css', array(), filemtime( CPD_DASHBOARD_PLUGIN_DIR . 'assets/css/cpd-hot-list-settings.css' ) );
wp_enqueue_script( 'cpd-hot-list-settings', CPD_DASHBOARD_PLUGIN_URL . 'assets/js/cpd-hot-list-settings.js', array( 'jquery' ), filemtime( CPD_DASHBOARD_PLUGIN_DIR . 'assets/js/cpd-hot-list-settings.js' ), true );

// Localize script
wp_localize_script( 'cpd-hot-list-settings', 'cpd_hot_list_ajax', array(
    'ajax_url' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'cpd_hot_list_nonce' ),
    'current_page_url' => $_SERVER['REQUEST_URI'],
    'is_admin' => $is_admin,
    'current_client_id' => $client_account ? $client_account->account_id : null
));


// Hide WordPress admin bar
show_admin_bar( false );

// Get current theme to load proper styles
get_header();

?>

<style>
    /* Remove theme elements for clean settings page */
    .site-header, .site-footer, #header, #footer { display: none !important; }
    body { margin: 0; padding: 0; }
    #main, .main { min-height: 100vh; }
</style>

<?php if ( isset( $success_message ) ) : ?>
    <div class="notice notice-success" style="margin: 20px; padding: 12px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;">
        <p><?php echo esc_html( $success_message ); ?></p>
    </div>
<?php endif; ?>

<?php if ( isset( $error_message ) ) : ?>
    <div class="notice notice-error" style="margin: 20px; padding: 12px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">
        <p><?php echo esc_html( $error_message ); ?></p>
    </div>
<?php endif; ?>

<?php
// Instead of including the template, let's render the form directly here
// This avoids any include conflicts and gives us full control
?>

<div class="settings-container <?php echo $is_admin ? '' : 'full-width'; ?>">
    <?php if ( $is_admin && !empty( $all_clients ) ) : ?>
    <!-- Client Selection Panel (Admin Only) -->
    <div class="client-panel">
        <div class="client-panel-header">
            <h2><i class="fas fa-users"></i> All Clients</h2>
        </div>
        <ul class="client-list">
            <?php foreach ( $all_clients as $client ) : ?>
                <li class="client-list-item <?php echo ( $client_account && $client_account->account_id === $client->account_id ) ? 'active' : ''; ?>" 
                    data-client-id="<?php echo esc_attr( $client->account_id ); ?>">
                    <div class="client-name"><?php echo esc_html( $client->client_name ); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Main Settings Content -->
    <div class="settings-main">
        <div class="settings-header">
            <div class="header-left">
                <h1><i class="fas fa-fire"></i> Hot List Settings</h1>
                <?php if ( $client_account ) : ?>
                <div class="client-info">
                    <div class="client-name"><?php echo esc_html( $client_account->client_name ); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <?php if ( $client_account && !empty( $client_account->logo_url ) ) : ?>
                <div class="client-logo-container">
                    <img src="<?php echo esc_url( $client_account->logo_url ); ?>" alt="Client Logo">
                </div>
                <?php endif; ?>
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

            <?php if ( $client_account ) : ?>
                
                <!-- Detailed Summary Section -->
                <div class="settings-summary">
                    <div class="summary-title">
                        <i class="fas fa-list-check"></i> Selected Filter Values
                    </div>
                    <div class="summary-grid">
                        <div class="summary-group">
                            <div class="summary-group-title">Company Revenue</div>
                            <div class="summary-values" id="revenue-summary">
                                <?php 
                                if (!empty($current_settings['revenue'])) {
                                    $revenue_labels = array(
                                        'any' => 'Any',
                                        'below-500k' => 'Below $500k',
                                        '500k-1m' => '$500k - $1M',
                                        '1m-5m' => '$1M - $5M',
                                        '5m-10m' => '$5M - $10M',
                                        '10m-20m' => '$10M - $20M',
                                        '20m-50m' => '$20M - $50M',
                                        'above-50m' => 'Above $50M'
                                    );
                                    foreach ($current_settings['revenue'] as $value) {
                                        $label = isset($revenue_labels[$value]) ? $revenue_labels[$value] : $value;
                                        echo '<span class="summary-tag">' . esc_html($label) . '</span>';
                                    }
                                } else {
                                    echo '<span class="summary-tag empty">None selected</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="summary-group">
                            <div class="summary-group-title">Company Size</div>
                            <div class="summary-values" id="size-summary">
                                <?php 
                                if (!empty($current_settings['company_size'])) {
                                    $size_labels = array(
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
                                    foreach ($current_settings['company_size'] as $value) {
                                        $label = isset($size_labels[$value]) ? $size_labels[$value] : $value;
                                        echo '<span class="summary-tag">' . esc_html($label) . '</span>';
                                    }
                                } else {
                                    echo '<span class="summary-tag empty">None selected</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="summary-group">
                            <div class="summary-group-title">Industry</div>
                            <div class="summary-values" id="industry-summary">
                                <?php 
                                if (!empty($current_settings['industry'])) {
                                    foreach ($current_settings['industry'] as $value) {
                                        echo '<span class="summary-tag">' . esc_html($value) . '</span>';
                                    }
                                } else {
                                    echo '<span class="summary-tag empty">None selected</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="summary-group">
                            <div class="summary-group-title">States</div>
                            <div class="summary-values" id="state-summary">
                                <?php 
                                if (!empty($current_settings['state'])) {
                                    $state_labels = array(
                                        'any' => 'Any',
                                        'AL' => 'Alabama',
                                        'AK' => 'Alaska',
                                        'AZ' => 'Arizona',
                                        'AR' => 'Arkansas',
                                        'CA' => 'California',
                                        'CO' => 'Colorado',
                                        'CT' => 'Connecticut',
                                        'DE' => 'Delaware',
                                        'FL' => 'Florida',
                                        'GA' => 'Georgia',
                                        'HI' => 'Hawaii',
                                        'ID' => 'Idaho',
                                        'IL' => 'Illinois',
                                        'IN' => 'Indiana',
                                        'IA' => 'Iowa',
                                        'KS' => 'Kansas',
                                        'KY' => 'Kentucky',
                                        'LA' => 'Louisiana',
                                        'ME' => 'Maine',
                                        'MD' => 'Maryland',
                                        'MA' => 'Massachusetts',
                                        'MI' => 'Michigan',
                                        'MN' => 'Minnesota',
                                        'MS' => 'Mississippi',
                                        'MO' => 'Missouri',
                                        'MT' => 'Montana',
                                        'NE' => 'Nebraska',
                                        'NV' => 'Nevada',
                                        'NH' => 'New Hampshire',
                                        'NJ' => 'New Jersey',
                                        'NM' => 'New Mexico',
                                        'NY' => 'New York',
                                        'NC' => 'North Carolina',
                                        'ND' => 'North Dakota',
                                        'OH' => 'Ohio',
                                        'OK' => 'Oklahoma',
                                        'OR' => 'Oregon',
                                        'PA' => 'Pennsylvania',
                                        'RI' => 'Rhode Island',
                                        'SC' => 'South Carolina',
                                        'SD' => 'South Dakota',
                                        'TN' => 'Tennessee',
                                        'TX' => 'Texas',
                                        'UT' => 'Utah',
                                        'VT' => 'Vermont',
                                        'VA' => 'Virginia',
                                        'WA' => 'Washington',
                                        'WV' => 'West Virginia',
                                        'WI' => 'Wisconsin',
                                        'WY' => 'Wyoming'
                                    );
                                    foreach ($current_settings['state'] as $value) {
                                        $label = isset($state_labels[$value]) ? $state_labels[$value] : $value;
                                        echo '<span class="summary-tag">' . esc_html($label) . '</span>';
                                    }
                                } else {
                                    echo '<span class="summary-tag empty">None selected</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>                

            <!-- Settings Form -->
            <form id="hot-list-settings-form" class="settings-form" method="post">
                <?php wp_nonce_field( 'cpd_save_hot_list_settings', 'hot_list_nonce' ); ?>
                <input type="hidden" name="action" value="cpd_save_hot_list_settings">
                <input type="hidden" name="client_id" value="<?php echo esc_attr( $client_account->account_id ); ?>">

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
                                    'Any' => 'Any',
                                    'Agriculture' => 'Agriculture',
                                    'Automotive' => 'Automotive',
                                    'Construction' => 'Construction',
                                    'Creative Arts and Entertainment' => 'Creative Arts and Entertainment',
                                    'Education' => 'Education',
                                    'Energy' => 'Energy',
                                    'Finance and Banking' => 'Finance and Banking',
                                    'Food and Beverage' => 'Food and Beverage',
                                    'Government and Public Administration' => 'Government and Public Administration',
                                    'Health and Pharmaceuticals' => 'Health and Pharmaceuticals',
                                    'Information Technology' => 'Information Technology',
                                    'Manufacturing' => 'Manufacturing',
                                    'Marketing & Advertising' => 'Marketing & Advertising',
                                    'Media and Publishing' => 'Media and Publishing',
                                    'Non-Profit and Social Services' => 'Non-Profit and Social Services',
                                    'Professional and Business Services' => 'Professional and Business Services',
                                    'Real Estate' => 'Real Estate',
                                    'Retail' => 'Retail',
                                    'Telecommunications' => 'Telecommunications',
                                    'Tourism and Hospitality' => 'Tourism and Hospitality',
                                    'Transportation and Logistics' => 'Transportation and Logistics',
                                    'Utilities' => 'Utilities'
                                );
                                foreach ( $industry_options as $value => $label ) :
                                    $checked = in_array( $value, $current_settings['industry'] ) ? 'checked' : '';
                                ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="industry-<?php echo esc_attr( $label ); ?>" 
                                           name="industry[]" value="<?php echo esc_attr( $label ); ?>" <?php echo $checked; ?>>
                                    <label for="industry-<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?></label>
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
                                    'AL' => 'Alabama',
                                    'AK' => 'Alaska',
                                    'AZ' => 'Arizona',
                                    'AR' => 'Arkansas',
                                    'CA' => 'California',
                                    'CO' => 'Colorado',
                                    'CT' => 'Connecticut',
                                    'DE' => 'Delaware',
                                    'FL' => 'Florida',
                                    'GA' => 'Georgia',
                                    'HI' => 'Hawaii',
                                    'ID' => 'Idaho',
                                    'IL' => 'Illinois',
                                    'IN' => 'Indiana',
                                    'IA' => 'Iowa',
                                    'KS' => 'Kansas',
                                    'KY' => 'Kentucky',
                                    'LA' => 'Louisiana',
                                    'ME' => 'Maine',
                                    'MD' => 'Maryland',
                                    'MA' => 'Massachusetts',
                                    'MI' => 'Michigan',
                                    'MN' => 'Minnesota',
                                    'MS' => 'Mississippi',
                                    'MO' => 'Missouri',
                                    'MT' => 'Montana',
                                    'NE' => 'Nebraska',
                                    'NV' => 'Nevada',
                                    'NH' => 'New Hampshire',
                                    'NJ' => 'New Jersey',
                                    'NM' => 'New Mexico',
                                    'NY' => 'New York',
                                    'NC' => 'North Carolina',
                                    'ND' => 'North Dakota',
                                    'OH' => 'Ohio',
                                    'OK' => 'Oklahoma',
                                    'OR' => 'Oregon',
                                    'PA' => 'Pennsylvania',
                                    'RI' => 'Rhode Island',
                                    'SC' => 'South Carolina',
                                    'SD' => 'South Dakota',
                                    'TN' => 'Tennessee',
                                    'TX' => 'Texas',
                                    'UT' => 'Utah',
                                    'VT' => 'Vermont',
                                    'VA' => 'Virginia',
                                    'WA' => 'Washington',
                                    'WV' => 'West Virginia',
                                    'WI' => 'Wisconsin',
                                    'WY' => 'Wyoming'
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
                    </div>
                </div>

                <?php
                // Build the back URL with preserved client_id parameter
                $back_to_dashboard_url = $dashboard_url;

                // If admin user and a client is selected, preserve the client_id in the back URL
                if ( $is_admin && $client_account ) {
                    $url_parts = parse_url( $back_to_dashboard_url );
                    $query_params = array();
                    
                    // Parse existing query parameters if any
                    if ( isset( $url_parts['query'] ) ) {
                        parse_str( $url_parts['query'], $query_params );
                    }
                    
                    // Add the client_id parameter
                    $query_params['client_id'] = $client_account->account_id;
                    
                    // Rebuild the URL
                    $back_to_dashboard_url = $url_parts['scheme'] . '://' . $url_parts['host'];
                    if ( isset( $url_parts['port'] ) ) {
                        $back_to_dashboard_url .= ':' . $url_parts['port'];
                    }
                    if ( isset( $url_parts['path'] ) ) {
                        $back_to_dashboard_url .= $url_parts['path'];
                    }
                    $back_to_dashboard_url .= '?' . http_build_query( $query_params );
                    
                    if ( isset( $url_parts['fragment'] ) ) {
                        $back_to_dashboard_url .= '#' . $url_parts['fragment'];
                    }
                }
                ?>

                <!-- In the Action Buttons section, replace the back button with: -->
                <div class="action-buttons">
                    <a href="<?php echo esc_url( $back_to_dashboard_url ); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
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

<?php get_footer(); ?>