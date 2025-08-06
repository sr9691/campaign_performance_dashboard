<?php
/**
 * HTML template for the admin management page.
 * This page contains navigation sidebar and management sections.
 *
 * @package CPD_Dashboard
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables passed from CPD_Admin::render_admin_management_page
// $plugin_name
// $all_clients
// $logs
// $all_users
// $all_client_accounts_for_dropdown
// $data_provider (explicitly passed now)

$memo_logo_url = CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Logo.png';
$current_page = isset($_GET['page']) ? $_GET['page'] : '';
$client_dashboard_url = get_option( 'cpd_client_dashboard_url', '' ); // Get the dashboard URL for the new link
?>

<div class="admin-page-container">
    <div class="admin-sidebar">
        <div class="logo-container">
            <img src="<?php echo esc_url( $memo_logo_url ); ?>" alt="MEMO Marketing Group">
        </div>

        <nav>
            <ul>
                <li>
                    <a href="#clients" class="nav-link active" data-target="clients-section">
                        <i class="fas fa-users"></i>
                        Client Management
                    </a>
                </li>
                <li>
                    <a href="#users" class="nav-link" data-target="users-section">
                        <i class="fas fa-user-cog"></i>
                        User Management
                    </a>
                </li>
                <li>
                    <a href="#settings" class="nav-link" data-target="settings-section">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
                <li>
                    <a href="#crm-emails" class="nav-link" data-target="crm-emails-section">
                        <i class="fas fa-envelope"></i>
                        CRM Emails
                    </a>
                </li>
                <li>
                    <a href="#intelligence" class="nav-link" data-target="intelligence-section">
                        <i class="fas fa-brain"></i>
                        Client Intelligence
                    </a>
                </li>
                <li>
                    <a href="#logs" class="nav-link" data-target="logs-section">
                        <i class="fas fa-list-alt"></i>
                        Action Logs
                    </a>
                </li>
                <?php if ( !empty( $client_dashboard_url ) ) : ?>
                <li class="admin-dashboard-link">
                    <a href="<?php echo esc_url( $client_dashboard_url ); ?>" target="_blank" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        View Dashboard
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="logout-container">
            <a href="<?php echo wp_logout_url(); ?>">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
    
    <div class="admin-main-content">
        <h1>Admin Management</h1>

        <div id="clients-section" class="card section-content active">
            <h2>Client Management</h2>

            <div class="add-form-section">
                <h3>Add New Client</h3>
                <form id="add-client-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_name">Client Name</label>
                            <input type="text" id="client_name" name="client_name" required>
                        </div>
                        <div class="form-group">
                            <label for="account_id">Account ID</label>
                            <input type="text" id="account_id" name="account_id" required>
                        </div>
                        <div class="form-group">
                            <label for="logo_url">Logo URL</label>
                            <input type="url" id="logo_url" name="logo_url">
                        </div>
                        <div class="form-group">
                            <label for="webpage_url">Webpage URL</label>
                            <input type="url" id="webpage_url" name="webpage_url">
                        </div>
                        <div class="form-group">
                            <label for="crm_feed_email">CRM Feed Email</label>
                            <input type="email" id="crm_feed_email" name="crm_feed_email">
                        </div>
                        
                        <div class="form-group ai-intelligence-toggle">
                            <label for="new_ai_intelligence_enabled">
                                <input type="checkbox" 
                                       id="new_ai_intelligence_enabled" 
                                       name="ai_intelligence_enabled" 
                                       value="1"
                                       <?php checked( $intelligence_default_enabled, 'yes' ); ?>>
                                Enable AI Intelligence for this client
                            </label>
                            <small>When enabled, visitors from this client can have AI intelligence generated for enhanced insights.</small>
                        </div>
                        
                    <div id="new-client-context-group" class="form-group" style="display: none;">
                            <label for="new_client_context_info" class="context-info-label">
                                About This Client
                                <i class="fas fa-question-circle context-help-icon" 
                                title="Click for JSON template" 
                                data-target="add-context"></i>
                            </label>
                            <textarea id="new_client_context_info" 
                            name="client_context_info" 
                            rows="8" 
                            maxlength="2000" 
                            placeholder="Enter client context as JSON object..."></textarea>
                            <div style="margin-top: 8px;">
                                <button type="button" class="button button-small format-json-btn" 
                                        data-target="new_client_context_info" 
                                        style="font-size: 12px; padding: 4px 8px;">
                                    <i class="fas fa-magic"></i> Format JSON
                                </button>
                                <small style="margin-left: 10px; color: #666;">Click to clean and format your JSON</small>
                            </div>
                        <small>This information helps AI generate more relevant and targeted intelligence about visitors.</small>
                        <?php if ( $intelligence_require_context === 'yes' ): ?>
                            <small style="color: #dc3545; font-weight: 600;">Context information is required for AI-enabled clients.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-actions">
                        <button type="submit">Add Client</button>
                    </div>
                </form>
            </div>

            <div class="table-section">
                <h3>All Clients</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Account ID</th>
                                <th>Logo</th>
                                <th>Webpage URL</th>
                                <th>CRM Feed Email</th>
                                <th>Intelligence</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $all_clients ) ) : ?>
                                <?php foreach ( $all_clients as $client ) : ?>
                                    <tr data-client-id="<?php echo esc_attr( $client->id ); ?>"
                                        data-client-name="<?php echo esc_attr( wp_unslash( $client->client_name ) ); ?>"
                                        data-account-id="<?php echo esc_attr( $client->account_id ); ?>"
                                        data-logo-url="<?php echo esc_url( $client->logo_url ); ?>"
                                        data-webpage-url="<?php echo esc_url( $client->webpage_url ); ?>"
                                        data-crm-email="<?php echo esc_attr( $client->crm_feed_email ); ?>"
                                        data-ai-intelligence-enabled="<?php echo esc_attr( $client->ai_intelligence_enabled ?? 0 ); ?>"
                                        data-client-context-info="<?php echo esc_attr( $client->client_context_info ?? '' ); ?>">
                                        
                                        <td><?php echo esc_html( wp_unslash( $client->client_name ) ); ?></td>
                                        <td><?php echo esc_html( $client->account_id ); ?></td>
                                        <td>
                                            <?php if ( ! empty( $client->logo_url ) ) : ?>
                                                <img src="<?php echo esc_url( $client->logo_url ); ?>" alt="Logo" class="client-logo-thumbnail">
                                            <?php else : ?>
                                                <span class="no-logo">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ( ! empty( $client->webpage_url ) ) : ?>
                                                <a href="<?php echo esc_url( $client->webpage_url ); ?>" target="_blank" rel="noopener">
                                                    <?php echo esc_html( $client->webpage_url ); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="no-url">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $client->crm_feed_email ); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span class="ai-status-badge <?php echo $client->ai_intelligence_enabled ? 'ai-enabled' : 'ai-disabled'; ?>">
                                                    <?php echo $client->ai_intelligence_enabled ? '✓ Enabled' : '✗ Disabled'; ?>
                                                </span>
                                                <?php if ( $client->ai_intelligence_enabled && !empty( $client->client_context_info ) ): ?>
                                                    <i class="fas fa-info-circle" 
                                                       title="Has context information" 
                                                       style="color: #28a745; cursor: help;"></i>
                                                <?php elseif ( $client->ai_intelligence_enabled ): ?>
                                                    <i class="fas fa-exclamation-triangle" 
                                                       title="No context information" 
                                                       style="color: #ffc107; cursor: help;"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <button class="action-button edit-client" title="Edit Client">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-button delete-client" data-client-id="<?php echo esc_attr( $client->id ); ?>" title="Delete Client">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6" class="no-data">No clients found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
        <div id="users-section" class="card section-content">
            <h2>User Management</h2>

            <div class="add-form-section">
                <h3>Add New User</h3>
                <form id="add-user-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="new_username">Username</label>
                            <input type="text" id="new_username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="new_email">Email</label>
                            <input type="email" id="new_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Password</label>
                            <input type="password" id="new_password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_role">Role</label>
                            <select id="new_role" name="role">
                                <option value="client">Client</option>
                                <option value="administrator">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_linked_client">Link to Client Account</label>
                            <select id="new_linked_client" name="client_account_id" class="searchable-select">
                                <option value="">-- No Client Link --</option>
                                <?php foreach ( $all_client_accounts_for_dropdown as $client_option ) : ?>
                                    <option value="<?php echo esc_attr( $client_option->account_id ); ?>">
                                        <?php echo esc_html( $client_option->client_name ); ?> (<?php echo esc_html( $client_option->account_id ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit">Add User</button>
                    </div>
                </form>
            </div>

            <div class="table-section">
                <h3>All Users</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Linked Client</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $all_users ) ) : ?>
                                <?php foreach ( $all_users as $user ) :
                                    $user_client_account_id = $data_provider->get_account_id_by_user_id( $user->ID );
                                    $linked_client_name = 'N/A';
                                    if ($user_client_account_id) {
                                        $linked_client_obj = $data_provider->get_client_by_account_id($user_client_account_id);
                                        if ($linked_client_obj) {
                                            $linked_client_name = $linked_client_obj->client_name;
                                        }
                                    }
                                ?>
                                    <tr data-user-id="<?php echo esc_attr( $user->ID ); ?>"
                                        data-username="<?php echo esc_attr( $user->user_login ); ?>"
                                        data-email="<?php echo esc_attr( $user->user_email ); ?>"
                                        data-role="<?php echo esc_attr( implode(', ', $user->roles) ); ?>"
                                        data-client-account-id="<?php echo esc_attr( $user_client_account_id ); ?>">
                                        <td><?php echo esc_html( $user->user_login ); ?></td>
                                        <td><?php echo esc_html( $user->user_email ); ?></td>
                                        <td><?php echo esc_html( implode(', ', $user->roles) ); ?></td>
                                        <td><?php echo esc_html( $linked_client_name ); ?></td>
                                        <td class="actions-cell">
                                            <button class="action-button edit-user" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ( $user->ID !== get_current_user_id() ) : // Prevent deleting current user ?>
                                                <button class="action-button delete-user" data-user-id="<?php echo esc_attr( $user->ID ); ?>" title="Delete User">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="no-data">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="settings-section" class="card section-content">
            <h2>Dashboard Settings</h2>

            <div class="settings-section">
                <h3>General Settings</h3>
                <form id="settings-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <input type="hidden" name="action" value="cpd_save_general_settings">
                    <?php wp_nonce_field('cpd_save_general_settings_nonce', '_wpnonce_cpd_settings'); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_dashboard_url">Client Dashboard URL</label>
                            <input type="url" id="client_dashboard_url" name="cpd_client_dashboard_url"
                                   value="<?php echo esc_url( get_option( 'cpd_client_dashboard_url', '' ) ); ?>"
                                   placeholder="https://memomarketinggroup.com/directreach/wp-admin/admin.php?page=reports">
                            <small class="form-help">Enter the full URL of the page where you added the [campaign_dashboard] shortcode.</small>
                        </div>
                        <div class="form-group">
                            <label for="cpd_report_problem_email">Report Problem Email</label>
                            <input type="email" id="cpd_report_problem_email" name="cpd_report_problem_email"
                                   value="<?php echo esc_attr( get_option('cpd_report_problem_email', 'support@memomarketinggroup.com') ); ?>"
                                   placeholder="support@memomarketinggroup.com">
                            <small class="form-help">Email address for the "Report a Problem" button.</small>
                        </div>
                        <div class="form-group">
                            <label for="cpd_api_key_field">REST API Key</label>
                            <div class="api-key-input-group">
                                <input type="text" id="cpd_api_key_field" name="cpd_api_key"
                                       value="<?php echo esc_attr( get_option('cpd_api_key', '') ); ?>"
                                       readonly>
                                <button type="button" id="generate_api_key_button" class="button button-secondary">Generate New Key</button>
                            </div>
                            <small class="form-help">This key is used for secure API data imports (e.g., from Make.com). Regenerating will invalidate the old key.</small>
                        </div>
                        <div class="form-group">
                            <label for="default_campaign_duration">Default Campaign Duration</label>
                            <select id="default_campaign_duration" name="default_campaign_duration">
                                <option value="campaign" <?php selected( get_option('default_campaign_duration', 'campaign'), 'campaign' ); ?>>Campaign Duration</option>
                                <option value="30" <?php selected( get_option('default_campaign_duration', 'campaign'), '30' ); ?>>30 days</option>
                                <option value="7" <?php selected( get_option('default_campaign_duration', 'campaign'), '7' ); ?>>7 days</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="enable_notifications">Enable Email Notifications</label>
                            <select id="enable_notifications" name="enable_notifications">
                                <option value="yes" <?php selected( get_option('enable_notifications', 'yes'), 'yes' ); ?>>Yes</option>
                                <option value="no" <?php selected( get_option('enable_notifications', 'yes'), 'no' ); ?>>No</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cpd_crm_email_schedule_hour">Daily CRM Email Schedule Time</label>
                            <select id="cpd_crm_email_schedule_hour" name="cpd_crm_email_schedule_hour">
                                <?php
                                $scheduled_hour = get_option('cpd_crm_email_schedule_hour', '09'); // Get current setting
                                for ($i = 0; $i < 24; $i++) {
                                    $hour_24 = str_pad($i, 2, '0', STR_PAD_LEFT);
                                    $hour_12 = ( $i == 0 || $i == 12 ) ? 12 : ($i % 12);
                                    $ampm = ( $i < 12 ) ? 'am' : 'pm';
                                    printf(
                                        '<option value="%s" %s>%s %s</option>',
                                        esc_attr($hour_24),
                                        selected($scheduled_hour, $hour_24, false),
                                        esc_html($hour_12),
                                        esc_html(strtoupper($ampm))
                                    );
                                }
                                ?>
                            </select>
                            <small class="form-help">Select the hour of the day for automatic CRM email feeds.</small>
                        </div>
                            <div class="form-group">
                                <label for="cpd_webhook_url">Make.com Webhook URL</label>
                                <input type="url" id="cpd_webhook_url" name="cpd_webhook_url"
                                        value="<?php echo esc_url( get_option( 'cpd_webhook_url', '' ) ); ?>"
                                        placeholder="https://hook.us1.make.com/...">
                                <small class="form-help">Enter the Make.com webhook URL for CRM email processing.</small>
                            </div>
                            <div class="form-group">
                                <label for="cpd_makecom_api_key">Make.com API Key</label>
                                <input type="text" id="cpd_makecom_api_key" name="cpd_makecom_api_key"
                                    value="<?php echo esc_attr( get_option( 'cpd_makecom_api_key', '' ) ); ?>"
                                    placeholder="Enter your Make.com API key">
                                <small class="form-help">API key for authenticating webhook requests to Make.com.</small>
                            </div>
                    </div>
                    <hr>
                    <div class="settings-section">
                        <h3>Referrer Logo Mapping</h3>
                        <p>Configure custom logos for different referrer domains. These logos will appear in the visitor panel.</p>
                        
                        <div id="referrer-logo-mappings">
                            <?php 
                            $existing_mappings = get_option('cpd_referrer_logo_mappings', array(
                                'google.com' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Google_%22G%22_logo.svg/240px-Google_%22G%22_logo.svg.png',
                                'linkedin.com' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/ca/LinkedIn_logo_initials.png/240px-LinkedIn_logo_initials.png',
                                'bing.com' => 'https://upload.wikimedia.org/wikipedia/commons/f/f3/Bing_fluent_logo.jpg'
                            ));
                            foreach ( $existing_mappings as $domain => $logo_url ) : ?>
                            <div class="referrer-mapping-row">
                                <div class="form-group">
                                    <label>Domain</label>
                                    <input type="text" name="referrer_domains[]" value="<?php echo esc_attr( $domain ); ?>" placeholder="e.g., google.com">
                                </div>
                                <div class="form-group">
                                    <label>Logo URL</label>
                                    <input type="url" name="referrer_logos[]" value="<?php echo esc_url( $logo_url ); ?>" placeholder="https://example.com/logo.png">
                                </div>
                                <div class="form-group">
                                    <button type="button" class="button button-secondary remove-mapping">Remove</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" id="add-referrer-mapping" class="button button-secondary">Add New Mapping</button>
                        
                        <div class="form-group direct-logo-checkbox" style="margin-top: 20px;"  style="display:none;">
                            <label>
                                <input type="checkbox" name="cpd_show_direct_logo" value="1" <?php checked( get_option('cpd_show_direct_logo', 1), 1 ); ?>>
                                Show "DIRECT" logo for visitors with no referrer
                            </label>
                            <small class="form-help">When enabled, visitors with blank referrer will show a "DIRECT" text logo.</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit">Save Settings</button>
                    </div>
                </form>
            </div>                        

<!--
            <div class="settings-section">
                <h3>Data Management</h3>
                <div class="data-management-actions">
                    <div class="action-group">
                        <h4>Export Data</h4>
                        <p>Export client and campaign data for backup purposes.</p>
                        <button type="button" class="action-button-large export-data">
                            <i class="fas fa-download"></i>
                            Export All Data
                        </button>
                    </div>

                </div>
            </div>
            -->

        </div>


        <div id="crm-emails-section" class="card section-content">
            <h2>CRM Email Management</h2>

            <!-- Consolidated Client Filter at Top -->
            <div class="form-grid" style="grid-template-columns: 1fr; margin-bottom: 30px;">
                <div class="form-group">
                    <label for="crm_client_filter">Select Client Account</label>
                    <select id="crm_client_filter" class="searchable-select">
                        <option value="all">-- All Clients --</option>
                        <?php foreach ( $all_clients as $client_option ) : ?>
                            <option value="<?php echo esc_attr( $client_option->account_id ); ?>">
                                <?php echo esc_html( $client_option->client_name ); ?> (<?php echo esc_html( $client_option->account_id ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
             </div>
            <div class="add-form-section">
                <label for="trigger_on_demand_send">On-Demand CRM Email Send</label>
                <div class="form-actions">
                    <button type="button" id="trigger_on_demand_send" class="button" title="">
                        <i class="fas fa-paper-plane"></i> Send On-Demand CRM Email
                    </button>
                </div>
            </div>

            <div class="table-section">
                <h3>Eligible Visitors for CRM Email</h3>
                <div class="table-container">
                    <table class="data-table" id="eligible-visitors-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Company Name</th>
                                <th>LinkedIn URL</th>
                                <th>City</th>
                                <th>State</th>
                                <th>Zip</th>
                                <th>Last Seen At</th>
                                <th>Pages Visited</th>
                                <th>Account ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="10" class="no-data">Loading eligible visitors...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="intelligence-section" class="card section-content">
            <h2>Client Intelligence Settings</h2>
        
            <?php
            // Load intelligence classes and get current settings
            $intelligence_configured = false;
            $intelligence_stats = array();
            
            $intelligence_file = CPD_DASHBOARD_PLUGIN_DIR . 'includes/class-cpd-intelligence.php';
            if ( file_exists( $intelligence_file ) ) {
                require_once $intelligence_file;
                if ( class_exists( 'CPD_Intelligence' ) ) {
                    $intelligence = new CPD_Intelligence();
                    $intelligence_configured = $intelligence->is_intelligence_configured();
                    $intelligence_stats = $intelligence->get_intelligence_statistics();
                }
            }
            ?>
        
            <!-- Intelligence Status Dashboard -->
            <div class="intelligence-status-section" style="margin-bottom: 30px;">
                <h3>Intelligence Status</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid <?php echo $intelligence_configured ? '#28a745' : '#dc3545'; ?>;">
                        <strong>Configuration Status</strong><br>
                        <span style="color: <?php echo $intelligence_configured ? '#28a745' : '#dc3545'; ?>; font-size: 18px; font-weight: bold;">
                            <?php echo $intelligence_configured ? '✓ Configured' : '✗ Not Configured'; ?>
                        </span>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #007cba;">
                        <strong>Total Requests</strong><br>
                        <span style="color: #007cba; font-size: 18px; font-weight: bold;">
                            <?php echo isset( $intelligence_stats['total_requests'] ) ? number_format( $intelligence_stats['total_requests'] ) : '0'; ?>
                        </span>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #007cba;">
                        <strong>Today's Requests</strong><br>
                        <span style="color: #007cba; font-size: 18px; font-weight: bold;">
                            <?php echo isset( $intelligence_stats['today_requests'] ) ? number_format( $intelligence_stats['today_requests'] ) : '0'; ?>
                        </span>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #28a745;">
                        <strong>Success Rate</strong><br>
                        <span style="color: #28a745; font-size: 18px; font-weight: bold;">
                            <?php echo isset( $intelligence_stats['success_rate'] ) ? $intelligence_stats['success_rate'] . '%' : '0%'; ?>
                        </span>
                    </div>
                </div>
        
                <?php if ( isset( $intelligence_stats['by_status'] ) && ! empty( $intelligence_stats['by_status'] ) ): ?>
                <div style="margin-top: 15px;">
                    <strong>Requests by Status:</strong>
                    <div style="display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap;">
                        <?php foreach ( $intelligence_stats['by_status'] as $status => $count ): ?>
                            <span style="background: #e9ecef; padding: 8px 12px; border-radius: 4px; font-size: 13px; font-weight: 500;">
                                <?php echo esc_html( ucfirst( $status ) ) . ': ' . number_format( $count ); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        
            <!-- API Configuration Section -->
            <div class="settings-section">
                <h3>API Configuration</h3>
                <form id="intelligence-settings-form" method="post">
                    <?php wp_nonce_field( 'cpd_intelligence_settings_nonce', '_wpnonce_intelligence_settings' ); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="intelligence_webhook_url">Make.com Webhook URL</label>
                            <input type="url" 
                                   id="intelligence_webhook_url" 
                                   name="intelligence_webhook_url" 
                                   value="<?php echo esc_attr( get_option( 'cpd_intelligence_webhook_url', '' ) ); ?>" 
                                   placeholder="https://hook.integromat.com/..." 
                                   required>
                            <small>The Make.com webhook URL for processing intelligence requests.</small>
                        </div>
                        <div class="form-group">
                            <label for="makecom_api_key">Make.com API Key</label>
                            <input type="text" 
                                   id="makecom_api_key" 
                                   name="makecom_api_key" 
                                   value="<?php echo esc_attr( get_option( 'cpd_makecom_api_key', '' ) ); ?>" 
                                   placeholder="Enter your API key..." 
                                   required>
                            <small>Your Make.com API key for authentication.</small>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="intelligence_rate_limit">Rate Limit (per visitor per day)</label>
                            <input type="number" 
                                   id="intelligence_rate_limit" 
                                   name="intelligence_rate_limit" 
                                   value="<?php echo esc_attr( get_option( 'cpd_intelligence_rate_limit', 5 ) ); ?>" 
                                   min="1" 
                                   max="10" 
                                   required>
                            <small>Maximum intelligence requests per visitor per day (1-10).</small>
                        </div>
                        <div class="form-group">
                            <label for="intelligence_timeout">API Timeout (seconds)</label>
                            <input type="number" 
                                   id="intelligence_timeout" 
                                   name="intelligence_timeout" 
                                   value="<?php echo esc_attr( get_option( 'cpd_intelligence_timeout', 30 ) ); ?>" 
                                   min="10" 
                                   max="120" 
                                   required>
                            <small>API request timeout in seconds (10-120).</small>
                        </div>
                    </div>
        
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" 
                                       name="intelligence_auto_generate_crm" 
                                       value="1" 
                                       <?php checked( 1, get_option( 'cpd_intelligence_auto_generate_crm', 1 ) ); ?>>
                                Auto-generate for CRM Export
                            </label>
                            <small>Automatically generate intelligence for CRM exports.</small>
                        </div>
                        <div class="form-group">
                            <label for="intelligence_processing_method">Processing Method</label>
                            <select name="intelligence_processing_method" id="intelligence_processing_method">
                                <option value="batch" <?php selected( 'batch', get_option( 'cpd_intelligence_processing_method', 'batch' ) ); ?>>Batch Processing</option>
                                <option value="serial" <?php selected( 'serial', get_option( 'cpd_intelligence_processing_method', 'batch' ) ); ?>>Serial Processing</option>
                            </select>
                            <small>How to process multiple intelligence requests.</small>
                        </div>
                    </div>
        
                    <div class="form-actions" style="display: flex; gap: 15px; align-items: center;">
                        <button type="submit" class="button-primary">Save Intelligence Settings</button>
                        <button type="button" id="test-webhook-btn" class="button-secondary">Test Webhook</button>
                        <div id="webhook-test-result" style="margin-left: 15px;"></div>
                    </div>
                </form>
            </div>
        
            <!-- Default Client Settings Section -->
            <div class="settings-section">
                <h3>Default Client Settings</h3>
                <form id="intelligence-defaults-form" method="post">
                    <?php wp_nonce_field( 'cpd_intelligence_defaults_nonce', '_wpnonce_intelligence_defaults' ); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" 
                                       name="intelligence_default_enabled" 
                                       value="1" 
                                       <?php checked( 1, get_option( 'cpd_intelligence_default_enabled', 0 ) ); ?>>
                                Enable AI for New Clients
                            </label>
                            <small>Enable AI intelligence for new clients by default.</small>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" 
                                       name="intelligence_require_context" 
                                       value="1" 
                                       <?php checked( 1, get_option( 'cpd_intelligence_require_context', 0 ) ); ?>>
                                Require Client Context
                            </label>
                            <small>Require client context information for AI intelligence.</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="button-primary">Save Default Settings</button>
                    </div>
                </form>
            </div>
        
            <!-- Recent Intelligence Requests -->
            <?php if ( class_exists( 'CPD_Intelligence' ) && isset( $intelligence ) ): ?>
            <div class="settings-section">
                <h3>Recent Intelligence Requests</h3>
                <?php
                $recent_requests = $intelligence->get_recent_intelligence_requests( 10 );
                if ( ! empty( $recent_requests ) ):
                ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Visitor</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_requests as $request ): ?>
                            <tr>
                                <td><?php echo esc_html( $request->id ); ?></td>
                                <td><?php echo esc_html( $request->first_name . ' ' . $request->last_name ); ?></td>
                                <td><?php echo esc_html( $request->client_name ); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr( $request->status ); ?>">
                                        <?php echo esc_html( ucfirst( $request->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $request->created_at ) ) ); ?></td>
                                <td><?php echo esc_html( $request->user_name ?: 'System' ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="color: #666; font-style: italic;">No intelligence requests found.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>



        <div id="logs-section" class="card section-content">
            <h2>Action Logs</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User Name</th>
                            <th>Action Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            error_log('CPD Debug: Logs variable contains ' . count($logs) . ' entries');
                            if (!empty($logs)) {
                                error_log('CPD Debug: First log entry: ' . print_r($logs[0], true));
                            }
                        ?>
                        <?php if ( ! empty( $logs ) ) : ?>
                            <?php foreach ( $logs as $log ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $log->timestamp ); ?></td>
                                    <td><?php echo esc_html( $log->user_name ); ?></td>
                                    <td><?php echo esc_html( $log->action_type ); ?></td>
                                    <td><?php echo esc_html( $log->description ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4" class="no-data">No logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>        


        <div id="edit-client-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Client</h2>
                <form id="edit-client-form">
                    <input type="hidden" id="edit_client_id" name="client_id">
                    <div class="form-group">
                        <label for="edit_client_name">Client Name</label>
                        <input type="text" id="edit_client_name" name="client_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_account_id">Account ID (Read Only)</label>
                        <input type="text" id="edit_account_id" name="account_id" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_logo_url">Logo URL</label>
                        <input type="url" id="edit_logo_url" name="logo_url">
                    </div>
                    <div class="form-group">
                        <label for="edit_webpage_url">Webpage URL</label>
                        <input type="url" id="edit_webpage_url" name="webpage_url">
                    </div>
                    <div class="form-group">
                        <label for="edit_crm_feed_email">CRM Feed Email</label>
                        <input type="email" id="edit_crm_feed_email" name="crm_feed_email">
                    </div>
                    <div class="form-group ai-intelligence-section">
                        <h3>AI Intelligence Settings</h3>
                        <div class="ai-intelligence-toggle">
                            <label for="edit_ai_intelligence_enabled">
                                <input type="checkbox" 
                                       id="edit_ai_intelligence_enabled" 
                                       name="ai_intelligence_enabled" 
                                       value="1">
                                Enable AI Intelligence for this client
                            </label>
                            <small>When enabled, visitors from this client can have AI intelligence generated for enhanced insights.</small>
                        </div>
                        
                        <!-- ✅ NEW: Context Information Section (Initially Hidden) -->
                        <div id="edit-client-context-group" class="form-group" style="display: none; margin-top: 15px;">
                                <label for="edit_client_context_info" class="context-info-label">
                                    About This Client
                                    <i class="fas fa-question-circle context-help-icon" 
                                    title="Click for JSON template" 
                                    data-target="edit-context"></i>
                                </label>
                                <textarea id="edit_client_context_info" 
                                name="client_context_info" 
                                rows="8" 
                                maxlength="2000" 
                                placeholder="Enter client context as JSON object..."></textarea>
                            <div style="margin-top: 8px;">
                                <button type="button" class="button button-small format-json-btn" 
                                        data-target="edit_client_context_info" 
                                        style="font-size: 12px; padding: 4px 8px;">
                                    <i class="fas fa-magic"></i> Format JSON
                                </button>
                                <small style="margin-left: 10px; color: #666;">Click to clean and format your JSON</small>
                            </div>                                
                            <small>This information helps AI generate more relevant and targeted intelligence about visitors.</small>
                            <?php if ( $intelligence_require_context === 'yes' ): ?>
                                <small style="color: #dc3545; font-weight: 600;">Context information is required for AI-enabled clients.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <div class="form-actions">
                        <button type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="edit-user-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit User</h2>
                <form id="edit-user-form">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="form-group">
                        <label for="edit_user_username">Username</label>
                        <input type="text" id="edit_user_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_user_email">Email</label>
                        <input type="email" id="edit_user_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_user_role">Role</label>
                        <select id="edit_user_role" name="role">
                            <option value="client">Client</option>
                            <option value="administrator">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_linked_client">Link to Client Account</label>
                        <select id="edit_linked_client" name="client_account_id" class="searchable-select">
                            <option value="">-- No Client Link --</option>
                            <?php foreach ( $all_client_accounts_for_dropdown as $client_option ) : ?>
                                <option value="<?php echo esc_attr( $client_option->account_id ); ?>">
                                    <?php echo esc_html( $client_option->client_name ); ?> (<?php echo esc_html( $client_option->account_id ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JSON Template Help Popup -->
    <div id="json-template-popup" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 700px;">
            <span class="close">&times;</span>
            <h2>Client Context JSON Template</h2>
            <p>Copy and paste this template, then customize the values for your client:</p>
            
            <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 15px 0; font-family: monospace; font-size: 14px; position: relative;">
                <button type="button" id="copy-template-btn" style="position: absolute; top: 10px; right: 10px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">
                    Copy
                </button>
                <pre id="json-template-text" style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">{
    "templateId": "marketing_campaign_v1",
    "clientName": "Your Client Name",
    "industry": "Technology",
    "targetAudience": "Small business owners",
    "brandTone": "Professional yet friendly",
    "mainObjective": "Increase lead generation",
    "keyFeatures": "AI-powered analytics, 24/7 support",
    "budgetRange": "10k-50k",
    "timeline": "Q2 2025"
    }</pre>
            </div>
            
            <div style="background: #e8f4f8; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <h4 style="margin-top: 0; color: #0073aa;">Field Descriptions:</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>templateId:</strong> Required. Identifies which template to use (e.g., "marketing_campaign_v1")</li>
                    <li><strong>clientName:</strong> The client's company name</li>
                    <li><strong>industry:</strong> Client's business sector</li>
                    <li><strong>targetAudience:</strong> Who the client wants to reach</li>
                    <li><strong>brandTone:</strong> How the client communicates</li>
                    <li><strong>mainObjective:</strong> Primary business goal</li>
                    <li><strong>keyFeatures:</strong> Main products/services offered</li>
                    <li><strong>budgetRange:</strong> Marketing budget range</li>
                    <li><strong>timeline:</strong> Project timeline or goals</li>
                </ul>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <h4 style="margin-top: 0; color: #856404;">Important Notes:</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>All fields except <code>templateId</code> are optional</li>
                    <li>Use double quotes around all text values</li>
                    <li>Keep the structure flat (no nested objects)</li>
                    <li>Add or remove fields as needed for your client</li>
                </ul>
            </div>
            
            <div class="form-actions">
                <button type="button" id="use-template-btn" class="button-primary">Use This Template</button>
            </div>
        </div>
    </div>

</div>

