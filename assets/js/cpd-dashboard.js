/**
 * Admin-specific JavaScript for the Campaign Performance Dashboard plugin.
 * Consolidates all logic into a single jQuery(document).ready block.
 */

if (typeof window.cpdAdminInitialized === "undefined") {
  window.cpdAdminInitialized = true;

  jQuery(document).ready(function ($) {
    console.log("cpd-dashboard.js: Script started. jQuery document ready.");
    console.log("jQuery is working, $ is:", typeof $);

    // Access localized data from cpd_dashboard_data (public-facing) for nonces needed on public page
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

    const dashboardContent = $("#clients-section"); // Main dashboard content container (admin-only HTML)
    const clientList = $(".account-list"); // Left sidebar client list (admin-only HTML)
    const dateRangeSelect = $(".duration-select select"); // Date range selector (exists on both)

    // NEW: Function to refresh client table
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
                  ? `<img src="${client.logo_url}" alt="Logo" class="client-logo-thumbnail">`
                  : '<span class="no-logo">N/A</span>';

                const webpageHtml = client.webpage_url
                  ? `<a href="${client.webpage_url}" target="_blank" rel="noopener">${client.webpage_url}</a>`
                  : '<span class="no-url">N/A</span>';

                // Add AI intelligence status
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

                clientTableBody.append(`
                                <tr data-client-id="${client.id}"
                                    data-client-name="${client.client_name}"
                                    data-account-id="${client.account_id}"
                                    data-logo-url="${client.logo_url || ""}"
                                    data-webpage-url="${
                                      client.webpage_url || ""
                                    }"
                                    data-crm-email="${
                                      client.crm_feed_email || ""
                                    }"
                                    data-ai-intelligence-enabled="${
                                      client.ai_intelligence_enabled || 0
                                    }"
                                    data-client-context-info="${
                                      client.client_context_info || ""
                                    }">
                                    <td>${client.client_name}</td>
                                    <td>${client.account_id}</td>
                                    <td>${logoHtml}</td>
                                    <td>${webpageHtml}</td>
                                    <td>${client.crm_feed_email || ""}</td>
                                    <td><div style="display: flex; align-items: center; gap: 10px;">${aiStatusBadge}${aiContextIcon}</div></td>
                                    <td class="actions-cell">
                                        <button class="action-button edit-client" title="Edit Client">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-button delete-client" data-client-id="${
                                          client.id
                                        }" title="Delete Client">
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

    // NEW: Function to refresh user table
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
                                        data-username="${user.user_login}"
                                        data-email="${user.user_email}"
                                        data-role="${user.roles.join(", ")}"
                                        data-client-account-id="${
                                          user.client_account_id || ""
                                        }">
                                        <td>${user.user_login}</td>
                                        <td>${user.user_email}</td>
                                        <td>${user.roles.join(", ")}</td>
                                        <td>${linkedClientName}</td>
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

    // Function to load dashboard data via AJAX (should only run on dashboard pages, not admin)
    function loadDashboardData(clientId, duration) {
      console.log(
        "ADMIN loadDashboardData: Called with Client ID:",
        clientId,
        "Duration:",
        duration
      );
      if (dashboardContent.length === 0) {
        // Defensive check
        console.warn(
          "Dashboard content container (#clients-section) not found. Cannot load dashboard data."
        );
        return;
      }
      dashboardContent.css("opacity", 0.5);

      $.ajax({
        url: cpd_admin_ajax.ajax_url,
        type: "POST",
        data: {
          action: "cpd_get_dashboard_data",
          nonce: cpd_admin_ajax.nonce,
          client_id: clientId === "all" ? null : clientId,
          duration: duration,
        },
        success: function (response) {
          console.log(
            "ADMIN loadDashboardData: AJAX Success. Response:",
            response
          );
          // Dashboard update logic here (but won't run on admin page)
        },
        error: function (jqXHR, textStatus, errorThrown) {
          console.error(
            "loadDashboardData: AJAX request failed. Status:",
            textStatus,
            "Error:",
            errorThrown,
            "Response Text:",
            jqXHR.responseText
          );
        },
        complete: function () {
          dashboardContent.css("opacity", 1);
          console.log("loadDashboardData: AJAX request complete.");
        },
      });
    }

    // ========================================
    // PAGE TYPE DETECTION AND INITIALIZATION
    // ========================================

    const isAdminPage = document.body.classList.contains(
      "campaign-dashboard_page_cpd-dashboard-management"
    );
    // console.log("isAdminPage: ", isAdminPage, " document.body.classList: ", document.body.classList);

    if (isAdminPage) {
      // console.log('cpd-dashboard.js: Admin-specific UI listeners attaching.');

      // Count elements for debugging
      const navLinks = document.querySelectorAll(
        ".admin-sidebar nav a[data-target]"
      );
      const editButtons = document.querySelectorAll(
        ".action-button.edit-client"
      );
      const clientTable = document.querySelectorAll(
        "#clients-section .data-table"
      );

      // Admin page initialization
      window.addEventListener("load", function () {
        // console.log('cpd-dashboard.js: window.load event fired. Delaying navigation initialization for full DOM readiness.');

        setTimeout(function () {
          // console.log('cpd-dashboard.js: Navigation initialization (delayed) starting.');

          const navLinks = document.querySelectorAll(
            ".admin-sidebar nav a[data-target]"
          );
          const sections = document.querySelectorAll(
            ".admin-main-content .section-content"
          );

          if (navLinks.length === 0 || sections.length === 0) {
            console.error(
              "cpd-dashboard.js: CRITICAL ERROR: Navigation links or sections not found even after delay. Check admin-page.php structure and IDs."
            );
            return;
          }

          const defaultSectionId = "clients-section";
          const initialHash = window.location.hash.substring(1);

          // Function to set the active section based on URL hash or default
          function setActiveSection() {
            const sections = document.querySelectorAll(
              ".admin-main-content .section-content"
            );
            const navLinks = document.querySelectorAll(
              ".admin-sidebar nav a[data-target]"
            );
            const defaultSectionId = "clients-section"; // default section ID

            let targetHashId = window.location.hash.substring(1);
            if (!targetHashId) {
              targetHashId = defaultSectionId.replace("-section", ""); // Fallback to default if no hash
            }

            // First, hide all sections and remove active class from all nav links
            sections.forEach((s) => {
              if (s) {
                s.classList.remove("active");
                s.style.display = "none"; // Explicitly hide the section
                // console.log('Removed active and set display: none for section:', s.id);
              }
            });

            navLinks.forEach((link) => {
              link.classList.remove("active");
              // console.log('Removed active from:', link);
            });

            const targetSection = document.getElementById(
              targetHashId + "-section"
            );
            const targetLink = document.querySelector(
              `.admin-sidebar nav a[data-target="${targetHashId}-section"]`
            );

            if (targetSection && targetLink) {
              targetSection.classList.add("active");
              targetSection.style.display = "block"; // Explicitly show the target section

              console.log(
                `DEBUG: Target section ${targetSection.id} display set to: ${targetSection.style.display}`
              );

              targetLink.classList.add("active");
              console.log(
                "setActiveSection: Added active classes and set display: block successfully to:",
                targetSection.id,
                "and",
                targetLink
              );
            } else {
              console.warn(
                "setActiveSection: Fallback to default, targetSection or targetLink not found for:",
                targetHashId
              );
              // Fallback to default client section if target not found
              const fallbackSection = document.getElementById(defaultSectionId);
              const fallbackLink = document.querySelector(
                `.admin-sidebar nav a[data-target="${defaultSectionId}"]`
              );
              if (fallbackSection) {
                fallbackSection.classList.add("active");
                fallbackSection.style.display = "block"; // Show fallback section
                console.log(
                  `DEBUG: Fallback section ${fallbackSection.id} display set to: ${fallbackSection.style.display}`
                );
              }
              if (fallbackLink) {
                fallbackLink.classList.add("active");
              }
              window.location.hash = defaultSectionId.replace("-section", "");
            }
          }
          // Call it once after delay
          setActiveSection();

          navLinks.forEach((link) => {
            console.log("Adding click listener to nav link:", link);
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

              // IMPORTANT CHANGE HERE: Call setActiveSection to handle display and classes
              setActiveSection();

              if (cleanedTargetId === "crm-emails") {
                if (typeof loadEligibleVisitors === "function") {
                  console.log("Calling loadEligibleVisitors...");
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

      // --- AJAX for Management Forms ---
      $("#add-client-form").on("submit", function (event) {
        event.preventDefault();
        console.log("cpd-dashboard.js: Add Client form submitted!");
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop("disabled", true).text("Adding...");

        const formData =
          form.serialize() +
          `&action=cpd_ajax_add_client&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: formData,
          success: function (response) {
            if (response.success) {
              alert("Client added successfully!");
              form[0].reset();
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
        console.log("cpd-dashboard.js: Add User form submitted!");
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

      // Client and User table action handlers
      $("#clients-section .data-table").on(
        "click",
        ".action-button.delete-client",
        function (event) {
          event.preventDefault();
          console.log("cpd-dashboard.js: Delete Client button clicked!");
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

      $("#users-section .data-table").on(
        "click",
        ".action-button.delete-user",
        function (event) {
          event.preventDefault();
          console.log("cpd-dashboard.js: Delete User button clicked!");
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

      // Edit client button handler
      $(document).on("click", ".action-button.edit-client", function (e) {
        e.preventDefault();
        e.stopPropagation();
        console.log("cpd-dashboard.js: Edit Client button clicked!");
        console.log("Edit button element:", this);

        const row = $(this).closest("tr");
        const clientId = row.data("client-id");
        console.log("cpd-dashboard.js: Edit Client ID:", clientId);
        const clientName = row.data("client-name");
        const accountId = row.data("account-id");
        const logoUrl = row.data("logo-url");
        const webpageUrl = row.data("webpage-url");
        const crmEmail = row.data("crm-email");
        const aiEnabled = row.data("ai-intelligence-enabled");
        const clientContext = row.data("client-context-info");

        $("#edit_client_id").val(clientId);
        $("#edit_client_name").val(clientName);
        $("#edit_account_id").val(accountId);
        $("#edit_logo_url").val(logoUrl);
        $("#edit_webpage_url").val(webpageUrl);
        $("#edit_crm_feed_email").val(crmEmail);
        $("#edit_ai_intelligence_enabled").prop("checked", aiEnabled == "1");
        $("#edit_client_context_info").val(clientContext || "");

        // toggleEditContextSection();

        editClientModal.fadeIn();
      });

      editClientForm.on("submit", function (event) {
        event.preventDefault();
        console.log("cpd-dashboard.js: Edit Client form submitted!");
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop("disabled", true).text("Saving...");

        const formData =
          form.serialize() +
          `&action=cpd_ajax_edit_client&nonce=${cpd_admin_ajax.nonce}`;

        $.ajax({
          url: cpd_admin_ajax.ajax_url,
          type: "POST",
          data: formData,
          success: function (response) {
            if (response.success) {
              alert("Client updated successfully!");
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
        console.log("cpd-dashboard.js: Modal close button clicked!");
        $(this).closest(".modal").fadeOut();
      });

      $(".modal").on("click", function (event) {
        if ($(event.target).hasClass("modal")) {
          console.log("cpd-dashboard.js: Modal background clicked!");
          $(this).fadeOut();
        }
      });

      const editUserModal = $("#edit-user-modal");
      const editUserForm = $("#edit-user-form");

      $(document).on("click", ".action-button.edit-user", function () {
        console.log("cpd-dashboard.js: Edit User button clicked!");
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
        console.log("cpd-dashboard.js: Edit User form submitted!");
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
        console.log(
          "cpd-dashboard.js: Select2 found, initializing searchable selects."
        );
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
              errorThrown,
              jqXHR.responseText
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
                                        <td>${fullName.trim() || "N/A"}</td>
                                        <td>${
                                          visitor.company_name || "N/A"
                                        }</td>
                                        <td><a href="${
                                          visitor.linkedin_url
                                        }" target="_blank" rel="noopener">${
                  visitor.linkedin_url || "N/A"
                }</a></td>
                                        <td>${visitor.city || "N/A"}</td>
                                        <td>${visitor.state || "N/A"}</td>
                                        <td>${visitor.zipcode || "N/A"}</td>
                                        <td>${new Date(
                                          visitor.last_seen_at
                                        ).toLocaleString()}</td>
                                        <td>${
                                          visitor.recent_page_count || 0
                                        }</td>
                                        <td>${visitor.account_id || "N/A"}</td>
                                        <td class="actions-cell">
                                            <button class="action-button undo-crm-button" data-visitor-internal-id="${
                                              visitor.id
                                            }" title="Undo CRM Flag">
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

      // Update button state and tooltip based on selection
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

      // Event handlers
      crmClientFilter.on("change", function () {
        loadEligibleVisitors();
        updateButtonState();
      });

      // Initialize button state
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
        console.log("cpd-dashboard.js: Add referrer mapping clicked");
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

      // Remove referrer mapping row
      $(document).on("click", ".remove-mapping", function () {
        console.log("cpd-dashboard.js: Remove referrer mapping clicked");
        const $row = $(this).closest(".referrer-mapping-row");

        if (
          confirm("Are you sure you want to remove this referrer logo mapping?")
        ) {
          $row.fadeOut(300, function () {
            $(this).remove();
          });
        }
      });

      // Domain input formatting helper
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

      // Form validation for referrer mappings
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

      console.log("CPD Intelligence Settings: JavaScript loaded");

      const ajaxUrl =
        typeof cpd_admin_ajax !== "undefined"
          ? cpd_admin_ajax.ajax_url
          : ajaxurl;

      // Webhook testing functionality
      const testWebhookBtn = $("#test-webhook-btn");
      const testResult = $("#webhook-test-result");

      if (testWebhookBtn.length) {
        testWebhookBtn.on("click", function () {
          const webhookUrl = $("#intelligence_webhook_url").val();
          const apiKey = $("#makecom_api_key").val();

          if (!webhookUrl || !apiKey) {
            testResult.html(
              '<span style="color: #dc3545;">⚠️ Please enter both Webhook URL and API Key</span>'
            );
            return;
          }

          testWebhookBtn.prop("disabled", true).text("Testing...");
          testResult.html(
            '<span style="color: #6c757d;">🔄 Testing connection...</span>'
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
                  '<span style="color: #28a745;">✅ ' +
                    response.data.message +
                    "</span>"
                );
              } else {
                testResult.html(
                  '<span style="color: #dc3545;">❌ ' +
                    response.data.message +
                    "</span>"
                );
              }
            },
            error: function () {
              console.error("Webhook test error");
              testResult.html(
                '<span style="color: #dc3545;">❌ Connection failed</span>'
              );
            },
            complete: function () {
              testWebhookBtn.prop("disabled", false).text("Test Webhook");
            },
          });
        });
      }

      // Intelligence Settings Form Submission
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

          console.log("Sending intelligence settings data:", formData);

          $.ajax({
            url: cpd_admin_ajax.ajax_url,
            type: "POST",
            data: formData,
            success: function (response) {
              if (response.success) {
                alert("✅ " + response.data.message);
              } else {
                alert("❌ Error: " + response.data.message);
              }
            },
            error: function (xhr, status, error) {
              console.error("AJAX error:", error);
              alert("❌ An error occurred while saving settings");
            },
            complete: function () {
              submitBtn.prop("disabled", false).text(originalText);
            },
          });

          return false;
        });
      }

      // Intelligence Defaults Form Submission
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
                alert("✅ " + response.data.message);
              } else {
                alert("❌ Error: " + response.data.message);
              }
            },
            error: function () {
              console.error("Save error");
              alert("❌ An error occurred while saving default settings");
            },
            complete: function () {
              submitBtn.prop("disabled", false).text(originalText);
            },
          });
        });
      }

      // AI Intelligence Toggle for Add Client Form
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

      // AI Intelligence Toggle for Edit Client Form
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

      // console.log('CPD Intelligence Settings: All event listeners attached');
      // console.log('cpd-dashboard.js: Admin page initialization complete.');
    } else {
      console.log(
        "cpd-dashboard.js: Not on admin management page. Checking if this is client dashboard page."
      );

      // Only run dashboard data loading logic if we're on the actual dashboard page
      const isDashboardPage =
        document.querySelector(".dashboard-container") ||
        document.querySelector(".client-dashboard") ||
        document.body.classList.contains("client-dashboard-page");

      if (isDashboardPage) {
        console.log(
          "cpd-dashboard.js: Client dashboard page detected. Initializing dashboard data."
        );

        // Dashboard initialization for client pages only
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
          console.log(
            "cpd-dashboard.js: Initializing dashboard data load for client dashboard."
          );

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
          "cpd-dashboard.js: Neither admin nor client dashboard page detected. Shared functions available only."
        );
      }
    }

    // ========================================
    // GLOBAL SHARED FUNCTIONALITY (runs on all pages)
    // ========================================

    // These functions can be used by both admin and client pages

    // A simple function to handle AJAX requests to our custom endpoint for visitor updates.
    // This is primarily for the *dashboard* visitor actions.
    const sendAjaxRequestForVisitor = async (action, visitorId) => {
      const ajaxUrl = localizedPublicData.ajax_url || adminAjaxData.ajax_url;
      const nonce = localizedPublicData.visitor_nonce; // This nonce is for public-facing visitor actions

      if (!ajaxUrl || !nonce) {
        console.error(
          "sendAjaxRequestForVisitorStatus: Localized data (ajax_url or nonce) is missing."
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
            `sendAjaxRequestForVisitorStatus: Server responded with status ${response.status}: ${errorText}`
          );
          throw new Error(
            `Network response was not ok. Status: ${response.status}, Details: ${errorText}`
          );
        }

        const data = await response.json();

        if (data.success) {
          console.log(
            `sendAjaxRequestForVisitorStatus: Visitor ${visitorId} status updated successfully.`
          );
          return true;
        } else {
          console.error(
            "sendAjaxRequestForVisitorStatus: AJAX error:",
            data.data
          );
          return false;
        }
      } catch (error) {
        console.error("sendAjaxRequestForVisitorStatus: Fetch error:", error);
        return false;
      }
    };

    // Visitor panel functionality (for dashboard pages that have visitor panels)
    const visitorPanel = $(".visitor-panel");
    console.log(
      "cpd-dashboard.js: Visitor Panel element found (jQuery):",
      visitorPanel.length > 0 ? "Yes" : "No",
      visitorPanel
    );

    if (visitorPanel.length > 0) {
      console.log(
        "cpd-dashboard.js: Attaching click listener to Visitor Panel buttons."
      );
      visitorPanel.on(
        "click",
        ".add-crm-icon, .delete-icon",
        async function (event) {
          event.preventDefault();
          console.log("cpd-dashboard.js: Visitor button clicked!");

          const button = $(this);
          const visitorCard = button.closest(".visitor-card");
          const visitorId = visitorCard.data("visitor-id");
          console.log("cpd-dashboard.js: Visitor ID:", visitorId);

          let updateAction = "";
          if (button.hasClass("add-crm-icon")) {
            updateAction = "add_crm";
            if (
              !confirm(
                "Are you sure you want to flag this visitor for CRM addition?"
              )
            ) {
              console.log("cpd-dashboard.js: CRM Add confirmation cancelled.");
              return;
            }
          } else if (button.hasClass("delete-icon")) {
            updateAction = "archive";
            if (
              !confirm(
                "Are you sure you want to archive this visitor? They will no longer appear in the list."
              )
            ) {
              console.log("cpd-dashboard.js: Archive confirmation cancelled.");
              return;
            }
          }
          console.log("cpd-dashboard.js: Update action:", updateAction);

          button.prop("disabled", true).css("opacity", 0.6);
          console.log("cpd-dashboard.js: Button disabled.");

          try {
            const success = await sendAjaxRequestForVisitor(
              updateAction,
              visitorId
            );
            console.log(
              "cpd-dashboard.js: sendAjaxRequestForVisitor success status:",
              success
            );

            if (success) {
              // Check if clientList exists and has an active item before trying to access its data
              const currentClientId =
                clientList.length > 0 && clientList.find("li.active").length > 0
                  ? clientList.find("li.active").data("client-id")
                  : "all";
              const currentDuration = dateRangeSelect.val();

              // Only reload dashboard data if we're on a dashboard page (not admin page)
              if (typeof loadDashboardData === "function" && !isAdminPage) {
                loadDashboardData(currentClientId, currentDuration);
              }
              console.log(
                `cpd-dashboard.js: Visitor ${visitorId} action "${updateAction}" processed. Dashboard reloaded.`
              );
            } else {
              alert(
                "Failed to update visitor status. Please check console for details."
              );
              console.error(
                "cpd-dashboard.js: Failed to update visitor status."
              );
            }
          } catch (error) {
            console.error(
              "cpd-dashboard.js: Error during visitor action AJAX:",
              error
            );
            alert("An unexpected error occurred. Please check console.");
          } finally {
            button.prop("disabled", false).css("opacity", 1);
            console.log("cpd-dashboard.js: Button re-enabled.");
          }
        }
      );
    }

    // Client list functionality (for pages that have client lists)
    if (clientList.length > 0) {
      clientList.on("click", "li", function () {
        console.log("cpd-dashboard.js: Client list item clicked!");
        const listItem = $(this);
        const clientId = listItem.data("client-id");
        console.log("cpd-dashboard.js: Clicked Client ID:", clientId);
        clientList.find("li").removeClass("active");
        listItem.addClass("active");

        const currentUrl = new URL(window.location.href);
        if (clientId === "all") {
          currentUrl.searchParams.delete("client_id");
        } else {
          currentUrl.searchParams.set("client_id", clientId);
        }
        window.history.pushState({}, "", currentUrl.toString());

        // Only call loadDashboardData if we're not on admin page
        if (typeof loadDashboardData === "function" && !isAdminPage) {
          loadDashboardData(clientId, dateRangeSelect.val());
        }
      });
    }

    // Date range selector functionality (for pages that have date selectors)
    if (dateRangeSelect.length > 0) {
      dateRangeSelect.on("change", function () {
        console.log("cpd-dashboard.js: Date range dropdown changed!");
        const activeClientListItem = clientList.find("li.active");
        const clientId =
          activeClientListItem.length > 0
            ? activeClientListItem.data("client-id")
            : "all";

        // Only call loadDashboardData if we're not on admin page
        if (typeof loadDashboardData === "function" && !isAdminPage) {
          loadDashboardData(clientId, $(this).val());
        }
      });
    }

    console.log("cpd-dashboard.js: Initialization complete.");
  });
}
