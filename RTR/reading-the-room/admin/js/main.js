/**
 * Reading the Room Dashboard - Main JavaScript
 *
 * Coordinates all dashboard functionality
 *
 * @package DirectReach
 * @subpackage ReadingTheRoom
 * @since 1.0.0
 */

(function () {
  "use strict";

  // API Client
  class APIClient {
    constructor(baseUrl, nonce) {
      this.baseUrl = baseUrl;
      this.nonce = nonce;
    }

    async request(endpoint, options = {}) {
      const url = `${this.baseUrl}${endpoint}`;
      const headers = {
        "Content-Type": "application/json",
        "X-WP-Nonce": this.nonce,
      };

      const config = {
        ...options,
        headers: {
          ...headers,
          ...options.headers,
        },
      };

      try {
        const response = await fetch(url, config);
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || "Request failed");
        }

        return data;
      } catch (error) {
        console.error("API Error:", error);
        throw error;
      }
    }

    get(endpoint, params = {}) {
      const query = new URLSearchParams(params).toString();
      const url = query ? `${endpoint}?${query}` : endpoint;
      return this.request(url, { method: "GET" });
    }

    post(endpoint, data = {}) {
      return this.request(endpoint, {
        method: "POST",
        body: JSON.stringify(data),
      });
    }

    put(endpoint, data = {}) {
      return this.request(endpoint, {
        method: "PUT",
        body: JSON.stringify(data),
      });
    }

    delete(endpoint) {
      return this.request(endpoint, { method: "DELETE" });
    }
  }

  // Dashboard Manager
  class DashboardManager {
    constructor(config) {
      this.config = config;
      this.api = new APIClient(config.apiUrl, config.nonce);
      this.currentClient = null;
      this.currentTimeframe = 30;
      this.roomCounts = { problem: 0, solution: 0, offer: 0, sales: 0 };
      this.prospects = { problem: [], solution: [], offer: [] };
      this.campaigns = [];
      this.campaignsLoaded = false;
      this.currentFilters = { problem: "all", solution: "all", offer: "all" };
      this.emailModal = null;

      this.init();
    }

    async init() {
      console.log("🎬 Dashboard init starting...");

      this.attachEventListeners();
      await this.loadInitialData();
      this.initModals();

      console.log("🔧 About to init email modal...");
      this.initEmailModal();

      console.log("📎 About to attach email icon listeners...");
      this.attachEmailIconListeners();

      console.log("⚙️ About to init prospect action listeners...");
      this.initProspectActionListeners();

      console.log("✅ Dashboard init complete");
    }

    attachEventListeners() {
      // Refresh button
      const refreshBtn = document.querySelector(".refresh-btn");
      if (refreshBtn) {
        refreshBtn.addEventListener("click", () => this.refreshData());
      }

      // Client selector
      const clientDropdown = document.querySelector(".client-dropdown");
      if (clientDropdown) {
        clientDropdown.addEventListener("change", (e) => {
          this.currentClient = e.target.value || null;
          this.loadInitialData();
        });
      }

      // Date filter
      const dateFilter = document.querySelector(".date-filter");
      if (dateFilter) {
        dateFilter.addEventListener("change", (e) => {
          this.currentTimeframe = parseInt(e.target.value);
          this.showNotification(
            `Showing data for last ${this.currentTimeframe} days`,
            "info"
          );
        });
      }

      // Chart buttons
      document.addEventListener("click", (e) => {
        if (e.target.closest(".chart-btn")) {
          const room = e.target.closest(".chart-btn").dataset.room;
          this.showChartModal(room);
        }
      });

      // Archive buttons
      document.addEventListener("click", async (e) => {
        if (e.target.closest(".archive-btn")) {
          e.stopPropagation();
          const prospectRow = e.target.closest(".prospect-row");
          const prospectId = prospectRow.dataset.prospectId;
          await this.archiveProspect(prospectId);
        }
      });

      // Handoff buttons
      document.addEventListener("click", async (e) => {
        if (e.target.closest(".handoff-btn")) {
          e.stopPropagation();
          const prospectRow = e.target.closest(".prospect-row");
          const prospectId = prospectRow.dataset.prospectId;
          await this.handoffToSales(prospectId);
        }
      });

      // Prospect row clicks for details
      document.addEventListener("click", (e) => {
        const prospectRow = e.target.closest(".prospect-row");
        if (
          prospectRow &&
          !e.target.closest(".prospect-actions") &&
          !e.target.closest(".email-icons")
        ) {
          const prospectId = prospectRow.dataset.prospectId;
          this.showProspectDetail(prospectId);
        }
      });
    }

    /**
     * Initialize prospect action event listeners
     */
    initProspectActionListeners() {
      // Listen for prospect archived events
      document.addEventListener("rtr:prospect-archived", async (e) => {
        const { room } = e.detail;

        if (room) {
          // Decrease room count
          this.roomCounts[room] = Math.max(0, (this.roomCounts[room] || 0) - 1);

          // Update UI
          const countEl = document.querySelector(
            `.room-count[data-room="${room}"]`
          );
          if (countEl) {
            countEl.textContent = this.roomCounts[room];

            // Add animation
            countEl.style.transition = "transform 0.3s ease";
            countEl.style.transform = "scale(1.1)";
            setTimeout(() => (countEl.style.transform = "scale(1)"), 300);
          }
        }
      });

      // Listen for prospect handoff events
      document.addEventListener("rtr:prospect-handoff", async () => {
        // Decrease offer count
        this.roomCounts.offer = Math.max(0, (this.roomCounts.offer || 0) - 1);

        // Increase sales count
        this.roomCounts.sales = (this.roomCounts.sales || 0) + 1;

        // Update both counts in UI
        ["offer", "sales"].forEach((room) => {
          const countEl = document.querySelector(
            `.room-count[data-room="${room}"]`
          );
          if (countEl) {
            countEl.textContent = this.roomCounts[room];

            // Add animation
            countEl.style.transition = "transform 0.3s ease";
            countEl.style.transform = "scale(1.1)";
            setTimeout(() => (countEl.style.transform = "scale(1)"), 300);
          }
        });
      });
    }

    async loadInitialData() {
      try {
        await Promise.all([this.loadRoomCounts(), this.loadProspectsByRoom()]);
      } catch (error) {
        console.error("Failed to load initial data:", error);
        this.showNotification("Failed to load dashboard data", "error");
      }
    }

    async loadRoomCounts() {
      try {
        const params = {};
        if (this.currentClient) {
          params.campaign_id = this.currentClient;
        }

        const response = await this.api.get("/analytics/room-counts", params);

        if (response.success) {
          this.roomCounts = response.data;
          this.updateRoomCountsUI();
        }
      } catch (error) {
        console.error("Failed to load room counts:", error);
      }
    }

    updateRoomCountsUI() {
      Object.keys(this.roomCounts).forEach((room) => {
        const countEl = document.querySelector(
          `.room-count[data-room="${room}"]`
        );
        if (countEl) {
          countEl.textContent = this.roomCounts[room];

          // Add animation
          countEl.style.transition = "transform 0.3s ease";
          countEl.style.transform = "scale(1.1)";
          setTimeout(() => (countEl.style.transform = "scale(1)"), 300);
        }
      });
    }

    async loadProspectsByRoom() {
      const rooms = ["problem", "solution", "offer"];
      const detailsSection = document.querySelector(".room-details-section");

      if (!detailsSection) return;

      detailsSection.innerHTML = "";

      for (const room of rooms) {
        await this.loadProspectsForRoom(room);
      }
    }

    async loadProspectsForRoom(room) {
      try {
        const params = { room, limit: 100 };
        if (this.currentClient) {
          params.campaign_id = this.currentClient;
        }

        const response = await this.api.get("/prospects", params);

        if (response.success) {
          this.prospects[room] = response.data;
          await this.renderRoomSection(room);
        }
      } catch (error) {
        console.error(`Failed to load ${room} prospects:`, error);
      }
    }

    async renderRoomSection(room) {
      const detailsSection = document.querySelector(".room-details-section");
      if (!detailsSection) return;

      const existingContainer = detailsSection.querySelector(
        `[data-room="${room}"]`
      );
      const container = await this.createRoomDetailContainer(room);

      if (existingContainer) {
        existingContainer.replaceWith(container);
      } else {
        detailsSection.appendChild(container);
      }

      this.attachRoomEventListeners(room);
    }

    async createRoomDetailContainer(room) {
      const container = document.createElement("div");
      container.className = "room-detail-container";
      container.dataset.room = room;

      const roomInfo = this.getRoomInfo(room);
      const prospects = this.getFilteredProspects(room);

      // Load campaigns for dropdown
      await this.loadCampaignsForRoom(room);

      container.innerHTML = `
                <div class="room-detail-header">
                    <h3>${roomInfo.name} <span class="room-count-badge">${
        prospects.length
      }</span></h3>
                    <div class="campaign-filter">
                        <select class="campaign-dropdown" data-room="${room}">
                            <option value="all">All Campaigns</option>
                        </select>
                    </div>
                </div>
                <div class="prospect-list" data-room="${room}">
                    ${this.renderProspectList(prospects, room)}
                </div>
            `;

      // Populate campaign dropdown
      const dropdown = container.querySelector(".campaign-dropdown");
      const campaignIds = [...new Set(prospects.map((p) => p.campaign_id))];
      for (const id of campaignIds) {
        const campaign = this.campaigns.find((c) => c.id === id);
        if (campaign) {
          const option = document.createElement("option");
          option.value = campaign.id;
          option.textContent = campaign.name;
          dropdown.appendChild(option);
        }
      }

      return container;
    }

    async loadCampaignsForRoom(room) {
      if (this.campaignsLoaded) return;

      try {
        const response = await this.api.get("/campaigns");
        if (response.success && response.data) {
          this.campaigns = response.data.map((c) => ({
            id: c.id,
            name: c.campaign_name || c.utm_campaign || `Campaign ${c.id}`,
            utm_campaign: c.utm_campaign,
            client_id: c.client_id,
          }));
          this.campaignsLoaded = true;
        }
      } catch (error) {
        console.error("Failed to load campaigns:", error);
      }
    }

    renderProspectList(prospects, room) {
      if (prospects.length === 0) {
        return `
                    <div style="padding: 40px; text-align: center; color: #999;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No prospects in this room</p>
                    </div>
                `;
      }

      return prospects
        .map((prospect) => this.createProspectRow(prospect, room))
        .join("");
    }

    createProspectRow(prospect, room) {
      const scoreClass = `${room}-score`;
      const showHandoff = room === "offer";
      const contactName = prospect.contact_name || "Unknown Contact";
      const companyName = prospect.company_name || "Unknown Company";
      const campaign = this.campaigns.find(
        (c) => c.id === prospect.campaign_id
      );
      const campaignName = campaign
        ? campaign.name
        : `Campaign ${prospect.campaign_id}`;

      return `
                <div class="prospect-row" data-prospect-id="${
                  prospect.id
                }" data-campaign-id="${prospect.campaign_id}">
                    <div class="prospect-name">
                        <strong>${this.escapeHtml(contactName)}</strong>
                        <div class="company-name">${this.escapeHtml(
                          companyName
                        )}</div>
                        <div class="campaign-tag">${this.escapeHtml(
                          campaignName
                        )}</div>
                    </div>
                    <div class="prospect-metrics">
                        <div class="lead-score">
                            <span>Lead Score: </span>
                            <span class="score-value ${scoreClass}">${
        prospect.lead_score || 0
      }</span>
                        </div>
                        <div class="email-progress">
                            <div class="email-icons">
                                ${this.createEmailIcons(
                                  prospect.email_sequence_position || 0,
                                  prospect
                                )}
                            </div>
                        </div>
                    </div>
                    <div class="prospect-actions">
                        ${
                          showHandoff
                            ? `
                            <button class="handoff-btn ${
                              prospect.lead_score >= 85 ? "handoff-ready" : ""
                            }" 
                                    title="Hand off to Sales">
                                <i class="fas fa-handshake"></i>
                            </button>
                        `
                            : ""
                        }
                        <button class="archive-btn" title="Archive prospect">
                            <i class="fas fa-archive"></i>
                        </button>
                    </div>
                </div>
            `;
    }

    /**
     * Create email sequence icons with proper states
     *
     * @param {number} sequencePosition - Current email sequence position (0-5)
     * @param {Object} prospect - Full prospect object with engagement data
     * @return {string} HTML for email icons
     */
    createEmailIcons(sequencePosition, prospect) {
      const icons = [];

      // Parse engagement data for email statuses
      const emailStatuses = this.parseEmailStatuses(prospect);

      for (let i = 1; i <= 5; i++) {
        let iconClass = "email-not-sent";
        let iconType = "fa-envelope";
        let title = `Email ${i}: Not Sent`;

        // Check if this email has been sent
        if (i <= sequencePosition) {
          // Email has been sent - check for opened status
          const emailStatus = emailStatuses[i] || {};

          if (emailStatus.opened) {
            // Email was opened
            iconClass = "email-opened";
            iconType = "fa-envelope-open-text";
            title = `Email ${i}: Opened`;
          } else if (emailStatus.clicked) {
            // Email was clicked
            iconClass = "email-opened"; // Use same style as opened
            iconType = "fa-mouse-pointer";
            title = `Email ${i}: Clicked`;
          } else {
            // Email was sent but not opened yet
            iconClass = "email-sent";
            iconType = "fa-paper-plane";
            title = `Email ${i}: Sent`;
          }
        } else if (i === sequencePosition + 1) {
          // Next email - ready to generate
          iconClass = "email-pending";
          iconType = "fa-envelope";
          title = `Email ${i}: Click to generate`;
        }
        // else: future emails remain 'email-not-sent'

        icons.push(
          `<i class="fas ${iconType} ${iconClass}" 
                        data-email-number="${i}" 
                        title="${title}"></i>`
        );
      }

      return icons.join("");
    }

    /**
     * Parse email statuses from prospect engagement data
     *
     * @param {Object} prospect - Prospect object
     * @return {Object} Email statuses by number
     */
    parseEmailStatuses(prospect) {
      const statuses = {};

      // Check if engagement_data exists and has emails array
      if (!prospect.engagement_data) {
        return statuses;
      }

      let engagementData;
      try {
        engagementData =
          typeof prospect.engagement_data === "string"
            ? JSON.parse(prospect.engagement_data)
            : prospect.engagement_data;
      } catch (e) {
        console.warn(
          "Failed to parse engagement data for prospect:",
          prospect.id
        );
        return statuses;
      }

      // Extract email statuses
      if (engagementData.emails && Array.isArray(engagementData.emails)) {
        engagementData.emails.forEach((email) => {
          statuses[email.number] = {
            sent: !!email.sent_at,
            opened: !!email.opened_at,
            clicked: !!email.clicked_at,
            status: email.status,
          };
        });
      }

      return statuses;
    }

    getFilteredProspects(room) {
      const allProspects = this.prospects[room] || [];
      const filter = this.currentFilters[room];

      if (filter === "all") return allProspects;
      return allProspects.filter((p) => p.campaign_id === parseInt(filter));
    }

    attachRoomEventListeners(room) {
      const dropdown = document.querySelector(
        `.campaign-dropdown[data-room="${room}"]`
      );
      if (dropdown) {
        dropdown.addEventListener("change", (e) => {
          this.filterProspectsByCampaign(room, e.target.value);
        });
      }
    }

    filterProspectsByCampaign(room, campaignId) {
      this.currentFilters[room] = campaignId;

      const prospectList = document.querySelector(
        `.prospect-list[data-room="${room}"]`
      );
      const countBadge = document.querySelector(
        `[data-room="${room}"] .room-count-badge`
      );

      if (prospectList) {
        const filteredProspects = this.getFilteredProspects(room);
        prospectList.innerHTML = this.renderProspectList(
          filteredProspects,
          room
        );

        if (countBadge) {
          countBadge.textContent = filteredProspects.length;
        }
      }
    }

    getRoomInfo(room) {
      const info = {
        problem: { name: "Problem Room", color: "#e74c3c" },
        solution: { name: "Solution Room", color: "#f39c12" },
        offer: { name: "Offer Room", color: "#27ae60" },
        sales: { name: "Sales Room", color: "#9b59b6" },
      };
      return info[room] || info.problem;
    }

    async archiveProspect(prospectId) {
      if (
        !confirm(
          "Archive this prospect? They will be removed from the pipeline."
        )
      ) {
        return;
      }

      try {
        const response = await this.api.post(
          `/prospects/${prospectId}/archive`,
          {
            reason: "Archived from dashboard",
          }
        );

        if (response.success) {
          this.removeProspect(prospectId, true);
          this.showNotification("Prospect archived successfully", "success");
          await this.loadRoomCounts();
        }
      } catch (error) {
        console.error("Failed to archive prospect:", error);
        this.showNotification("Failed to archive prospect", "error");
      }
    }

    async handoffToSales(prospectId) {
      const notes = prompt("Add notes for sales team (optional):");
      if (notes === null) return;

      try {
        const response = await this.api.post(
          `/prospects/${prospectId}/handoff-sales`,
          {
            notes: notes,
          }
        );

        if (response.success) {
          const row = document.querySelector(
            `.prospect-row[data-prospect-id="${prospectId}"]`
          );
          if (row) {
            row.style.transition = "all 0.5s ease";
            row.style.opacity = "0";
            row.style.transform = "translateX(20px)";
            row.style.background = "linear-gradient(90deg, #d4edda, #c3e6cb)";
          }

          setTimeout(() => {
            this.removeProspect(prospectId, false);
          }, 500);

          this.showNotification("Handed off to sales successfully", "success");
          await this.loadRoomCounts();
        }
      } catch (error) {
        console.error("Failed to hand off prospect:", error);
        this.showNotification("Failed to hand off prospect", "error");
      }
    }

    removeProspect(prospectId, animated = true) {
      const row = document.querySelector(
        `.prospect-row[data-prospect-id="${prospectId}"]`
      );
      if (!row) return;

      if (animated) {
        row.style.transition = "all 0.3s ease";
        row.style.opacity = "0";
        row.style.transform = "translateX(-20px)";
        setTimeout(() => this.removeProspectElement(row, prospectId), 300);
      } else {
        this.removeProspectElement(row, prospectId);
      }
    }

    removeProspectElement(row, prospectId) {
      const container = row.closest(".room-detail-container");
      const room = container?.dataset.room;

      row.remove();

      if (container && room) {
        const badge = container.querySelector(".room-count-badge");
        const remainingRows = container.querySelectorAll(".prospect-row");

        if (badge) badge.textContent = remainingRows.length;

        if (this.prospects[room]) {
          this.prospects[room] = this.prospects[room].filter(
            (p) => p.id !== parseInt(prospectId)
          );
        }

        if (remainingRows.length === 0) {
          const prospectList = container.querySelector(".prospect-list");
          if (prospectList) {
            prospectList.innerHTML = this.renderProspectList([], room);
          }
        }
      }
    }

    async refreshData() {
      const refreshBtn = document.querySelector(".refresh-btn");
      const icon = refreshBtn?.querySelector("i");

      if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.classList.add("loading");
      }
      if (icon) {
        icon.style.animation = "spin 1s linear infinite";
      }

      try {
        await this.loadInitialData();
        this.showNotification("Data refreshed successfully", "success");
      } catch (error) {
        console.error("Failed to refresh data:", error);
        this.showNotification("Failed to refresh data", "error");
      } finally {
        if (refreshBtn) {
          refreshBtn.disabled = false;
          refreshBtn.classList.remove("loading");
        }
        if (icon) {
          icon.style.animation = "";
        }
      }
    }

    showProspectDetail(prospectId) {
      let prospect = null;
      const rooms = ["problem", "solution", "offer"];

      for (const room of rooms) {
        prospect = this.prospects[room].find(
          (p) => p.id === parseInt(prospectId)
        );
        if (prospect) break;
      }

      if (!prospect) {
        console.error("Prospect not found:", prospectId);
        return;
      }

      const modal = document.getElementById("email-details-modal");
      const title = document.getElementById("email-modal-title");
      const content = document.getElementById("email-modal-content");

      if (!modal || !title || !content) return;

      title.textContent = `${prospect.contact_name || "Prospect Details"}`;

      content.innerHTML = `
                <div class="prospect-detail-view">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <h4 style="margin-bottom: 15px; color: #2c435d;">Contact Information</h4>
                            <p><strong>Name:</strong> ${this.escapeHtml(
                              prospect.contact_name || "Unknown"
                            )}</p>
                            <p><strong>Company:</strong> ${this.escapeHtml(
                              prospect.company_name || "Unknown"
                            )}</p>
                            <p><strong>Email:</strong> ${this.escapeHtml(
                              prospect.contact_email || "Not provided"
                            )}</p>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 15px; color: #2c435d;">Lead Metrics</h4>
                            <p><strong>Lead Score:</strong> ${
                              prospect.lead_score || 0
                            }</p>
                            <p><strong>Current Room:</strong> ${this.capitalizeFirst(
                              prospect.current_room
                            )}</p>
                            <p><strong>Days in Room:</strong> ${
                              prospect.days_in_room || 0
                            }</p>
                            <p><strong>Last Activity:</strong> ${this.formatDate(
                              prospect.updated_at
                            )}</p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px; color: #2c435d;">Email Sequence Progress</h4>
                        <div class="email-icons" style="display: flex; gap: 8px; font-size: 1.5rem;">
                            ${this.createEmailIcons(
                              prospect.email_sequence_position || 0
                            )}
                        </div>
                        <p style="margin-top: 10px; color: #666; font-size: 0.9rem;">
                            ${
                              prospect.email_sequence_position || 0
                            } of 5 emails sent
                        </p>
                    </div>
                    
                    ${this.renderRecentActivity(prospect)}
                </div>
            `;

      modal.style.display = "flex";
    }

    renderRecentActivity(prospect) {
      let engagement = { recent_pages: [], page_view_count: 0 };

      if (prospect.engagement_data) {
        try {
          engagement =
            typeof prospect.engagement_data === "string"
              ? JSON.parse(prospect.engagement_data)
              : prospect.engagement_data;
        } catch (e) {
          console.error("Failed to parse engagement data:", e);
        }
      }

      if (!engagement.recent_pages || engagement.recent_pages.length === 0) {
        return `
                    <div>
                        <h4 style="margin-bottom: 15px; color: #2c435d;">Recent Activity</h4>
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; text-align: center; color: #999;">
                            No recent activity recorded
                        </div>
                    </div>
                `;
      }

      const recentPages = engagement.recent_pages.slice(0, 5);

      return `
                <div>
                    <h4 style="margin-bottom: 15px; color: #2c435d;">Recent Activity</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        ${recentPages
                          .map(
                            (page) => `
                            <p style="margin: 5px 0; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-eye" style="color: #4294cc;"></i>
                                <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    ${this.escapeHtml(
                                      page.title || page.url || "Unknown page"
                                    )}
                                </span>
                            </p>
                        `
                          )
                          .join("")}
                        <p style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; color: #666; font-size: 0.9rem;">
                            <strong>${
                              engagement.page_view_count || 0
                            }</strong> total page views
                        </p>
                    </div>
                </div>
            `;
    }

    initModals() {
      document.querySelectorAll(".close-modal").forEach((btn) => {
        btn.addEventListener("click", () => {
          const modal = btn.closest(".modal");
          if (modal) modal.style.display = "none";
        });
      });

      document.querySelectorAll(".modal").forEach((modal) => {
        modal.addEventListener("click", (e) => {
          if (e.target === modal) modal.style.display = "none";
        });
      });

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          document.querySelectorAll(".modal").forEach((modal) => {
            modal.style.display = "none";
          });
        }
      });
    }

    showChartModal(room) {
      const modal = document.getElementById("chart-modal");
      const title = document.getElementById("chart-modal-title");

      if (modal && title) {
        const roomInfo = this.getRoomInfo(room);
        title.textContent = `${roomInfo.name} - Campaign Analytics`;
        modal.style.display = "flex";

        setTimeout(() => this.createPlaceholderChart(room), 100);
      }
    }

    createPlaceholderChart(room) {
      const canvas = document.getElementById("campaign-chart");
      if (!canvas || !window.Chart) return;

      const ctx = canvas.getContext("2d");
      if (canvas.chart) canvas.chart.destroy();

      const roomInfo = this.getRoomInfo(room);

      canvas.chart = new Chart(ctx, {
        type: "bar",
        data: {
          labels: ["Campaign 1", "Campaign 2", "Campaign 3"],
          datasets: [
            {
              label: "Prospects",
              data: [12, 19, 8],
              backgroundColor: roomInfo.color + "80",
              borderColor: roomInfo.color,
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { y: { beginAtZero: true } },
        },
      });

      document.getElementById("total-prospects").textContent = "39";
      document.getElementById("active-campaigns").textContent = "3";
      document.getElementById("top-campaign").textContent = "Campaign 2";
    }

    /**
     * Initialize email modal manager
     */
    initEmailModal() {
      // Set up event listeners IMMEDIATELY (before async imports)
      document.addEventListener("rtr:generate-email", async (e) => {
        const { prospectId, room, emailNumber, prospectName } = e.detail;

        console.log("🚀 Generate email event received:", {
          prospectId,
          room,
          emailNumber,
          prospectName,
        });

        // Wait for emailModal to be loaded
        if (!this.emailModal) {
          console.warn("Email modal manager not loaded yet, waiting...");
          await new Promise((resolve) => {
            const checkInterval = setInterval(() => {
              if (this.emailModal) {
                clearInterval(checkInterval);
                resolve();
              }
            }, 100);
          });
        }

        // Call the email modal manager to generate and show the email
        await this.emailModal.generateEmail(prospectId, room, emailNumber);
      });

      document.addEventListener("rtr:show-email-history", async (e) => {
        const { prospectId, emailNumber, prospectName } = e.detail;

        console.log("📧 Show email history event received:", {
          prospectId,
          emailNumber,
          prospectName,
        });

        // Wait for emailHistory to be loaded
        if (!this.emailHistory) {
          console.warn("Email history manager not loaded yet, waiting...");
          await new Promise((resolve) => {
            const checkInterval = setInterval(() => {
              if (this.emailHistory) {
                clearInterval(checkInterval);
                resolve();
              }
            }, 100);
          });
        }

        // Call the email history manager to show the modal
        await this.emailHistory.showEmailHistory(prospectId, emailNumber);
      });

      // Now load the modules asynchronously
      import("./modules/email-modal-manager.js")
        .then((module) => {
          const EmailModalManager = module.default;

          // Pass APIClient class and config to email modal
          this.emailModal = new EmailModalManager(APIClient, this.config);

          // Listen for notification events from email modal
          document.addEventListener("rtr:notification", (e) => {
            this.showNotification(e.detail.message, e.detail.type);
          });

          console.log("Email modal manager initialized");
        })
        .catch((error) => {
          console.error("Failed to load email modal manager:", error);
        });

      // Initialize email history manager
      import("./modules/email-history-manager.js")
        .then((module) => {
          const EmailHistoryManager = module.default;

          // Pass API client and config
          this.emailHistory = new EmailHistoryManager(this.api, this.config);

          console.log("Email history manager initialized");
        })
        .catch((error) => {
          console.error("Failed to load email history manager:", error);
        });
    }

    attachEmailIconListeners() {
      console.log("🎯 Setting up email icon listeners...");

      // Use event delegation for dynamically added prospects
      document.addEventListener("click", async (e) => {
        const emailIcon = e.target.closest(".email-icons i");

        if (!emailIcon) return;

        console.log("📧 Email icon clicked:", emailIcon);

        // Don't trigger if email is in generating state
        if (emailIcon.classList.contains("email-generating")) {
          console.log("⏳ Email is generating, ignoring click");
          return;
        }

        e.stopPropagation();

        const prospectRow = emailIcon.closest(".prospect-row");
        if (!prospectRow) {
          console.log("❌ No prospect row found");
          return;
        }

        const prospectId = parseInt(prospectRow.dataset.prospectId);
        const emailNumber = parseInt(emailIcon.dataset.emailNumber);

        console.log("📊 Prospect data:", { prospectId, emailNumber });

        // Try to find prospect in memory first
        let prospect = this.findProspectById(prospectId);

        // If not in memory, load from API
        if (!prospect) {
          console.warn(
            `Prospect ${prospectId} not in memory, fetching from API...`
          );

          try {
            const response = await this.api.get(`/prospects/${prospectId}`);

            if (response.success && response.data) {
              prospect = response.data;
              console.log("✓ Loaded prospect from API:", prospect);
            } else {
              console.error("API returned unsuccessful response:", response);
              this.showNotification("Unable to load prospect details", "error");
              return;
            }
          } catch (error) {
            console.error("Failed to load prospect from API:", error);
            this.showNotification(
              "Error loading prospect: " + error.message,
              "error"
            );
            return;
          }
        }

        // Determine action based on icon state
        const isSent = emailIcon.classList.contains("email-sent");
        const isOpened = emailIcon.classList.contains("email-opened");
        const isPending = emailIcon.classList.contains("email-pending");
        const isReady = emailIcon.classList.contains("email-ready");
        const isNotSent = emailIcon.classList.contains("email-not-sent");

        console.log("🎨 Icon states:", {
          isSent,
          isOpened,
          isPending,
          isReady,
          isNotSent,
        });

        if (isSent || isOpened) {
          console.log("📨 Dispatching show-email-history event");

          const event = new CustomEvent("rtr:show-email-history", {
            detail: {
              prospectId: prospectId,
              emailNumber: emailNumber,
              prospectName: prospect.contact_name || prospect.company_name,
            },
            bubbles: true,
          });

          document.dispatchEvent(event);
          console.log("✓ Event dispatched:", event);
        } else if (isPending || isReady || isNotSent) {
          console.log("🚀 Dispatching generate-email event");

          const event = new CustomEvent("rtr:generate-email", {
            detail: {
              prospectId: prospectId,
              room: prospect.current_room,
              emailNumber: emailNumber,
              prospectName: prospect.contact_name || prospect.company_name,
            },
            bubbles: true,
          });

          document.dispatchEvent(event);
          console.log("✓ Event dispatched:", event);
        } else {
          console.log(
            "⚠️ No matching icon state found - email icon might not have proper classes"
          );
          console.log("Icon classes:", emailIcon.className);
        }
      });

      console.log("✅ Email icon listeners attached");
    }

    /**
     * Find prospect by ID across all rooms
     */
    findProspectById(prospectId) {
      const rooms = ["problem", "solution", "offer"];

      for (const room of rooms) {
        const prospect = this.prospects[room].find((p) => p.id === prospectId);
        if (prospect) return prospect;
      }

      return null;
    }

    showNotification(message, type = "info") {
      const notification = document.createElement("div");
      notification.className = `notification notification-${type}`;
      notification.textContent = message;
      notification.style.cssText = `
                position: fixed; top: 20px; right: 20px; padding: 15px 20px;
                background: ${
                  type === "success"
                    ? "#27ae60"
                    : type === "error"
                    ? "#e74c3c"
                    : "#4294cc"
                };
                color: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000; font-weight: 500; opacity: 0; transform: translateX(100%);
                transition: all 0.3s ease;
            `;

      document.body.appendChild(notification);
      setTimeout(() => {
        notification.style.opacity = "1";
        notification.style.transform = "translateX(0)";
      }, 10);
      setTimeout(() => {
        notification.style.opacity = "0";
        notification.style.transform = "translateX(100%)";
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }

    capitalizeFirst(str) {
      if (!str) return "";
      return str.charAt(0).toUpperCase() + str.slice(1);
    }

    formatDate(dateStr) {
      if (!dateStr) return "Never";

      const date = new Date(dateStr);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);

      if (diffMins < 60) return `${diffMins} minutes ago`;
      if (diffHours < 24) return `${diffHours} hours ago`;
      if (diffDays < 7) return `${diffDays} days ago`;

      return date.toLocaleDateString();
    }

    escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text || "";
      return div.innerHTML;
    }
  }

  // Initialize
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  function init() {
    if (typeof RTR_CONFIG === "undefined") {
      console.error("RTR_CONFIG not found");
      return;
    }

    window.rtrDashboard = new DashboardManager(RTR_CONFIG);
    console.log("Reading the Room Dashboard initialized");
  }
})();
