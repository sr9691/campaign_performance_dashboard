/**
 * Force DOM manipulation for Campaign Performance Dashboard
 */

// Force hide WordPress admin elements immediately
document.addEventListener('DOMContentLoaded', function() {
    // Force hide admin elements
    const elementsToHide = [
        '#adminmenumain',
        '#adminmenuwrap', 
        '#adminmenuback',
        '#wpadminbar',
        '#wpfooter'
    ];
    
    elementsToHide.forEach(selector => {
        const element = document.querySelector(selector);
        if (element) {
            element.style.display = 'none';
            element.style.visibility = 'hidden';
        }
    });
    
    // Force body classes
    document.body.classList.add('cpd-dashboard-active');
    
    // Force wrapper styles
    const wpContent = document.getElementById('wpcontent');
    if (wpContent) {
        wpContent.style.marginLeft = '0';
        wpContent.style.padding = '0';
    }
    
    const wpBody = document.getElementById('wpbody');
    if (wpBody) {
        wpBody.style.backgroundColor = '#eef2f6';
        wpBody.style.padding = '0';
        wpBody.style.margin = '0';
    }
    
    const wrap = document.querySelector('.wrap');
    if (wrap) {
        wrap.style.margin = '0';
        wrap.style.padding = '0';
        wrap.style.display = 'flex';
        wrap.style.width = '100%';
        wrap.style.minHeight = '100vh';
        wrap.style.maxWidth = 'none';
    }
    
    // Debug log
    console.log('CPD Dashboard DOM manipulation complete');
    console.log('Admin page container found:', document.querySelector('.admin-page-container') ? 'Yes' : 'No');
});

/**
 * Admin-specific JavaScript for the Campaign Performance Dashboard plugin.
 */

