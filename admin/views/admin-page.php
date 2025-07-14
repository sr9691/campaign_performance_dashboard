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

// Variables passed from CPD_Admin::render_admin_management_page()
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
                    <a href="#crm-emails" class="nav-link" data-target="crm-email-management-section">
                        <i class="fas fa-envelope"></i>
                        CRM Emails
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
                                        data-crm-email="<?php echo esc_attr( $client->crm_feed_email ); ?>">
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
            </div>


<div id="crm-email-management-section" class="card section-content">
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
</div>

<script>
// Navigation functionality
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.section-content');

    // Determine initial active section based on hash or default to clients-section
    const initialHash = window.location.hash ? window.location.hash.substring(1) : 'clients'; // Changed default to 'clients' for consistency
    let activeSectionId = initialHash + '-section'; // Initialize with the full section ID

    // Fallback if the initial hash doesn't correspond to a valid section
    if (!document.getElementById(activeSectionId)) {
        activeSectionId = 'clients-section'; // Default to clients-section
    }

    // Function to set active section based on ID
    function setActiveSection(targetSectionId) {
        // Remove active class from all nav links and sections
        navLinks.forEach(link => link.classList.remove('active'));
        sections.forEach(section => section.classList.remove('active'));

        // Find and activate the target section
        const targetElement = document.getElementById(targetSectionId);
        if (targetElement) {
            targetElement.classList.add('active');
        }

        // Find and activate the corresponding nav link
        const targetNavLink = document.querySelector(`a[data-target="${targetSectionId}"]`);
        if (targetNavLink) {
            targetNavLink.classList.add('active');
        }

        // Update URL hash without jumping
        const cleanedHash = targetSectionId.replace('-section', '');
        if (history.pushState) {
            history.pushState(null, null, '#' + cleanedHash);
        } else {
            window.location.hash = '#' + cleanedHash;
        }

        // If CRM Emails section is active, trigger loadEligibleVisitors (assuming it's defined in cpd-dashboard.js)
        if (cleanedHash === 'crm-emails' && typeof loadEligibleVisitors === 'function') {
            loadEligibleVisitors();
        }
    }

    // Set initial active state on load
    setActiveSection(activeSectionId);

    // Add click listeners to navigation links
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetSectionId = this.getAttribute('data-target');

            // Only prevent default if it's a section navigation link (has data-target attribute and is not a dashboard link)
            if (targetSectionId && !this.classList.contains('admin-dashboard-link')) {
                e.preventDefault();
                setActiveSection(targetSectionId);
            }
        });
    });

    // Handle hash changes from browser back/forward buttons or manual hash entry
    window.addEventListener('hashchange', function() {
        const newHash = window.location.hash ? window.location.hash.substring(1) : 'clients'; // Default to 'clients'
        setActiveSection(newHash + '-section');
    });
});
</script>