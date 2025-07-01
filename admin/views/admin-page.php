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
?>

<div class="admin-page-container">
    <!-- LEFT SIDEBAR - Navigation Panel -->
    <div class="admin-sidebar">
        <div class="logo-container">
            <img src="<?php echo esc_url( $memo_logo_url ); ?>" alt="MEMO Marketing Group">
        </div>
        
        <nav>
            <ul>
                <li>
                    <a href="#clients-section" class="nav-link active" data-section="clients">
                        <i class="fas fa-users"></i>
                        Client Management
                    </a>
                </li>
                <li>
                    <a href="#users-section" class="nav-link" data-section="users">
                        <i class="fas fa-user-cog"></i>
                        User Management
                    </a>
                </li>
                <li>
                    <a href="#settings-section" class="nav-link" data-section="settings">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
                <li>
                    <a href="#logs-section" class="nav-link" data-section="logs">
                        <i class="fas fa-list-alt"></i>
                        Action Logs
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="logout-container">
            <a href="<?php echo wp_logout_url(); ?>">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="admin-main-content">
        <h1>Admin Management</h1>

        <!-- Clients Section -->
        <div id="clients-section" class="card section-content active">
            <h2>Client Management</h2>
            
            <!-- Add New Client Form -->
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

            <!-- All Clients Table -->
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
                                        data-client-name="<?php echo esc_attr( $client->client_name ); ?>"
                                        data-account-id="<?php echo esc_attr( $client->account_id ); ?>"
                                        data-logo-url="<?php echo esc_url( $client->logo_url ); ?>"
                                        data-webpage-url="<?php echo esc_url( $client->webpage_url ); ?>"
                                        data-crm-email="<?php echo esc_attr( $client->crm_feed_email ); ?>">
                                        <td><?php echo esc_html( $client->client_name ); ?></td>
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

        <!-- Users Section -->
        <div id="users-section" class="card section-content">
            <h2>User Management</h2>
            
            <!-- Add New User Form -->
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

            <!-- All Users Table -->
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

        <!-- Settings Section -->
        <div id="settings-section" class="card section-content">
            <h2>Dashboard Settings</h2>
            
            <div class="settings-section">
                <h3>General Settings</h3>
                <form id="settings-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_dashboard_url">Client Dashboard URL</label>
                            <input type="url" id="client_dashboard_url" name="client_dashboard_url" 
                                   value="<?php echo esc_url( get_option( 'cpd_client_dashboard_url', '' ) ); ?>" 
                                   placeholder="https://yoursite.com/dashboard">
                            <small class="form-help">Enter the full URL of the page where you added the [campaign_dashboard] shortcode.</small>
                        </div>
                        <div class="form-group">
                            <label for="default_campaign_duration">Default Campaign Duration</label>
                            <select id="default_campaign_duration" name="default_campaign_duration">
                                <option value="campaign">Campaign Duration</option>
                                <option value="30">30 days</option>
                                <option value="7">7 days</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dashboard_theme">Dashboard Theme</label>
                            <select id="dashboard_theme" name="dashboard_theme">
                                <option value="default">Default Theme</option>
                                <option value="dark">Dark Theme</option>
                                <option value="light">Light Theme</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="enable_notifications">Enable Email Notifications</label>
                            <select id="enable_notifications" name="enable_notifications">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
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
                    <div class="action-group">
                        <h4>Clear Cache</h4>
                        <p>Clear dashboard cache to refresh data and improve performance.</p>
                        <button type="button" class="action-button-large clear-cache">
                            <i class="fas fa-refresh"></i>
                            Clear Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Logs Section -->
        <div id="logs-section" class="card section-content">
            <h2>Action Logs</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User ID</th>
                            <th>Action Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $logs ) ) : ?>
                            <?php foreach ( $logs as $log ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $log->timestamp ); ?></td>
                                    <td><?php echo esc_html( $log->user_id ); ?></td>
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

        <!-- Modals for Edit Client and Edit User -->
        <!-- Edit Client Modal -->
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

        <!-- Edit User Modal -->
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
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetSection = this.getAttribute('data-section');
            
            if (targetSection) {
                e.preventDefault();
                
                // Remove active class from all nav links and sections
                navLinks.forEach(nav => nav.classList.remove('active'));
                sections.forEach(section => section.classList.remove('active'));
                
                // Add active class to clicked nav link
                this.classList.add('active');
                
                // Show target section
                const targetElement = document.getElementById(targetSection + '-section');
                if (targetElement) {
                    targetElement.classList.add('active');
                }
            }
        });
    });
});
</script>