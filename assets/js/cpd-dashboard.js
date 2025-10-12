/**
 * Admin-specific JavaScript for the Campaign Performance Dashboard plugin.
 * OPTIMIZED VERSION with performance improvements and premium tier support
 */

// Global state management for performance optimization
(function() {
    const timestamp = Date.now();
    const globalKey = 'CPD_Dashboard_' + timestamp;
    
    if (typeof window.CPD_Dashboard_INITIALIZED === "undefined") {
        window.CPD_Dashboard_INITIALIZED = true;
        
        window.CPD_Dashboard = {
            initialized: false,
            loadingStates: new Set(),
            requestQueue: new Map(),
            debounceTimers: {},
            lastLoadParams: null,
            timestamp: timestamp,
            
            // Debounce utility
            debounce: function(key, func, delay) {
                if (this.debounceTimers[key]) {
                    clearTimeout(this.debounceTimers[key]);
                }
                this.debounceTimers[key] = setTimeout(func, delay);
            },

            // Check if request is already in progress
            isLoading: function(key) {
                return this.loadingStates.has(key);
            },

            // Mark request as loading
            setLoading: function(key, isLoading) {
                if (isLoading) {
                    this.loadingStates.add(key);
                } else {
                    this.loadingStates.delete(key);
                }
            },

            // Generate request key for deduplication
            getRequestKey: function(clientId, duration, action) {
                return `${action}_${clientId || 'all'}_${duration || 'default'}`;
            },

            // Check if this is a duplicate request
            isDuplicateRequest: function(clientId, duration) {
                const currentParams = `${clientId || 'all'}_${duration || 'default'}`;
                if (this.lastLoadParams === currentParams) {
                    return true;
                }
                this.lastLoadParams = currentParams;
                return false;
            }
        };
        
        console.log('CPD_Dashboard initialized with timestamp:', timestamp);
    } else {
        console.log('CPD_Dashboard already initialized, reusing existing instance');
    }
})();

// Use the global reference
const CPD_Dashboard = window.CPD_Dashboard;