jQuery(document).ready(function($) {
    // --- Navigation and UI Toggles ---
    const navLinks = document.querySelectorAll('.admin-sidebar nav a[data-target]');
    const sections = document.querySelectorAll('.admin-main-content');
    
    // Set the initial active state based on the URL hash.
    const initialHash = window.location.hash.substring(1);
    if (initialHash) {
        // Clear all active classes first
        navLinks.forEach(l => l.classList.remove('active'));
        sections.forEach(s => s.classList.remove('active'));

        // Add the active class to the element that matches the URL hash
        const targetSection = document.getElementById(initialHash);
        const targetLink = document.querySelector(`a[data-target="${initialHash}"]`);
        if (targetSection && targetLink) {
            targetSection.classList.add('active');
            targetLink.classList.add('active');
        }
    } else {
        // Fallback to make the first section active on initial load if no hash is present.
        const firstNavLink = document.querySelector('.admin-sidebar nav a[data-target]');
        if (firstNavLink) {
            const targetId = firstNavLink.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
            firstNavLink.classList.add('active');
        }
    }

    // Add click event listeners to the sidebar navigation links.
    navLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            
            // Remove active class from all links and sections
            navLinks.forEach(l => l.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            
            // Add active class to clicked link and target section
            const targetId = link.getAttribute('data-target');
            const targetSection = document.getElementById(targetId);
            
            if (targetSection) {
                targetSection.classList.add('active');
                link.classList.add('active');
                
                // Update URL hash without reloading the page.
                window.location.hash = link.getAttribute('href').split('#')[1];
            }
        });
    });

    // --- Chart Rendering Functionality ---
    let impressionsChartInstance = null;
    let impressionsByAdGroupChartInstance = null;

    /**
     * Renders or updates the Impressions Line Chart.
     * @param {Array} data The campaign data from the AJAX response.
     */
    function renderImpressionsChart(data) {
        const ctx = $('#impressions-chart-canvas');
        if (ctx.length === 0) return;

        const labels = data.map(item => new Date(item.last_updated).toLocaleDateString());
        const impressions = data.map(item => item.impressions);

        // Destroy the old chart instance if it exists
        if (impressionsChartInstance) {
            impressionsChartInstance.destroy();
        }

        impressionsChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Impressions',
                    data: impressions,
                    borderColor: 'rgb(66, 148, 204)', // Secondary color
                    backgroundColor: 'rgba(66, 148, 204, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { display: true, title: { display: true, text: 'Date' } },
                    y: { display: true, title: { display: true, text: 'Impressions' } }
                }
            }
        });
    }

    /**
     * Renders or updates the Impressions by Ad Group Pie Chart.
     * @param {Array} data The campaign data from the AJAX response.
     */
    function renderImpressionsByAdGroupChart(data) {
        const ctx = $('#ad-group-chart-canvas');
        if (ctx.length === 0) return;

        const labels = data.map(item => item.ad_group_name);
        const impressions = data.map(item => item.impressions);
        
        const backgroundColors = [
            '#2c435d', '#4294cc', '#a8d2e8', '#e8a8d2', '#d2e8a8', '#88a8d2', '#5d2c43' // Use a palette based on your brand colors
        ];

        // Destroy the old chart instance if it exists
        if (impressionsByAdGroupChartInstance) {
            impressionsByAdGroupChartInstance.destroy();
        }

        impressionsByAdGroupChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: impressions,
                    backgroundColor: backgroundColors,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        enabled: false // No tooltips as per the user's request
                    }
                }
            }
        });
    }

    // --- AJAX for Dashboard Filtering (Client list and date range) ---
    const dashboardContent = $('#clients-section');
    const clientList = $('.admin-sidebar .account-list');
    const dateRangeSelect = $('.duration-select select');

    // Function to load dashboard data via AJAX
    function loadDashboardData(clientId, duration) {
        dashboardContent.css('opacity', 0.5); // Show loading state

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cpd_get_dashboard_data',
                nonce: cpd_admin_ajax.nonce,
                client_id: clientId,
                duration: duration
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // 1. Update Summary Cards
                    $('.summary-card .value').each(function() {
                        const label = $(this).next('.label').text().toLowerCase().replace(/ /g, '_');
                        if (data.summary_metrics[label]) {
                            $(this).text(data.summary_metrics[label]);
                        }
                    });
                    
                    // 2. Update Charts
                    renderImpressionsChart(data.campaign_data);
                    renderImpressionsByAdGroupChart(data.campaign_data);

                    // 3. Update Ad Group Table
                    const adGroupTableBody = $('.ad-group-table tbody');
                    adGroupTableBody.empty();
                    data.campaign_data.forEach(item => {
                        adGroupTableBody.append(`
                            <tr>
                                <td>${item.ad_group_name}</td>
                                <td>${(item.impressions).toLocaleString()}</td>
                                <td>${(item.reach).toLocaleString()}</td>
                                <td>${(item.ctr).toLocaleString()}%</td>
                                <td>${(item.clicks).toLocaleString()}</td>
                            </tr>
                        `);
                    });
                } else {
                    console.error('AJAX Error:', response.data);
                }
            },
            error: function(error) {
                console.error('AJAX request failed:', error);
            },
            complete: function() {
                dashboardContent.css('opacity', 1); // Hide loading state
            }
        });
    }

    // Event listener for client list clicks (Admin sidebar)
    clientList.on('click', 'li', function() {
        const clientId = $(this).data('client-id');
        clientList.find('li').removeClass('active');
        $(this).addClass('active');
        loadDashboardData(clientId, dateRangeSelect.val());
    });

    // Event listener for date range dropdown change
    dateRangeSelect.on('change', function() {
        const clientId = clientList.find('li.active').data('client-id');
        loadDashboardData(clientId, $(this).val());
    });

    // --- AJAX for Management Forms ---
    // Handle Add Client form submission
    $('#add-client-form').on('submit', function(event) {
        event.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Adding...');
        
        const formData = form.serialize() + `&action=cpd_ajax_add_client&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Client added successfully!');
                    form[0].reset();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred during the request.');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Add Client');
            }
        });
    });

    // Handle Add User form submission
    $('#add-user-form').on('submit', function(event) {
        event.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Adding...');
        
        const formData = form.serialize() + `&action=cpd_ajax_add_user&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('User added successfully!');
                    form[0].reset();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred during the request.');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Add User');
            }
        });
    });

    // --- Delete Client via AJAX ---
    $('#clients-section .data-table').on('click', '.action-button.delete-client', function(event) {
        event.preventDefault();
        const row = $(this).closest('tr');
        const clientId = $(this).data('client-id');
        
        if (confirm('Are you sure you want to delete this client? This action cannot be undone.')) {
            $.ajax({
                url: cpd_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cpd_ajax_delete_client',
                    nonce: cpd_admin_ajax.nonce,
                    client_id: clientId
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove(); // Remove the row on success
                            alert('Client deleted successfully!');
                        });
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during the delete request.');
                }
            });
        }
    });
    
    // --- Delete User via AJAX ---
    $('#users-section .data-table').on('click', '.action-button.delete-user', function(event) {
        event.preventDefault();
        const row = $(this).closest('tr');
        const userId = $(this).data('user-id');
        
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            $.ajax({
                url: cpd_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cpd_ajax_delete_user',
                    nonce: cpd_admin_ajax.nonce,
                    user_id: userId
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove(); // Remove the row on success
                            alert('User deleted successfully!');
                        });
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during the delete request.');
                }
            });
        }
    });

    // --- Edit Client via AJAX (Modal Logic) ---
    const editClientModal = $('#edit-client-modal');
    const editClientForm = $('#edit-client-form');
    
    // Open modal and populate form when Edit button is clicked
    $('#clients-section .data-table').on('click', '.action-button.edit-client', function() {
        const row = $(this).closest('tr');
        const clientId = $(this).data('client-id');
        const clientName = row.data('client-name');
        const accountId = row.data('account-id');
        const logoUrl = row.data('logo-url');
        const webpageUrl = row.data('webpage-url');
        const crmEmail = row.data('crm-email');

        // Populate the modal form fields
        $('#edit_client_id').val(clientId);
        $('#edit_client_name').val(clientName);
        $('#edit_account_id').val(accountId); // This field is read-only
        $('#edit_logo_url').val(logoUrl);
        $('#edit_webpage_url').val(webpageUrl);
        $('#edit_crm_feed_email').val(crmEmail);

        editClientModal.fadeIn(); // Show the modal
    });

    // Handle Edit Client form submission
    editClientForm.on('submit', function(event) {
        event.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Saving...');
        
        const formData = form.serialize() + `&action=cpd_ajax_edit_client&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Client updated successfully!');
                    editClientModal.fadeOut();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred during the update request.');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Save Changes');
            }
        });
    });

    // Close modal when close button is clicked
    $('.modal .close').on('click', function() {
        $(this).closest('.modal').fadeOut();
    });

    // Close modal when clicking outside of it
    $('.modal').on('click', function(event) {
        if ($(event.target).hasClass('modal')) {
            $(this).fadeOut();
        }
    });

    // --- Edit User via AJAX (Modal Logic) ---
    const editUserModal = $('#edit-user-modal');
    const editUserForm = $('#edit-user-form');

    // Open modal and populate form when Edit button is clicked
    $('#users-section .data-table').on('click', '.action-button.edit-user', function() {
        const row = $(this).closest('tr');
        const userId = $(this).data('user-id');
        const username = row.data('username');
        const email = row.data('email');
        const role = row.data('role');
        const clientAccountId = row.data('client-account-id');

        // Populate the modal form fields
        $('#edit_user_id').val(userId);
        $('#edit_user_username').val(username);
        $('#edit_user_email').val(email);
        $('#edit_user_role').val(role);
        $('#edit_linked_client').val(clientAccountId); // This will set the value of the select
        
        editUserModal.fadeIn();
    });

    // Handle Edit User form submission
    editUserForm.on('submit', function(event) {
        event.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Saving...');
        
        const formData = form.serialize() + `&action=cpd_ajax_edit_user&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('User updated successfully!');
                    editUserModal.fadeOut();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred during the update request.');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Save Changes');
            }
        });
    });

    // --- Searchable Dropdown (Select2) Initialization ---
    // You'll need to enqueue Select2 CSS and JS in your PHP file.
    if ($.fn.select2) {
        $('.searchable-select').select2({
            dropdownParent: $('.card'),
            placeholder: 'Select a client...',
            allowClear: true
        });
    }

    // --- Final Step: Initialize dashboard data on page load ---
    // Load initial dashboard data when the admin page loads.
    const initialClientId = $('.admin-sidebar .account-list li.active').data('client-id');
    const initialDuration = dateRangeSelect.val();
    if (initialClientId) {
        loadDashboardData(initialClientId, initialDuration);
    }
});