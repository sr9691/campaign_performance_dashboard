<div class="admin-page-container">
    <div class="admin-sidebar">
        <div class="logo-container">
            <img src="<?php echo CPD_DASHBOARD_PLUGIN_URL . 'assets/images/MEMO_Logo.png'; ?>" alt="MEMO Marketing Group Logo">
        </div>
        <nav>
            <ul>
                <li><a href="?page=<?php echo esc_attr( $plugin_name ); ?>#clients" class="active" data-target="clients-section"><i class="fas fa-users-cog"></i> Client Management</a></li>
                <li><a href="?page=<?php echo esc_attr( $plugin_name ); ?>#users" data-target="users-section"><i class="fas fa-user-friends"></i> User Management</a></li>
                <li><a href="?page=<?php echo esc_attr( $plugin_name ); ?>#settings" data-target="settings-section"><i class="fas fa-cogs"></i> Settings</a></li>
                <li><a href="?page=<?php echo esc_attr( $plugin_name ); ?>#logs" data-target="logs-section"><i class="fas fa-history"></i> Activity Log</a></li>
                <li style="margin-top: auto;"><a href="<?php echo esc_url( wp_logout_url() ); ?>" onclick="alert('Logout button clicked!');"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </div>

    <div id="clients-section" class="admin-main-content active">
        <h1>Client Management</h1>

        <div class="card">
            <h2>Add New Client</h2>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="add-client-form">
                <input type="hidden" name="action" value="cpd_add_client">
                <?php wp_nonce_field( 'cpd_add_client_nonce' ); ?>
                <div class="form-grid">
                    <div class="form-group"><label for="client_name">Client Name</label><input type="text" name="client_name" id="client_name" placeholder="e.g., CleanSlate" required></div>
                    <div class="form-group"><label for="account_id">Account ID</label><input type="text" name="account_id" id="account_id" placeholder="e.g., 316578" required></div>
                    <div class="form-group"><label for="logo_url">Client Logo URL</label><input type="url" name="logo_url" id="logo_url" placeholder="https://example.com/logo.png"></div>
                    <div class="form-group"><label for="webpage_url">Webpage URL</label><input type="url" name="webpage_url" id="webpage_url" placeholder="https://example.com"></div>
                    <div class="form-group"><label for="crm_feed_email">CRM Feed Email</label><input type="email" name="crm_feed_email" id="crm_feed_email" placeholder="client@example.com (comma separated)"></div>
                    <div class="form-actions" style="grid-column: 1 / span 2; text-align: right;"><button type="submit">Add Client</button></div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Existing Clients</h2>
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Account ID</th><th>Logo</th><th>Webpage</th><th>CRM Email</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php 
                        if (!empty($all_clients)) {
                            foreach ($all_clients as $client) {
                    ?>
                    <tr data-client-id="<?php echo esc_attr($client->id); ?>" data-client-name="<?php echo esc_attr($client->client_name); ?>" data-account-id="<?php echo esc_attr($client->account_id); ?>" data-logo-url="<?php echo esc_url($client->logo_url); ?>" data-webpage-url="<?php echo esc_url($client->webpage_url); ?>" data-crm-email="<?php echo esc_attr($client->crm_feed_email); ?>">
                        <td><?php echo esc_html($client->client_name); ?></td>
                        <td><?php echo esc_html($client->account_id); ?></td>
                        <td>
                            <?php if (!empty($client->logo_url)) : ?>
                                <img src="<?php echo esc_url($client->logo_url); ?>" alt="<?php echo esc_attr($client->client_name); ?> Logo" class="client-logo-thumbnail">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><a href="<?php echo esc_url($client->webpage_url); ?>" target="_blank">View Site</a></td>
                        <td><?php echo esc_html($client->crm_feed_email); ?></td>
                        <td class="actions-cell">
                            <a href="?page=<?php echo esc_attr($plugin_name); ?>&client_id=<?php echo esc_attr($client->account_id); ?>"><button class="action-button"><i class="fas fa-chart-bar"></i></button></a>
                            <button class="action-button edit-client" data-client-id="<?php echo esc_attr($client->id); ?>"><i class="fas fa-edit"></i></button>
                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;">
                                <input type="hidden" name="action" value="cpd_delete_client">
                                <input type="hidden" name="client_id" value="<?php echo esc_attr($client->id); ?>">
                                <?php wp_nonce_field( 'cpd_delete_client_nonce' ); ?>
                                <button type="submit" class="action-button delete-client" onclick="return confirm('Are you sure you want to delete this client?');"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php } } else { ?>
                        <tr><td colspan="6">No clients added yet.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        
        <?php if ( $selected_client ) : ?>
            <h2><?php echo esc_html( $selected_client->client_name ); ?> Dashboard</h2>
            <div class="dashboard-content-wrapper">
                <div class="dashboard-header">
                    <div class="left-header">
                        <div class="client-logo-container">
                            <?php if ( ! empty( $selected_client->logo_url ) ) : ?>
                                <img src="<?php echo esc_url( $selected_client->logo_url ); ?>" alt="Client Logo" style="max-width: 100%; max-height: 100%;">
                            <?php endif; ?>
                        </div>
                        <div class="header-title-section">
                            <h1>Digital Marketing Report</h1>
                            <div class="duration-select">
                                <span>Campaign Duration:</span>
                                <select>
                                    <option value="Campaign Duration">Campaign Duration</option>
                                    <option value="30 days">30 days</option>
                                    <option value="7 days">7 days</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="summary-cards">
                    <div class="summary-card"><p class="value"><?php echo esc_html( $summary_metrics['impressions'] ); ?></p><p class="label">Impressions</p></div>
                    <div class="summary-card"><p class="value"><?php echo esc_html( $summary_metrics['reach'] ); ?></p><p class="label">Reach</p></div>
                    <div class="summary-card"><p class="value"><?php echo esc_html( $summary_metrics['ctr'] ); ?></p><p class="label">CTR</p></div>
                    <div class="summary-card"><p class="value"><?php echo esc_html( $summary_metrics['new_contacts'] ); ?></p><p class="label">New Contacts</p></div>
                    <div class="summary-card"><p class="value"><?php echo esc_html( $summary_metrics['crm_additions'] ); ?></p><p class="label">CRM Additions</p></div>
                </div>
                <div class="charts-section">
                    <div class="chart-container" style="flex: 2;"><h3>Impressions Chart</h3><canvas id="impressions-chart-canvas"></canvas></div>
                    <div class="chart-container" style="flex: 1;"><h3>Impressions by Ad Group</h3><canvas id="ad-group-chart-canvas"></canvas></div>
                </div>
                <div class="ad-group-table">
                    <table class="data-table">
                        <thead><tr><th>Ad Group Name</th><th>Impressions</th><th>Reach</th><th>CTR</th><th>Clicks</th></tr></thead>
                        <tbody>
                            <?php foreach ( $campaign_data as $ad_group ) : ?>
                                <tr><td><?php echo esc_html( $ad_group->ad_group_name ); ?></td><td><?php echo esc_html( number_format( $ad_group->impressions ) ); ?></td><td><?php echo esc_html( number_format( $ad_group->reach ) ); ?></td><td><?php echo esc_html( number_format( $ad_group->clicks ) ); ?></td><td><?php echo esc_html( number_format( $ad_group->ctr, 2 ) ); ?>%</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
            </div>
        <?php endif; ?>
    </div>

    <div id="users-section" class="admin-main-content">
        <h1>User Management</h1>
        <div class="card">
            <h2>Add New User</h2>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="add-user-form">
                <input type="hidden" name="action" value="cpd_add_user">
                <?php wp_nonce_field( 'cpd_add_user_nonce' ); ?>
                <div class="form-grid">
                    <div class="form-group"><label for="user_username">Username</label><input type="text" name="username" id="user_username" placeholder="e.g., jsmith" required></div>
                    <div class="form-group"><label for="user_email">Email</label><input type="email" name="email" id="user_email" placeholder="user@example.com" required></div>
                    <div class="form-group"><label for="user_password">Password</label><input type="password" name="password" id="user_password" placeholder="********" required></div>
                    <div class="form-group"><label for="user_role">Role</label><select name="role" id="user_role"><option value="client" selected>Client</option><option value="admin">Administrator</option></select></div>
                    <div class="form-group">
                        <label for="linked_client">Link to Client</label>
                        <select name="client_account_id" id="linked_client" class="searchable-select">
                            <option value="">Select a client...</option>
                            <?php foreach ($all_clients as $client_option) : ?>
                                <option value="<?php echo esc_attr($client_option->account_id); ?>"><?php echo esc_html($client_option->client_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions"><button type="submit">Add User</button></div>
                </div>
            </form>
        </div>
        <div class="card">
            <h2>Existing Users</h2>
            <table class="data-table">
                <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Linked Client(s)</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php 
                        $users = get_users();
                        $data_provider = new CPD_Data_Provider();
                        foreach ($users as $user) {
                            $user_roles = $user->roles; $user_role = !empty($user_roles) ? array_shift($user_roles) : 'none';
                            $linked_client_name = 'None';
                            $account_id = $data_provider->get_account_id_by_user_id($user->ID);
                            
                            // --- Add this safety check ---
                            if ($account_id) {
                                $client = $data_provider->get_client_by_account_id($account_id);
                                $linked_client_name = $client ? esc_html($client->client_name) : 'N/A (Client Not Found)';
                            }

                            if ($user_role == 'administrator') { $linked_client_name = 'All Clients'; }
                    ?>
                    <tr data-user-id="<?php echo esc_attr($user->ID); ?>" data-username="<?php echo esc_attr($user->user_login); ?>" data-email="<?php echo esc_attr($user->user_email); ?>" data-role="<?php echo esc_attr($user_role); ?>" data-client-account-id="<?php echo esc_attr($account_id); ?>">
                        <td><?php echo esc_html($user->user_login); ?></td><td><?php echo esc_html($user->user_email); ?></td><td><?php echo esc_html(ucfirst($user_role)); ?></td><td><?php echo esc_html($linked_client_name); ?></td>
                        <td class="actions-cell">
                            <button class="action-button edit-user" data-user-id="<?php echo esc_attr($user->ID); ?>"><i class="fas fa-edit"></i></button>
                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;">
                                <input type="hidden" name="action" value="cpd_delete_user">
                                <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                                <?php wp_nonce_field( 'cpd_delete_user_nonce' ); ?>
                                <button type="submit" class="action-button delete-user" onclick="return confirm('Are you sure you want to delete this user?');"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="settings-section" class="admin-main-content"><h1>Dashboard Settings</h1><p>This is a placeholder for the settings page content.</p></div>
    <div id="logs-section" class="admin-main-content"><h1>Activity Log</h1><div class="card"><h2>Recent Activities</h2><table class="data-table"><thead><tr><th>Timestamp</th><th>User</th><th>Action Type</th><th>Description</th></tr></thead><tbody>
        <?php if ( ! empty( $logs ) ) : ?><?php foreach ( $logs as $log ) : $user_info = get_user_by( 'id', $log->user_id ); $username = $user_info ? esc_html( $user_info->user_login ) : 'System/Unknown'; ?><tr><td><?php echo esc_html( $log->timestamp ); ?></td><td><?php echo esc_html( $username ); ?></td><td><?php echo esc_html( $log->action_type ); ?></td><td><?php echo esc_html( $log->description ); ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="4">No log entries found.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>

<div id="edit-client-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div class="modal-content card" style="width:500px;">
        <span class="close" style="float:right; font-size:24px; cursor:pointer;">&times;</span>
        <h2>Edit Client</h2>
        <form id="edit-client-form">
            <input type="hidden" name="client_id" id="edit_client_id">
            <div class="form-group"><label for="edit_client_name">Client Name</label><input type="text" name="client_name" id="edit_client_name" required></div>
            <div class="form-group"><label for="edit_account_id">Account ID</label><input type="text" name="account_id" id="edit_account_id" required readonly></div>
            <div class="form-group"><label for="edit_logo_url">Client Logo URL</label><input type="url" name="logo_url" id="edit_logo_url"></div>
            <div class="form-group"><label for="edit_webpage_url">Webpage URL</label><input type="url" name="webpage_url" id="edit_webpage_url"></div>
            <div class="form-group"><label for="edit_crm_feed_email">CRM Feed Email</label><input type="email" name="crm_feed_email" id="edit_crm_feed_email" placeholder="comma separated"></div>
            <div class="form-actions" style="grid-column: 1 / span 2; text-align: right;"><button type="submit">Save Changes</button></div>
        </form>
    </div>
</div>

<div id="edit-user-modal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div class="modal-content card" style="width:500px;">
        <span class="close" style="float:right; font-size:24px; cursor:pointer;">&times;</span>
        <h2>Edit User</h2>
        <form id="edit-user-form">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group"><label for="edit_user_username">Username</label><input type="text" name="username" id="edit_user_username" required></div>
            <div class="form-group"><label for="edit_user_email">Email</label><input type="email" name="email" id="edit_user_email" required></div>
            <div class="form-group"><label for="edit_user_role">Role</label><select name="role" id="edit_user_role"><option value="client">Client</option><option value="administrator">Administrator</option></select></div>
            <div class="form-group">
                <label for="edit_linked_client">Link to Client</label>
                <select name="client_account_id" id="edit_linked_client" class="searchable-select">
                    <option value="">Select a client...</option>
                    <?php foreach ($all_clients as $client_option) : ?>
                        <option value="<?php echo esc_attr($client_option->account_id); ?>"><?php echo esc_html($client_option->client_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions" style="grid-column: 1 / span 2; text-align: right;"><button type="submit">Save Changes</button></div>
        </form>
    </div>
</div>
<script src="<?php echo CPD_DASHBOARD_PLUGIN_URL . 'assets/js/cpd-dashboard.js'; ?>" id="cpd-dashboard-admin-js"></script>