if (typeof window.cpdAdminInitialized === "undefined") {
  window.cpdAdminInitialized = true;

  jQuery(document).ready(function ($) {

    // Access localized data
    const localizedPublicData =
      typeof cpd_dashboard_data !== "undefined" ? cpd_dashboard_data : {};
    const adminAjaxData =
      typeof cpd_admin_ajax !== "undefined" ? cpd_admin_ajax : {};

    const elementsToHide = [
      "#adminmenumain",
      "#adminmenuwrap",
      "#adminmenuback",
      "#wpadminbar",
      "#wpfooter",
    ];

    elementsToHide.forEach((selector) => {
      const element = document.querySelector(selector);
      if (element) {
        element.style.display = "none";
        element.style.visibility = "hidden";
      }
    });

    document.body.classList.add("cpd-dashboard-active");

    const wpContent = document.getElementById("wpcontent");
    if (wpContent) {
      wpContent.style.marginLeft = "0";
      wpContent.style.padding = "0";
    }

    const wpBody = document.getElementById("wpbody");
    if (wpBody) {
      wpBody.style.backgroundColor = "#eef2f6";
      wpBody.style.padding = "0";
      wpBody.style.margin = "0";
    }

    const wrap = document.querySelector(".wrap");
    if (wrap) {
      wrap.style.margin = "0";
      wrap.style.padding = "0";
      wrap.style.display = "flex";
      wrap.style.width = "100%";
      wrap.style.minHeight = "100vh";
      wrap.style.maxWidth = "none";
    }

    const dashboardContent = $("#clients-section");
    const clientList = $(".account-list");
    const dateRangeSelect = $(".duration-select select");

    // Client Context JSON validation function
    function validateClientContextJSON(contextText) {
        if (!contextText.trim()) {
            return { valid: true, error: null };
        }
        
        let cleanText = contextText.trim();
        cleanText = cleanText.replace(/^\uFEFF/, '');
        cleanText = cleanText.replace(/[\u201C\u201D]/g, '"');
        cleanText = cleanText.replace(/[\u2018\u2019]/g, "'");
        
        try {
            const parsed = JSON.parse(cleanText);
            
            if (typeof parsed !== 'object' || Array.isArray(parsed)) {
                return { valid: false, error: 'Context must be a JSON object' };
            }
            
            if (!parsed.templateId || typeof parsed.templateId !== 'string') {
                return { valid: false, error: 'templateId is required and must be a string' };
            }
            
            for (const [key, value] of Object.entries(parsed)) {
                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                    return { valid: false, error: 'Context structure should be flat. Nested objects are not allowed except for arrays.' };
                }
            }
            
            return { valid: true, error: null, parsed: parsed };
        } catch (e) {
            let errorMessage = 'Invalid JSON format: ';
            if (e.message.includes('Unexpected token')) {
                errorMessage += 'Check for missing commas, quotes, or brackets. ';
            }
            if (e.message.includes('position')) {
                const position = e.message.match(/position (\d+)/);
                if (position) {
                    const pos = parseInt(position[1]);
                    const contextSnippet = cleanText.substring(Math.max(0, pos - 10), pos + 10);
                    errorMessage += `Error near: "${contextSnippet}"`;
                }
            } else {
                errorMessage += e.message;
            }
            
            return { valid: false, error: errorMessage };
        }
    }

    /**
     * Handle subscription tier changes (conditional RTR enabling)
     */
    function setupPremiumTierHandlers() {
        // Add client form
        $('#add-subscription-tier').on('change', function() {
            handleTierChange($(this).val(), '#add-rtr-enabled', false);
        });
        
        // Edit client form
        $('#edit-subscription-tier').on('change', function() {
            handleTierChange($(this).val(), '#edit-rtr-enabled', true);
        });
        
        // Initial state on page load
        if ($('#add-subscription-tier').length) {
            handleTierChange($('#add-subscription-tier').val(), '#add-rtr-enabled', false);
        }
    }
    
    /**
     * Handle tier change logic
     */
    function handleTierChange(tier, checkboxSelector, isEdit) {
        const $checkbox = $(checkboxSelector);
        if (!$checkbox.length) return;
        
        const $form = $checkbox.closest('form');
        const $rtrHelp = $form.find('.rtr-help');
        const $rtrDisabledHelp = $form.find('.rtr-disabled-help');
        
        if (tier === 'premium') {
            $checkbox.prop('disabled', false);
            $rtrHelp.show();
            $rtrDisabledHelp.hide();
        } else {
            $checkbox.prop('disabled', true);
            $checkbox.prop('checked', false);
            $rtrHelp.hide();
            $rtrDisabledHelp.show();
        }
    }

    /**
     * Helper function to escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Function to refresh client table with premium badges
    function refreshClientTable() {
      console.log("cpd-dashboard.js: Refreshing client management table...");

      $.ajax({
        url: cpd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "cpd_get_clients",
          nonce: cpd_admin_ajax.nonce,
        },
        success: function (response) {
          if (response.success && response.data.clients) {
            const clientTableBody = $("#clients-section .data-table tbody");
            clientTableBody.empty();

            if (response.data.clients.length > 0) {
              response.data.clients.forEach((client) => {
                const logoHtml = client.logo_url
                  ? `<img src="${escapeHtml(client.logo_url)}" alt="Logo" class="client-logo-thumbnail">`
                  : '<span class="no-logo">N/A</span>';

                const webpageHtml = client.webpage_url
                  ? `<a href="${escapeHtml(client.webpage_url)}" target="_blank" rel="noopener">${escapeHtml(client.webpage_url)}</a>`
                  : '<span class="no-url">N/A</span>';

                // AI intelligence status
                const isAIEnabled = [1, '1', true, 'true'].includes(client.ai_intelligence_enabled);
                const aiStatusBadge = isAIEnabled
                  ? '<span class="ai-status-badge ai-enabled">✓ Enabled</span>'
                  : '<span class="ai-status-badge ai-disabled">✗ Disabled</span>';

                const hasContextContent = client.client_context_info && client.client_context_info.toString().trim() !== '';
                const aiContextIcon = isAIEnabled && hasContextContent
                  ? '<i class="fas fa-info-circle" title="Has context information" style="color: #28a745; cursor: help;"></i>'
                  : isAIEnabled
                  ? '<i class="fas fa-exclamation-triangle" title="No context information" style="color: #ffc107; cursor: help;"></i>'
                  : "";

                // Premium tier badge
                let premiumBadge = '';
                if (client.subscription_tier === 'premium') {
                    const rtrStatus = client.rtr_enabled == 1 ? 
                        '<span style="color: #27ae60; font-size: 12px; margin-left: 6px;">● RTR Active</span>' : 
                        '<span style="color: #999; font-size: 12px; margin-left: 6px;">○ RTR Inactive</span>';
                    premiumBadge = `
                        <span class="premium-badge" style="
                            display: inline-block;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            padding: 2px 8px;
                            border-radius: 12px;
                            font-size: 11px;
                            font-weight: 600;
                            margin-left: 8px;
                            vertical-align: middle;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
                        ">PREMIUM</span>
                        ${rtrStatus}
                    `;
                }

                clientTableBody.append(`
                    <tr data-client-id="${escapeHtml(client.id)}"
                        data-client-name="${escapeHtml(client.client_name)}"
                        data-account-id="${escapeHtml(client.account_id)}"
                        data-logo-url="${escapeHtml(client.logo_url || "")}"
                        data-webpage-url="${escapeHtml(client.webpage_url || "")}"
                        data-crm-email="${escapeHtml(client.crm_feed_email || "")}"
                        data-ai-intelligence-enabled="${escapeHtml(client.ai_intelligence_enabled || 0)}"
                        data-client-context-info="${escapeHtml(client.client_context_info || "")}"
                        data-subscription-tier="${escapeHtml(client.subscription_tier || 'basic')}"
                        data-rtr-enabled="${escapeHtml(client.rtr_enabled || 0)}"
                        data-rtr-activated-at="${escapeHtml(client.rtr_activated_at || "")}"
                        data-subscription-expires-at="${escapeHtml(client.subscription_expires_at || "")}">
                        <td>${escapeHtml(client.client_name)}${premiumBadge}</td>
                        <td>${escapeHtml(client.account_id)}</td>
                        <td>${logoHtml}</td>
                        <td>${webpageHtml}</td>
                        <td>${escapeHtml(client.crm_feed_email || "")}</td>
                        <td><div style="display: flex; align-items: center; gap: 10px;">${aiStatusBadge}${aiContextIcon}</div></td>
                        <td class="actions-cell">
                            <button class="action-button edit-client" title="Edit Client">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-button delete-client" data-client-id="${escapeHtml(client.id)}" title="Delete Client">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `);
              });
            } else {
              clientTableBody.append(
                '<tr><td colspan="7" class="no-data">No clients found.</td></tr>'
              );
            }
          }
        },
        error: function () {
          console.error("Failed to refresh client table");
        },
      });
    }

    // Function to refresh user table
    function refreshUserTable() {
      $.ajax({
        url: cpd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "cpd_get_users",
          nonce: cpd_admin_ajax.nonce,
        },
        success: function (response) {
          if (response.success && response.data.users) {
            const userTableBody = $("#users-section .data-table tbody");
            userTableBody.empty();

            if (response.data.users.length > 0) {
              response.data.users.forEach((user) => {
                const linkedClientName = user.linked_client_name || "N/A";
                const deleteButton = user.can_delete
                  ? `<button class="action-button delete-user" data-user-id="${user.ID}" title="Delete User">
                        <i class="fas fa-trash-alt"></i>
                    </button>`
                  : "";

                userTableBody.append(`
                    <tr data-user-id="${user.ID}"
                        data-username="${escapeHtml(user.user_login)}"
                        data-email="${escapeHtml(user.user_email)}"
                        data-role="${escapeHtml(user.roles.join(", "))}"
                        data-client-account-id="${escapeHtml(user.client_account_id || "")}">
                        <td>${escapeHtml(user.user_login)}</td>
                        <td>${escapeHtml(user.user_email)}</td>
                        <td>${escapeHtml(user.roles.join(", "))}</td>
                        <td>${escapeHtml(linkedClientName)}</td>
                        <td class="actions-cell">
                            <button class="action-button edit-user" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>
                            ${deleteButton}
                        </td>
                    </tr>
                `);
              });
            } else {
              userTableBody.append(
                '<tr><td colspan="5" class="no-data">No users found.</td></tr>'
              );
            }
          }
        },
        error: function () {
          console.error("Failed to refresh user table");
        },
      });
    }

    // Function to load dashboard data via AJAX with deduplication
    function loadDashboardData(clientId, duration) {
      if (CPD_Dashboard.isDuplicateRequest(clientId, duration)) {
        return;
      }

      const requestKey = CPD_Dashboard.getRequestKey(clientId, duration, 'dashboard');
      
      if (CPD_Dashboard.isLoading(requestKey)) {
        return;
      }

      if (dashboardContent.length === 0) {
        console.warn(
          "Dashboard content container (#clients-section) not found. Cannot load dashboard data."
        );
        return;
      }

      CPD_Dashboard.setLoading(requestKey, true);
      dashboardContent.css("opacity", 0.5);

      const ajaxData = {
        action: "cpd_get_dashboard_data",
        nonce: cpd_admin_ajax.nonce,
        client_id: clientId === "all" ? null : clientId,
        duration: duration,
      };

      $.ajax({
        url: cpd_admin_ajax.ajax_url,
        type: "POST",
        data: ajaxData,
        timeout: 30000,
        success: function (response) {
          if (response.success && response.data) {
            const hotListKey = CPD_Dashboard.getRequestKey(clientId, duration, 'hotlist');
            if (!CPD_Dashboard.isLoading(hotListKey)) {
              CPD_Dashboard.debounce('hotListLoad', () => {
                loadHotListData(clientId, duration);
              }, 100);
            }
          } else {
            console.error('CPD Dashboard: Invalid response format:', response);
          }
        },
        error: function (jqXHR, textStatus, errorThrown) {
          console.error(
            "loadDashboardData: AJAX request failed. Status:",
            textStatus,
            "Error:",
            errorThrown
          );
        },
        complete: function () {
          CPD_Dashboard.setLoading(requestKey, false);
          dashboardContent.css("opacity", 1);
        },
      });
    }

    // Separate hot list loading function
    function loadHotListData(clientId, duration) {
      const requestKey = CPD_Dashboard.getRequestKey(clientId, duration, 'hotlist');
      
      if (CPD_Dashboard.isLoading(requestKey)) {
        return;
      }

      CPD_Dashboard.setLoading(requestKey, true);

      const ajaxData = {
        action: 'cpd_get_hot_list_data',
        client_id: clientId || '',
        nonce: cpd_admin_ajax.nonce || localizedPublicData.visitor_nonce
      };

      $.ajax({
        url: cpd_admin_ajax.ajax_url || localizedPublicData.ajax_url,
        type: 'POST',
        data: ajaxData,
        timeout: 30000,
        success: function(response) {
          if (response.success && response.data) {
            updateHotListSection(response.data);
          } else {
            console.error('CPD Hot List: Invalid response format:', response);
          }
        },
        error: function(xhr, status, error) {
          console.error('CPD Hot List: AJAX error:', error);
          updateHotListSection({ has_settings: false, hot_visitors: [] });
        },
        complete: function() {
          CPD_Dashboard.setLoading(requestKey, false);
        }
      });
    }

    // Update hot list section
    function updateHotListSection(data) {
      const $hotListPanel = $('.hot-list-panel');
      
      if (!$hotListPanel.length) {
        return;
      }

      if (data.has_settings && data.hot_visitors && data.hot_visitors.length > 0) {
        renderHotListVisitors(data.hot_visitors, data.criteria_summary);
        $hotListPanel.show();
      } else {
        $hotListPanel.hide();
      }
    }

    // Render hot list visitors
    function renderHotListVisitors(visitors, criteria) {
      const $hotListContent = $('.hot-list-content');
      
      if (!$hotListContent.length) {
        return;
      }

      $hotListContent.empty();

      if (criteria) {
        const criteriaHtml = `
          <div class="hot-list-criteria">
            <h4>Hot List Criteria</h4>
            <p>Requires ${criteria.required_matches} of ${criteria.active_filters} active filters</p>
            <ul>
              ${criteria.criteria.map(c => `<li>${escapeHtml(c)}</li>`).join('')}
            </ul>
          </div>
        `;
        $hotListContent.append(criteriaHtml);
      }

      visitors.forEach(visitor => {
        const visitorHtml = renderVisitorCard(visitor, true);
        $hotListContent.append(visitorHtml);
      });
    }

    // Helper function to render visitor cards
    function renderVisitorCard(visitor, isHotList = false) {
      return `<div class="visitor-card" data-visitor-id="${visitor.id}">
        <div class="visitor-info">${escapeHtml(visitor.company_name || 'Unknown Company')}</div>
      </div>`;
    }

    // ========================================
    // PAGE TYPE DETECTION AND INITIALIZATION
    // ========================================

    const isAdminPage = document.body.classList.contains(
      "campaign-dashboard_page_cpd-dashboard-management"
    );

    if (isAdminPage) {
      // Admin page initialization
      window.addEventListener("load", function () {
        setTimeout(function () {
          const navLinks = document.querySelectorAll(
            ".admin-sidebar nav a[data-target]"
          );
          const sections = document.querySelectorAll(
            ".admin-main-content .section-content"
          );

          if (navLinks.length === 0 || sections.length === 0) {
            console.error(
              "cpd-dashboard.js: Navigation links or sections not found. Check admin-page.php structure."
            );
            return;
          }

          const defaultSectionId = "clients-section";

          function setActiveSection() {
            const sections = document.querySelectorAll(
              ".admin-main-content .section-content"
            );
            const navLinks = document.querySelectorAll(
              ".admin-sidebar nav a[data-target]"
            );
            const defaultSectionId = "clients-section";

            let targetHashId = window.location.hash.substring(1);
            if (!targetHashId) {
              targetHashId = defaultSectionId.replace("-section", "");
            }

            sections.forEach((s) => {
              if (s) {
                s.classList.remove("active");
                s.style.display = "none";
              }
            });

            navLinks.forEach((link) => {
              link.classList.remove("active");
            });

            const targetSection = document.getElementById(
              targetHashId + "-section"
            );
            const targetLink = document.querySelector(
              `.admin-sidebar nav a[data-target="${targetHashId}-section"]`
            );

            if (targetSection && targetLink) {
              targetSection.classList.add("active");
              targetSection.style.display = "block";
              targetLink.classList.add("active");
            } else {
              console.warn(
                "setActiveSection: Fallback to default"
              );
              const fallbackSection = document.getElementById(defaultSectionId);
              const fallbackLink = document.querySelector(
                `.admin-sidebar nav a[data-target="${defaultSectionId}"]`
              );
              if (fallbackSection) {
                fallbackSection.classList.add("active");
                fallbackSection.style.display = "block";
              }
              if (fallbackLink) {
                fallbackLink.classList.add("active");
              }
              window.location.hash = defaultSectionId.replace("-section", "");
            }
          }

          setActiveSection();

          navLinks.forEach((link) => {
            link.addEventListener("click", (event) => {
              if (link.getAttribute("target") === "_blank") {
                return;
              }

              event.preventDefault();

              const targetId = link.getAttribute("data-target");
              const cleanedTargetId = targetId.replace("-section", "");

              if (history.pushState) {
                history.pushState(null, null, "#" + cleanedTargetId);
              } else {
                window.location.hash = "#" + cleanedTargetId;
              }

              setActiveSection();

              if (cleanedTargetId === "crm-emails") {
                if (typeof loadEligibleVisitors === "function") {
                  loadEligibleVisitors();
                } else {
                  console.warn("loadEligibleVisitors function not available");
                }
              }
            });
          });

          window.addEventListener("hashchange", setActiveSection);
        }, 100);
      });

      // ========================================
      // ADMIN FORM HANDLERS
      // ========================================

      // JSON Formatter/Cleaner functionality
      $(document).on('click', '.format-json-btn', function() {
          const targetId = $(this).data('target');
          const textarea = $('#' + targetId);
          const rawJson = textarea.val();
          
          if (!rawJson.trim()) {
              alert('Please enter some JSON text first.');
              return;
          }
          
          try {
              let cleanText = rawJson.trim();
              cleanText = cleanText.replace(/^\uFEFF/, '');
              cleanText = cleanText.replace(/[\u201C\u201D]/g, '"');
              cleanText = cleanText.replace(/[\u2018\u2019]/g, "'");
              
              const parsed = JSON.parse(cleanText);
              const formatted = JSON.stringify(parsed, null, 2);
              
              textarea.val(formatted);
              
              const btn = $(this);
              const originalHtml = btn.html();
              btn.html('<i class="fas fa-check"></i> Formatted!').prop('disabled', true);
              
              setTimeout(function() {
                  btn.html(originalHtml).prop('disabled', false);
              }, 2000);
              
          } catch (e) {
              alert('JSON formatting failed: ' + e.message + '\n\nPlease check your JSON syntax.');
          }
      });

      // JSON Template Help Popup Functionality
      let currentContextTarget = null;

      $(document).on('click', '.context-help-icon', function() {
          currentContextTarget = $(this).data('target');
          $('#json-template-popup').fadeIn();
      });

      $('#copy-template-btn').on('click', function() {
          const templateText = $('#json-template-text').text();
          
          if (navigator.clipboard && window.isSecureContext) {
              navigator.clipboard.writeText(templateText).then(function() {
                  showCopySuccess();
              }).catch(function() {
                  fallbackCopyToClipboard(templateText);
              });
          } else {
              fallbackCopyToClipboard(templateText);
          }
      });

      $('#use-template-btn').on('click', function() {
          const templateText = $('#json-template-text').text();
          
          let targetTextarea;
          if (currentContextTarget === 'add-context') {
              targetTextarea = $('#new_client_context_info');
          } else if (currentContextTarget === 'edit-context') {
              targetTextarea = $('#edit_client_context_info');
          }
          
          if (targetTextarea && targetTextarea.length) {
              targetTextarea.val(templateText);
              targetTextarea.focus();
          }
          
          if (navigator.clipboard && window.isSecureContext) {
              navigator.clipboard.writeText(templateText);
          }
          
          $('#json-template-popup').fadeOut();
          alert('Template added to the context field! You can now customize the values for your client.');
      });

      function fallbackCopyToClipboard(text) {
          const textArea = document.createElement('textarea');
          textArea.value = text;
          textArea.style.position = 'fixed';
          textArea.style.left = '-999999px';
          textArea.style.top = '-999999px';
          document.body.appendChild(textArea);
          textArea.focus();
          textArea.select();
          
          try {
              document.execCommand('copy');
              showCopySuccess();
          } catch (err) {
              console.error('Failed to copy text: ', err);
              alert('Failed to copy to clipboard. Please select and copy the text manually.');
          }
          
          document.body.removeChild(textArea);
      }

      function showCopySuccess() {
          const copyBtn = $('#copy-template-btn');
          const originalText = copyBtn.text();
          
          copyBtn.addClass('copy-success').text('Copied!');
          
          setTimeout(function() {
              copyBtn.removeClass('copy-success').text(originalText);
          }, 2000);
      }

      $('#json-template-popup .close').on('click', function() {
          $('#json-template-popup').fadeOut();
      });

      $('#json-template-popup').on('click', function(event) {
          if ($(event.target).hasClass('modal')) {
              $('#json-template-popup').fadeOut();
          }
      });

      // Add Client Form with Premium Fields
      $("#add-client-form").on("submit", function (event) {
        event.preventDefault();

        // Validate client context JSON if AI is enabled
        const aiEnabled = $("#new_ai_intelligence_enabled").is(":checked");
        const contextInfo = $("#new_client_context_info").val();
        
        if (aiEnabled && contextInfo.trim()) {
            const validation = validateClientContextJSON(contextInfo);
            if (!validation.valid) {
                alert("Client Context Error: " + validation.error);
                $("#new_client_context_info").focus();
                return;
            }
        }

        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop("disabled", true).text("Adding...");

        // Build form data including premium fields
        let formData = form.serialize() + `&action=cpd_ajax_add_client&nonce=${cpd_admin_ajax.nonce}`;
        
        // Add premium fields if they exist
        if ($('#add-subscription-tier').length) {
            formData += `&subscription_tier=${$('#add-subscription-tier').val() || 'basic'}`;
            formData += `&rtr_enabled=${$('#add-rtr-enabled').is(':checked') ? '1' : '0'}`;
        }

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: formData,
          success: function (response) {
            if (response.success) {
              let message = 'Client added successfully!';
              if (response.data && response.data.subscription_tier === 'premium') {
                  message += ' Premium tier activated.';
                  if (response.data.rtr_enabled) {
                      message += ' RTR enabled.';
                  }
              }
              alert(message);
              form[0].reset();
              
              // Reset premium fields to default
              if ($('#add-subscription-tier').length) {
                  $('#add-subscription-tier').val('basic');
                  handleTierChange('basic', '#add-rtr-enabled', false);
              }
              
              refreshClientTable();
            } else {
              alert("Error: " + response.data.message);
            }
          },
          error: function () {
            alert("An error occurred during the request.");
          },
          complete: function () {
            submitBtn.prop("disabled", false).text("Add Client");
          },
        });
      });

      $("#add-user-form").on("submit", function (event) {
        event.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop("disabled", true).text("Adding...");

        const formData =
          form.serialize() +
          `&action=cpd_ajax_add_user&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: formData,
          success: function (response) {
            if (response.success) {
              alert("User added successfully!");
              form[0].reset();
              refreshUserTable();
            } else {
              alert("Error: " + response.data.message);
            }
          },
          error: function () {
            alert("An error occurred during the request.");
          },
          complete: function () {
            submitBtn.prop("disabled", false).text("Add User");
          },
        });
      });

      // Delete Client
      $("#clients-section .data-table").on(
        "click",
        ".action-button.delete-client",
        function (event) {
          event.preventDefault();
          const row = $(this).closest("tr");
          const clientId = row.data("client-id");

          if (
            confirm(
              "Are you sure you want to delete this client? This action cannot be undone."
            )
          ) {
            $.ajax({
              url: cpd_admin_ajax.ajax_url,
              type: "POST",
              data: {
                action: "cpd_ajax_delete_client",
                nonce: cpd_admin_ajax.nonce,
                client_id: clientId,
              },
              success: function (response) {
                if (response.success) {
                  alert("Client deleted successfully!");
                  refreshClientTable();
                } else {
                  alert("Error: " + response.data.message);
                }
              },
              error: function () {
                alert("An error occurred during the delete request.");
              },
            });
          }
        }
      );

      // Delete User
      $("#users-section .data-table").on(
        "click",
        ".action-button.delete-user",
        function (event) {
          event.preventDefault();
          const row = $(this).closest("tr");
          const userId = row.data("user-id");

          if (
            confirm(
              "Are you sure you want to delete this user? This action cannot be undone."
            )
          ) {
            $.ajax({
              url: cpd_admin_ajax.ajax_url,
              type: "POST",
              data: {
                action: "cpd_ajax_delete_user",
                nonce: cpd_admin_ajax.nonce,
                user_id: userId,
              },
              success: function (response) {
                if (response.success) {
                  alert("User deleted successfully!");
                  refreshUserTable();
                } else {
                  alert("Error: " + response.data.message);
                }
              },
              error: function () {
                alert("An error occurred during the delete request.");
              },
            });
          }
        }
      );

      // ========================================
      // MODAL HANDLERS
      // ========================================

      const editClientModal = $("#edit-client-modal");
      const editClientForm = $("#edit-client-form");

      // Edit Client Button - Updated with Premium Fields
      $(document).on("click", ".action-button.edit-client", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const row = $(this).closest("tr");
        const clientId = row.data("client-id");
        const clientName = row.data("client-name");
        const accountId = row.data("account-id");
        const logoUrl = row.data("logo-url");
        const webpageUrl = row.data("webpage-url");
        const crmEmail = row.data("crm-email");
        const aiEnabled = row.data("ai-intelligence-enabled");
        const clientContext = row.data("client-context-info");
        
        // Premium fields
        const subscriptionTier = row.data("subscription-tier") || 'basic';
        const rtrEnabled = row.data("rtr-enabled") || 0;
        const rtrActivatedAt = row.data("rtr-activated-at");
        const subscriptionExpiresAt = row.data("subscription-expires-at");
        
        // Handle client context
        let contextValue = "";
        if (clientContext) {
            if (typeof clientContext === 'object') {
                contextValue = JSON.stringify(clientContext, null, 2);
            } else {
                contextValue = clientContext;
            }
        }
        
        // Populate standard fields
        $("#edit_client_context_info").val(contextValue);
        $("#edit_client_id").val(clientId);
        $("#edit_client_name").val(clientName);
        $("#edit_account_id").val(accountId);
        $("#edit_logo_url").val(logoUrl);
        $("#edit_webpage_url").val(webpageUrl);
        $("#edit_crm_feed_email").val(crmEmail);
        $("#edit_ai_intelligence_enabled").prop("checked", aiEnabled == "1");
        
        // Populate premium fields if they exist
        if ($('#edit-subscription-tier').length) {
            $('#edit-subscription-tier').val(subscriptionTier);
            $('#edit-rtr-enabled').prop('checked', rtrEnabled == 1);
            
            // Update conditional display
            handleTierChange(subscriptionTier, '#edit-rtr-enabled', true);
            
            // Show RTR status if applicable
            if (rtrEnabled && rtrActivatedAt) {
                const activatedDate = new Date(rtrActivatedAt);
                const expiresDate = subscriptionExpiresAt ? 
                    new Date(subscriptionExpiresAt) : null;
                
                $('#rtr-activated-display').html(
                    `<strong>Activated:</strong> ${activatedDate.toLocaleDateString()}`
                );
                
                if (expiresDate) {
                    $('#subscription-expires-display').html(
                        `<strong>Expires:</strong> ${expiresDate.toLocaleDateString()}`
                    );
                }
                
                $('#rtr-status-info').show();
            } else {
                $('#rtr-status-info').hide();
            }
        }

        editClientModal.fadeIn();
      });

      // Edit Client Form Submit with Premium Fields
      editClientForm.on("submit", function (event) {
        event.preventDefault();

        // Validate client context JSON if AI is enabled
        const aiEnabled = $("#edit_ai_intelligence_enabled").is(":checked");
        const contextInfo = $("#edit_client_context_info").val();
        
        if (aiEnabled && contextInfo.trim()) {
            const validation = validateClientContextJSON(contextInfo);
            if (!validation.valid) {
                alert("Client Context Error: " + validation.error);
                $("#edit_client_context_info").focus();
                return;
            }
        }

        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop("disabled", true).text("Saving...");

        // Build form data including premium fields
        let formData = form.serialize() + `&action=cpd_ajax_edit_client&nonce=${cpd_admin_ajax.nonce}`;
        
        // Add premium fields if they exist
        if ($('#edit-subscription-tier').length) {
            formData += `&subscription_tier=${$('#edit-subscription-tier').val() || 'basic'}`;
            formData += `&rtr_enabled=${$('#edit-rtr-enabled').is(':checked') ? '1' : '0'}`;
        }

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: formData,
          success: function (response) {
            if (response.success) {
              let message = 'Client updated successfully!';
              
              // Show additional feedback for premium changes
              if (response.data) {
                  if (response.data.tier_changed) {
                      message += ' Subscription tier changed.';
                  }
                  if (response.data.rtr_status_changed) {
                      message += response.data.rtr_enabled ? 
                          ' RTR enabled.' : ' RTR disabled.';
                  }
              }
              
              alert(message);
              editClientModal.fadeOut();
              refreshClientTable();
            } else {
              alert("Error: " + response.data.message);
            }
          },
          error: function () {
            alert("An error occurred during the update request.");
          },
          complete: function () {
            submitBtn.prop("disabled", false).text("Save Changes");
          },
        });
      });

      $(".modal .close").on("click", function () {
        $(this).closest(".modal").fadeOut();
      });

      $(".modal").on("click", function (event) {
        if ($(event.target).hasClass("modal")) {
          $(this).fadeOut();
        }
      });

      const editUserModal = $("#edit-user-modal");
      const editUserForm = $("#edit-user-form");

      $(document).on("click", ".action-button.edit-user", function () {
        const row = $(this).closest("tr");
        const userId = row.data("user-id");
        const username = row.data("username");
        const email = row.data("email");
        const role = row.data("role");
        const clientAccountId = row.data("client-account-id");

        $("#edit_user_id").val(userId);
        $("#edit_user_username").val(username);
        $("#edit_user_email").val(email);
        $("#edit_user_role").val(role);
        $("#edit_linked_client").val(clientAccountId);

        editUserModal.fadeIn();
      });

      editUserForm.on("submit", function (event) {
        event.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop("disabled", true).text("Saving...");

        const formData =
          form.serialize() +
          `&action=cpd_ajax_edit_user&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: formData,
          success: function (response) {
            if (response.success) {
              alert("User updated successfully!");
              editUserModal.fadeOut();
              refreshUserTable();
            } else {
              alert("Error: " + response.data.message);
            }
          },
          error: function () {
            alert("An error occurred during the request.");
          },
          complete: function () {
            submitBtn.prop("disabled", false).text("Save Changes");
          },
        });
      });

      // ========================================
      // OTHER ADMIN FUNCTIONALITY
      // ========================================

      if ($.fn.select2) {
        $(".searchable-select").each(function () {
          let dropdownParent = $(this).closest(".modal").length
            ? $(this).closest(".modal")
            : $(this).parent();
          if (
            $(this).attr("id") === "new_linked_client" ||
            $(this).attr("id") === "edit_linked_client" ||
            $(this).attr("id") === "on_demand_client_select" ||
            $(this).attr("id") === "eligible_visitors_client_filter"
          ) {
            dropdownParent = $(this).closest(".card").length
              ? $(this).closest(".card")
              : $(document.body);
          }

          $(this).select2({
            dropdownParent: dropdownParent,
            placeholder: "Select an option...",
            allowClear: true,
          });
        });
      }

      // API Key Generation Logic
      $("#generate_api_key_button").on("click", function (event) {
        event.preventDefault();
        const button = $(this);
        const apiKeyField = $("#cpd_api_key_field");
        const originalText = button.text();

        button.prop("disabled", true).text("Generating...");

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: {
            action: "cpd_generate_api_token",
            nonce: cpd_admin_ajax.nonce,
          },
          success: function (response) {
            if (response.success && response.data.token) {
              apiKeyField.val(response.data.token);
              alert("New API Key generated successfully!");
            } else {
              alert(
                "Error: " +
                  (response.data && response.data.message
                    ? response.data.message
                    : "Failed to generate API key.")
              );
              console.error("API Key generation failed:", response);
            }
          },
          error: function (jqXHR, textStatus, errorThrown) {
            alert("An error occurred during API key generation.");
            console.error(
              "AJAX error during API key generation:",
              textStatus,
              errorThrown
            );
          },
          complete: function () {
            button.prop("disabled", false).text(originalText);
          },
        });
      });

      // CRM Email Management Logic
      const crmClientFilter = $("#crm_client_filter");
      const triggerOnDemandSendButton = $("#trigger_on_demand_send");
      const eligibleVisitorsTableBody = $("#eligible-visitors-table tbody");

      function loadEligibleVisitors() {
        eligibleVisitorsTableBody.html(
          '<tr><td colspan="10" class="no-data">Loading eligible visitors...</td></tr>'
        );
        const clientId = crmClientFilter.val();

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: {
            action: "cpd_get_eligible_visitors",
            nonce: cpd_admin_ajax.nonce,
            account_id: clientId,
          },
          success: function (response) {
            eligibleVisitorsTableBody.empty();
            if (response.success && response.data.visitors.length > 0) {
              response.data.visitors.forEach((visitor) => {
                const fullName =
                  (visitor.first_name || "") + " " + (visitor.last_name || "");
                eligibleVisitorsTableBody.append(`
                    <tr>
                        <td>${escapeHtml(fullName.trim() || "N/A")}</td>
                        <td>${escapeHtml(visitor.company_name || "N/A")}</td>
                        <td><a href="${escapeHtml(visitor.linkedin_url)}" target="_blank" rel="noopener">${escapeHtml(visitor.linkedin_url || "N/A")}</a></td>
                        <td>${escapeHtml(visitor.city || "N/A")}</td>
                        <td>${escapeHtml(visitor.state || "N/A")}</td>
                        <td>${escapeHtml(visitor.zipcode || "N/A")}</td>
                        <td>${new Date(visitor.last_seen_at).toLocaleString()}</td>
                        <td>${visitor.recent_page_count || 0}</td>
                        <td>${escapeHtml(visitor.account_id || "N/A")}</td>
                        <td class="actions-cell">
                            <button class="action-button undo-crm-button" data-visitor-internal-id="${visitor.id}" title="Undo CRM Flag">
                                <i class="fas fa-undo"></i>
                            </button>
                        </td>
                    </tr>
                `);
              });
            } else {
              eligibleVisitorsTableBody.append(
                '<tr><td colspan="10" class="no-data">No eligible visitors found.</td></tr>'
              );
            }
          },
          error: function () {
            eligibleVisitorsTableBody.html(
              '<tr><td colspan="10" class="no-data">Error loading visitors.</td></tr>'
            );
            alert("Error loading eligible visitors. Please try again.");
          },
        });
      }

      function updateButtonState() {
        const selectedValue = crmClientFilter.val();
        if (selectedValue === "all") {
          triggerOnDemandSendButton.prop("disabled", true);
          triggerOnDemandSendButton.attr(
            "title",
            "Please select a specific client to send on-demand emails"
          );
        } else {
          triggerOnDemandSendButton.prop("disabled", false);
          triggerOnDemandSendButton.attr("title", "");
        }
      }

      crmClientFilter.on("change", function () {
        CPD_Dashboard.debounce('crmClientChange', () => {
          loadEligibleVisitors();
          updateButtonState();
        }, 300);
      });

      updateButtonState();

      triggerOnDemandSendButton.off("click").on("click", function () {
        const button = $(this);
        const selectedAccountId = crmClientFilter.val();
        if (selectedAccountId === "all") {
          alert("Please select a specific client to send on-demand emails.");
          return;
        }

        if (
          confirm(
            `Are you sure you want to send on-demand CRM emails for client: ${selectedAccountId}?`
          )
        ) {
          button
            .prop("disabled", true)
            .html('<i class="fas fa-spinner fa-spin"></i> Sending...');

          $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: "POST",
            data: {
              action: "cpd_trigger_on_demand_send",
              nonce: cpd_admin_ajax.nonce,
              account_id: selectedAccountId,
            },
            success: function (response) {
              if (response.success) {
                alert(response.data.message);
                loadEligibleVisitors();
              } else {
                alert("Error: " + response.data.message);
              }
            },
            error: function () {
              alert("An error occurred during the on-demand send request.");
            },
            complete: function () {
              button
                .prop("disabled", false)
                .html(
                  '<i class="fas fa-paper-plane"></i> Send On-Demand CRM Email'
                );
              updateButtonState();
            },
          });
        }
      });

      // ========================================
      // REFERRER LOGO MAPPING MANAGEMENT
      // ========================================

      $("#add-referrer-mapping").on("click", function () {
        const newRow = `
            <div class="referrer-mapping-row">
                <div class="form-group">
                    <label>Domain</label>
                    <input type="text" name="referrer_domains[]" placeholder="e.g., facebook.com">
                </div>
                <div class="form-group">
                    <label>Logo URL</label>
                    <input type="url" name="referrer_logos[]" placeholder="https://example.com/logo.png">
                </div>
                <div class="form-group">
                    <button type="button" class="button button-secondary remove-mapping">Remove</button>
                </div>
            </div>
        `;
        $("#referrer-logo-mappings").append(newRow);

        const $newRow = $(
          "#referrer-logo-mappings .referrer-mapping-row:last-child"
        );
        $newRow.hide().fadeIn(300);
      });

      $(document).on("click", ".remove-mapping", function () {
        const $row = $(this).closest(".referrer-mapping-row");

        if (
          confirm("Are you sure you want to remove this referrer logo mapping?")
        ) {
          $row.fadeOut(300, function () {
            $(this).remove();
          });
        }
      });

      $(document).on("input", 'input[name="referrer_domains[]"]', function () {
        const $input = $(this);
        let domain = $input.val().toLowerCase().trim();

        domain = domain.replace(/^https?:\/\//, "");
        domain = domain.replace(/^www\./, "");
        domain = domain.replace(/\/$/, "");

        if (domain !== $input.val()) {
          $input.val(domain);
        }
      });

      $("#settings-form").on("submit", function (e) {
        const domains = $('input[name="referrer_domains[]"]');
        const logos = $('input[name="referrer_logos[]"]');
        let hasError = false;

        domains.removeClass("error");
        logos.removeClass("error");
        $(".referrer-error-message").remove();

        domains.each(function (index) {
          const domain = $(this).val().trim();
          const logo = logos.eq(index).val().trim();

          if (domain && !logo) {
            $(this).addClass("error");
            logos.eq(index).addClass("error");
            logos
              .eq(index)
              .after(
                '<span class="referrer-error-message" style="color: #dc3545; font-size: 0.8rem;">Logo URL is required when domain is specified</span>'
              );
            hasError = true;
          } else if (!domain && logo) {
            $(this).addClass("error");
            $(this).after(
              '<span class="referrer-error-message" style="color: #dc3545; font-size: 0.8rem;">Domain is required when logo URL is specified</span>'
            );
            hasError = true;
          }
        });

        if (hasError) {
          e.preventDefault();
          $("html, body").animate(
            {
              scrollTop: $(".error").first().offset().top - 100,
            },
            300
          );
          return false;
        }
      });

      // ========================================
      // INTELLIGENCE SETTINGS FUNCTIONALITY
      // ========================================

      const ajaxUrl =
        typeof cpd_admin_ajax !== "undefined"
          ? cpd_admin_ajax.ajax_url
          : ajaxurl;

      const testWebhookBtn = $("#test-webhook-btn");
      const testResult = $("#webhook-test-result");

      if (testWebhookBtn.length) {
        testWebhookBtn.on("click", function () {
          const webhookUrl = $("#intelligence_webhook_url").val();
          const apiKey = $("#makecom_api_key").val();

          if (!webhookUrl || !apiKey) {
            testResult.html(
              '<span style="color: #dc3545;">Please enter both Webhook URL and API Key</span>'
            );
            return;
          }

          testWebhookBtn.prop("disabled", true).text("Testing...");
          testResult.html(
            '<span style="color: #6c757d;">Testing connection...</span>'
          );

          $.ajax({
            url: ajaxUrl,
            type: "POST",
            data: {
              action: "cpd_test_intelligence_webhook",
              nonce: cpd_admin_ajax.nonce,
              webhook_url: webhookUrl,
              api_key: apiKey,
            },
            success: function (response) {
              if (response.success) {
                testResult.html(
                  '<span style="color: #28a745;">' +
                    response.data.message +
                    "</span>"
                );
              } else {
                testResult.html(
                  '<span style="color: #dc3545;">' +
                    response.data.message +
                    "</span>"
                );
              }
            },
            error: function () {
              console.error("Webhook test error");
              testResult.html(
                '<span style="color: #dc3545;">Connection failed</span>'
              );
            },
            complete: function () {
              testWebhookBtn.prop("disabled", false).text("Test Webhook");
            },
          });
        });
      }

      const intelligenceForm = $("#intelligence-settings-form");
      if (intelligenceForm.length) {
        intelligenceForm.off("submit").on("submit", function (e) {
          e.preventDefault();
          e.stopPropagation();

          const submitBtn = intelligenceForm.find('button[type="submit"]');
          const originalText = submitBtn.text();

          if (submitBtn.prop("disabled")) {
            return false;
          }

          submitBtn.prop("disabled", true).text("Saving...");

          const formData = {
            action: "cpd_save_intelligence_settings",
            nonce: cpd_admin_ajax.nonce,
            intelligence_webhook_url: $("#intelligence_webhook_url").val(),
            makecom_api_key: $("#makecom_api_key").val(),
            intelligence_rate_limit: $("#intelligence_rate_limit").val(),
            intelligence_timeout: $("#intelligence_timeout").val(),
            intelligence_auto_generate_crm: $(
              "#intelligence_auto_generate_crm"
            ).is(":checked")
              ? "1"
              : "",
            intelligence_processing_method: $(
              "#intelligence_processing_method"
            ).val(),
            intelligence_batch_size: $("#intelligence_batch_size").val(),
            intelligence_crm_timeout: $("#intelligence_crm_timeout").val(),
          };

          $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: "POST",
            data: formData,
            success: function (response) {
              if (response.success) {
                alert(response.data.message);
              } else {
                alert("Error: " + response.data.message);
              }
            },
            error: function (xhr, status, error) {
              console.error("AJAX error:", error);
              alert("An error occurred while saving settings");
            },
            complete: function () {
              submitBtn.prop("disabled", false).text(originalText);
            },
          });

          return false;
        });
      }

      const defaultsForm = $("#intelligence-defaults-form");
      if (defaultsForm.length) {
        defaultsForm.on("submit", function (e) {
          e.preventDefault();

          const submitBtn = defaultsForm.find('button[type="submit"]');
          const originalText = submitBtn.text();

          submitBtn.prop("disabled", true).text("Saving...");

          const formData =
            defaultsForm.serialize() +
            "&action=cpd_save_intelligence_defaults&nonce=" +
            cpd_admin_ajax.nonce;

          $.ajax({
            url: ajaxUrl,
            type: "POST",
            data: formData,
            success: function (response) {
              if (response.success) {
                alert(response.data.message);
              } else {
                alert("Error: " + response.data.message);
              }
            },
            error: function () {
              console.error("Save error");
              alert("An error occurred while saving default settings");
            },
            complete: function () {
              submitBtn.prop("disabled", false).text(originalText);
            },
          });
        });
      }

      const newAiToggle = $("#new_ai_intelligence_enabled");
      const newContextGroup = $("#new-client-context-group");

      if (newAiToggle.length && newContextGroup.length) {
        newContextGroup.toggle(newAiToggle.is(":checked"));

        newAiToggle.on("change", function () {
          const isChecked = $(this).is(":checked");
          newContextGroup.toggle(isChecked);
          if (isChecked) {
            newContextGroup[0].scrollIntoView({
              behavior: "smooth",
              block: "nearest",
            });
          }
        });
      }

      const editAiToggle = $("#edit_ai_intelligence_enabled");
      const editContextGroup = $("#edit_client_context_info");

      if (editAiToggle.length && editContextGroup.length) {
        editAiToggle.on("change", function () {
          const isChecked = $(this).is(":checked");
          editContextGroup.toggle(isChecked);
          if (isChecked) {
            editContextGroup[0].scrollIntoView({
              behavior: "smooth",
              block: "nearest",
            });
          }
        });
      }

      // Initialize premium tier handlers
      setupPremiumTierHandlers();

    } else {

      const isDashboardPage =
        document.querySelector(".dashboard-container") ||
        document.querySelector(".client-dashboard") ||
        document.body.classList.contains("client-dashboard-page");

      if (isDashboardPage) {
        setTimeout(function () {
          const initialClientIdElement = $(
            ".admin-sidebar .account-list li.active"
          );
          let initialClientId;

          if (initialClientIdElement.length > 0) {
            initialClientId = initialClientIdElement.data("client-id");
          } else {
            initialClientId = "all";
            const allClientsListItem = $(
              '.admin-sidebar .account-list li[data-client-id="all"]'
            );
            if (allClientsListItem.length > 0) {
              allClientsListItem.addClass("active");
            }
          }

          const initialDuration = dateRangeSelect.val();

          if (
            dashboardContent.length > 0 &&
            typeof loadDashboardData === "function"
          ) {
            loadDashboardData(
              initialClientId === "all" ? null : initialClientId,
              initialDuration
            );
          }
        }, 150);
      } else {
        console.log(
          "cpd-dashboard.js: Neither admin nor client dashboard page detected."
        );
      }
    }

    // ========================================
    // GLOBAL SHARED FUNCTIONALITY
    // ========================================

    const sendAjaxRequestForVisitor = async (action, visitorId) => {
      const ajaxUrl = localizedPublicData.ajax_url || adminAjaxData.ajax_url;
      const nonce = localizedPublicData.visitor_nonce;

      if (!ajaxUrl || !nonce) {
        console.error(
          "sendAjaxRequestForVisitor: Localized data missing."
        );
        return false;
      }

      const formData = new FormData();
      formData.append("action", "cpd_update_visitor_status");
      formData.append("nonce", nonce);
      formData.append("visitor_id", visitorId);
      formData.append("update_action", action);

      try {
        const response = await fetch(ajaxUrl, {
          method: "POST",
          body: formData,
        });

        if (!response.ok) {
          const errorText = await response.text();
          console.error(
            `sendAjaxRequestForVisitor: Server error ${response.status}: ${errorText}`
          );
          throw new Error(
            `Network response was not ok. Status: ${response.status}`
          );
        }

        const data = await response.json();

        if (data.success) {
          return true;
        } else {
          console.error(
            "sendAjaxRequestForVisitor: AJAX error:",
            data.data
          );
          return false;
        }
      } catch (error) {
        console.error("sendAjaxRequestForVisitor: Fetch error:", error);
        return false;
      }
    };

    const visitorPanel = $(".visitor-panel");

    if (visitorPanel.length > 0) {
      visitorPanel.off('click.visitorActions').on(
        "click.visitorActions",
        ".add-crm-icon, .delete-icon",
        async function (event) {
          event.preventDefault();

          const button = $(this);
          const visitorCard = button.closest(".visitor-card");
          const visitorId = visitorCard.data("visitor-id");
          
          if (button.prop('disabled')) {
            return;
          }

          let updateAction = "";
          if (button.hasClass("add-crm-icon")) {
            updateAction = "add_crm";
            if (
              !confirm(
                "Are you sure you want to flag this visitor for CRM addition?"
              )
            ) {
              return;
            }
          } else if (button.hasClass("delete-icon")) {
            updateAction = "archive";
            if (
              !confirm(
                "Are you sure you want to archive this visitor?"
              )
            ) {
              return;
            }
          }

          button.prop("disabled", true).css("opacity", 0.6);

          try {
            const success = await sendAjaxRequestForVisitor(
              updateAction,
              visitorId
            );

            if (success) {
              const currentClientId =
                clientList.length > 0 && clientList.find("li.active").length > 0
                  ? clientList.find("li.active").data("client-id")
                  : "all";
              const currentDuration = dateRangeSelect.val();

              if (typeof loadDashboardData === "function" && !isAdminPage) {
                CPD_Dashboard.debounce('dashboardReload', () => {
                  loadDashboardData(currentClientId, currentDuration);
                }, 300);
              }
            } else {
              alert(
                "Failed to update visitor status. Please check console for details."
              );
            }
          } catch (error) {
            alert("An unexpected error occurred. Please check console.");
          } finally {
            button.prop("disabled", false).css("opacity", 1);
          }
        }
      );
    }

    if (clientList.length > 0) {
      clientList.off('click.clientSelection').on("click.clientSelection", "li", function () {
        const listItem = $(this);
        const clientId = listItem.data("client-id");
        
        if (listItem.hasClass('active')) {
          return;
        }
        
        clientList.find("li").removeClass("active");
        listItem.addClass("active");

        const currentUrl = new URL(window.location.href);
        if (clientId === "all") {
          currentUrl.searchParams.delete("client_id");
        } else {
          currentUrl.searchParams.set("client_id", clientId);
        }
        window.history.pushState({}, "", currentUrl.toString());

        if (typeof loadDashboardData === "function" && !isAdminPage) {
          CPD_Dashboard.debounce('clientChange', () => {
            loadDashboardData(clientId, dateRangeSelect.val());
          }, 300);
        }
      });
    }

    if (dateRangeSelect.length > 0) {
      dateRangeSelect.off('change.durationChange').on("change.durationChange", function () {
        const activeClientListItem = clientList.find("li.active");
        const clientId =
          activeClientListItem.length > 0
            ? activeClientListItem.data("client-id")
            : "all";

        if (typeof loadDashboardData === "function" && !isAdminPage) {
          CPD_Dashboard.debounce('durationChange', () => {
            loadDashboardData(clientId, $(this).val());
          }, 300);
        }
      });
    }

    $(window).on('beforeunload', function() {
      Object.values(CPD_Dashboard.debounceTimers).forEach(timer => {
        if (timer) clearTimeout(timer);
      });
      
      CPD_Dashboard.loadingStates.clear();
      CPD_Dashboard.requestQueue.clear();
    });

    console.log("cpd-dashboard.js: Initialization complete with premium support.");
  });
}