/**
 * Scoring Rules Manager (Global Configuration)
 * 
 * Handles global scoring rules configuration with multi-room support
 * Follows Campaign Builder architecture
 * 
 * @package DirectReach
 * @subpackage RTR_Scoring_System
 * @since 2.1.0
 */

class ScoringRulesManager {
    constructor(config) {
        this.config = config;
        // Ensure strings object exists even if localization fails
        this.config.strings = this.config.strings || {
            saveSuccess: 'Rules saved successfully',
            saveError: 'Failed to save rules',
            validationError: 'Please fix validation errors before saving',
            resetConfirm: 'Reset rules to global defaults? This cannot be undone.'
        };
        this.apiUrl = config.apiUrl.endsWith('/') ? config.apiUrl : config.apiUrl + '/';
        this.nonce = config.nonce;
        this.currentRoom = 'problem';
        this.rules = {
            problem: config.globalRules.problem || {},
            solution: config.globalRules.solution || {},
            offer: config.globalRules.offer || {}
        };
        this.industries = config.industries || {};
        this.industryModal = null;
        this.currentIndustryTarget = null;
        
        this.init();
    }
    
    /**
     * Initialize the manager
     */
    init() {
        this.attachEventListeners();
        this.renderAllRooms();
        this.initIndustryModal();
        this.initValueSelectorModal();
    }
    
    /**
     * Attach all event listeners
     */
    attachEventListeners() {
        // Room tab switching
        document.querySelectorAll('.room-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const room = e.currentTarget.dataset.room;
                this.switchRoom(room);
            });
        });
        
        // Form submissions
        ['problem', 'solution', 'offer'].forEach(room => {
            const form = document.getElementById(`${room}-rules-form`);
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.saveRules(room);
                });
            }
            
            // Reset buttons
            const resetBtn = document.getElementById(`reset-${room}-btn`);
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    this.resetRules(room);
                });
            }
        });
    }
    
    /**
     * Switch active room tab
     */
    switchRoom(room) {
        this.currentRoom = room;
        
        // Update tabs
        document.querySelectorAll('.room-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.room === room);
        });
        
        // Update content (must override inline styles)
        document.querySelectorAll('.room-content').forEach(content => {
            const isActive = content.dataset.roomContent === room;
            content.classList.toggle('active', isActive);
            content.style.display = isActive ? 'block' : 'none'; // Override inline style
        });
    }
    
    /**
     * Render all rooms
     */
    renderAllRooms() {
        this.renderProblemRoom();
        this.renderSolutionRoom();
        this.renderOfferRoom();
    }
    
    /**
     * Render Problem Room rules
     */
    renderProblemRoom() {
        const container = document.getElementById('problem-rules');
        const rules = this.rules.problem;
        
        container.innerHTML = `
            ${this.renderRule('revenue', 'Revenue', rules.revenue || this.getDefaultRule('revenue'), 'problem', [
                'Under $1M', '$1M-$5M', '$5M-$10M', '$10M-$50M', '$50M-$100M', 'Over $100M'
            ])}
            
            ${this.renderRule('company_size', 'Company Size', rules.company_size || this.getDefaultRule('company_size'), 'problem', [
                '1-10', '11-50', '51-200', '201-500', '501-1000', '1001-5000', '5000+'
            ])}
            
            ${this.renderIndustryRule('industry_alignment', 'Industry Alignment', rules.industry_alignment || this.getDefaultRule('industry_alignment'), 'problem')}
            
            ${this.renderRule('target_states', 'Target States', rules.target_states || this.getDefaultRule('target_states'), 'problem', [
                'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
            ])}
            
            ${this.renderSimpleRule('visited_target_pages', 'Visited Target Page(s)', rules.visited_target_pages || this.getDefaultRule('visited_target_pages'), 'problem')}
            
            ${this.renderSimpleRule('multiple_visits', 'More than 2 Visits', rules.multiple_visits || this.getDefaultRule('multiple_visits'), 'problem', {
                showMinVisits: true
            })}
            
            ${this.renderRoleMatchRule('role_match', 'Role/Job Title Match', rules.role_match || this.getDefaultRule('role_match'), 'problem')}
            
            ${this.renderThresholdRule('minimum_threshold', 'Minimum Threshold', rules.minimum_threshold || this.getDefaultRule('minimum_threshold'), 'problem')}
        `;
        
        this.attachRuleListeners('problem');
    }
    
    /**
     * Render Solution Room rules
     */
    renderSolutionRoom() {
        const container = document.getElementById('solution-rules');
        const rules = this.rules.solution;
        
        container.innerHTML = `
            ${this.renderSimpleRule('email_open', 'Email Open', rules.email_open || this.getDefaultRule('email_open', 'solution'), 'solution')}
            
            ${this.renderSimpleRule('email_click', 'Email Click', rules.email_click || this.getDefaultRule('email_click', 'solution'), 'solution')}
            
            ${this.renderSimpleRule('email_multiple_click', 'Email Multiple Click', rules.email_multiple_click || this.getDefaultRule('email_multiple_click', 'solution'), 'solution', {
                showMinClicks: true
            })}
            
            ${this.renderSimpleRule('page_visit', 'Page Visit', rules.page_visit || this.getDefaultRule('page_visit', 'solution'), 'solution', {
                showPointsPerVisit: true,
                showMaxPoints: true
            })}
            
            ${this.renderKeyPageRule('key_page_visit', 'Key Page Visit', rules.key_page_visit || this.getDefaultRule('key_page_visit', 'solution'), 'solution')}
            
            ${this.renderSimpleRule('ad_engagement', 'Ad Engagement', rules.ad_engagement || this.getDefaultRule('ad_engagement', 'solution'), 'solution', {
                showUtmSources: true
            })}
        `;
        
        this.attachRuleListeners('solution');
    }
    
    /**
     * Render Offer Room rules
     */
    renderOfferRoom() {
        const container = document.getElementById('offer-rules');
        const rules = this.rules.offer;
        
        container.innerHTML = `
            ${this.renderSimpleRule('demo_request', 'Demo Request', rules.demo_request || this.getDefaultRule('demo_request', 'offer'), 'offer', {
                showPatterns: true
            })}
            
            ${this.renderSimpleRule('contact_form', 'Contact Form Fill', rules.contact_form || this.getDefaultRule('contact_form', 'offer'), 'offer')}
            
            ${this.renderSimpleRule('pricing_page', 'Pricing Page Visit', rules.pricing_page || this.getDefaultRule('pricing_page', 'offer'), 'offer', {
                showPageUrls: true
            })}
            
            ${this.renderSimpleRule('pricing_question', 'Ask Pricing Question', rules.pricing_question || this.getDefaultRule('pricing_question', 'offer'), 'offer')}
            
            ${this.renderSimpleRule('partner_referral', 'Partner Referral', rules.partner_referral || this.getDefaultRule('partner_referral', 'offer'), 'offer', {
                showUtmSources: true
            })}
            
            ${this.renderSimpleRule('webinar_attendance', 'Webinar Attendance', rules.webinar_attendance || this.getDefaultRule('webinar_attendance', 'offer'), 'offer')}
        `;
        
        this.attachRuleListeners('offer');
    }
    
    /**
     * Render a standard rule with values
     */
    renderRule(ruleKey, label, rule, room, presetValues = []) {
        const enabled = rule.enabled !== false;
        const points = rule.points || 0;
        const values = rule.values || [];
        
        return `
            <div class="rule-card ${!enabled ? 'disabled' : ''}" data-rule="${ruleKey}">
                <div class="rule-header">
                    <div class="rule-title">
                        <h4>${label}</h4>
                        <span class="rule-points">+${points} points</span>
                    </div>
                    <div class="rule-toggle">
                        <span class="toggle-label">Enabled</span>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                data-room="${room}" 
                                data-rule="${ruleKey}" 
                                data-field="enabled" 
                                ${enabled ? 'checked' : ''} />
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="rule-body">
                    <div class="rule-config">
                        <div class="config-group">
                            <label>Points</label>
                            <input type="number" 
                                data-room="${room}" 
                                data-rule="${ruleKey}" 
                                data-field="points" 
                                value="${points}" 
                                min="0" 
                                max="100" />
                        </div>
                    </div>
                    <div class="rule-values">
                        <div class="values-header">
                            <h5>Selected Values (${values.length})</h5>
                            <button type="button" 
                                    class="btn-value-selector" 
                                    data-room="${room}" 
                                    data-rule="${ruleKey}"
                                    data-label="${label}">
                                <i class="fas fa-plus-circle"></i>
                                Select ${label}
                            </button>
                        </div>
                        <div class="values-list" data-room="${room}" data-rule="${ruleKey}">
                            ${values.length > 0 ? values.map(v => `
                                <div class="value-chip">
                                    <span>${v}</span>
                                    <button type="button" data-value="${v}">&times;</button>
                                </div>
                            `).join('') : '<div class="empty-values">No values selected</div>'}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Render industry rule with modal selector
     */
    renderIndustryRule(ruleKey, label, rule, room) {
        const enabled = rule.enabled !== false;
        const points = rule.points || 0;
        const values = rule.values || [];
        
        return `
            <div class="rule-card ${!enabled ? 'disabled' : ''}" data-rule="${ruleKey}">
                <div class="rule-header">
                    <div class="rule-title">
                        <h4>${label}</h4>
                        <span class="rule-points">+${points} points</span>
                    </div>
                    <div class="rule-toggle">
                        <span class="toggle-label">Enabled</span>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="enabled" 
                                   ${enabled ? 'checked' : ''} />
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="rule-body">
                    <div class="rule-config">
                        <div class="config-group">
                            <label>Points</label>
                            <input type="number" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="points" 
                                   value="${points}" 
                                   min="0" 
                                   max="100" />
                        </div>
                    </div>
                    <div class="rule-values">
                        <div class="values-header">
                            <h5>Selected Industries</h5>
                            <button type="button" 
                                    class="btn-industry-selector" 
                                    data-room="${room}" 
                                    data-rule="${ruleKey}">
                                <i class="fas fa-industry"></i>
                                Select Industries
                            </button>
                        </div>
                        <div class="values-list" data-room="${room}" data-rule="${ruleKey}">
                            ${values.length > 0 ? values.map(v => `
                                <div class="value-chip">
                                    <span>${v.replace('|', ' → ')}</span>
                                    <button type="button" data-value="${v}">&times;</button>
                                </div>
                            `).join('') : '<div class="empty-values">No industries selected</div>'}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Render simple rule without values
     */
    renderSimpleRule(ruleKey, label, rule, room, options = {}) {
        const enabled = rule.enabled !== false;
        const points = rule.points || 0;
        
        let configHtml = `
            <div class="config-group">
                <label>Points</label>
                <input type="number" 
                       data-room="${room}" 
                       data-rule="${ruleKey}" 
                       data-field="points" 
                       value="${points}" 
                       min="0" 
                       max="100" />
            </div>
        `;
        
        if (options.showMinVisits) {
            configHtml += `
                <div class="config-group">
                    <label>Minimum Visits</label>
                    <input type="number" 
                           data-room="${room}" 
                           data-rule="${ruleKey}" 
                           data-field="minimum_visits" 
                           value="${rule.minimum_visits || 2}" 
                           min="1" 
                           max="10" />
                </div>
            `;
        }
        
        if (options.showMinClicks) {
            configHtml += `
                <div class="config-group">
                    <label>Minimum Clicks</label>
                    <input type="number" 
                           data-room="${room}" 
                           data-rule="${ruleKey}" 
                           data-field="minimum_clicks" 
                           value="${rule.minimum_clicks || 2}" 
                           min="1" 
                           max="10" />
                </div>
            `;
        }
        
        if (options.showPointsPerVisit) {
            configHtml += `
                <div class="config-group">
                    <label>Points Per Visit</label>
                    <input type="number" 
                           data-room="${room}" 
                           data-rule="${ruleKey}" 
                           data-field="points_per_visit" 
                           value="${rule.points_per_visit || 3}" 
                           min="1" 
                           max="20" />
                </div>
            `;
        }
        
        if (options.showMaxPoints) {
            configHtml += `
                <div class="config-group">
                    <label>Max Points</label>
                    <input type="number" 
                           data-room="${room}" 
                           data-rule="${ruleKey}" 
                           data-field="max_points" 
                           value="${rule.max_points || 15}" 
                           min="0" 
                           max="100" />
                </div>
            `;
        }
        
        return `
            <div class="rule-card ${!enabled ? 'disabled' : ''}" data-rule="${ruleKey}">
                <div class="rule-header">
                    <div class="rule-title">
                        <h4>${label}</h4>
                        <span class="rule-points">+${points} points</span>
                    </div>
                    <div class="rule-toggle">
                        <span class="toggle-label">Enabled</span>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="enabled" 
                                   ${enabled ? 'checked' : ''} />
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="rule-body">
                    <div class="rule-config">
                        ${configHtml}
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Render key page rule
     */
    renderKeyPageRule(ruleKey, label, rule, room) {
        const enabled = rule.enabled !== false;
        const points = rule.points || 0;
        const keyPages = rule.key_pages || [];
        
        return `
            <div class="rule-card ${!enabled ? 'disabled' : ''}" data-rule="${ruleKey}">
                <div class="rule-header">
                    <div class="rule-title">
                        <h4>${label}</h4>
                        <span class="rule-points">+${points} points</span>
                    </div>
                    <div class="rule-toggle">
                        <span class="toggle-label">Enabled</span>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="enabled" 
                                   ${enabled ? 'checked' : ''} />
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="rule-body">
                    <div class="rule-config">
                        <div class="config-group">
                            <label>Points</label>
                            <input type="number" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="points" 
                                   value="${points}" 
                                   min="0" 
                                   max="100" />
                        </div>
                    </div>
                    <div class="rule-values">
                        <div class="values-header">
                            <h5>Key Pages (paths)</h5>
                        </div>
                        <div class="values-list" data-room="${room}" data-rule="${ruleKey}">
                            ${keyPages.length > 0 ? keyPages.map(v => `
                                <div class="value-chip">
                                    <span>${v}</span>
                                    <button type="button" data-value="${v}">&times;</button>
                                </div>
                            `).join('') : '<div class="empty-values">No key pages defined</div>'}
                        </div>
                        <div class="value-input-group">
                            <input type="text" 
                                   placeholder="/pricing" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-add-value />
                            <button type="button" data-room="${room}" data-rule="${ruleKey}" data-add-btn>
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                        <small class="help-text">Enter page paths like /pricing or /demo</small>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Render role match rule
     */
    renderRoleMatchRule(ruleKey, label, rule, room) {
        const enabled = rule.enabled !== false;
        const points = rule.points || 0;
        const targetRoles = rule.target_roles || {
            decision_makers: [],
            technical: [],
            marketing: [],
            sales: []
        };
        
        return `
            <div class="rule-card ${!enabled ? 'disabled' : ''}" data-rule="${ruleKey}">
                <div class="rule-header">
                    <div class="rule-title">
                        <h4>${label}</h4>
                        <span class="rule-points">+${points} points</span>
                    </div>
                    <div class="rule-toggle">
                        <span class="toggle-label">Enabled</span>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="enabled" 
                                   ${enabled ? 'checked' : ''} />
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="rule-body">
                    <div class="rule-config">
                        <div class="config-group">
                            <label>Points</label>
                            <input type="number" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="points" 
                                   value="${points}" 
                                   min="0" 
                                   max="100" />
                        </div>
                        <div class="config-group">
                            <label>Match Type</label>
                            <select data-room="${room}" 
                                    data-rule="${ruleKey}" 
                                    data-field="match_type">
                                <option value="contains" ${rule.match_type === 'contains' ? 'selected' : ''}>Contains</option>
                                <option value="exact" ${rule.match_type === 'exact' ? 'selected' : ''}>Exact Match</option>
                            </select>
                        </div>
                    </div>
                    <div class="rule-values">
                        <div class="values-header">
                            <h5>Role Keywords</h5>
                        </div>
                        <small class="help-text">Common roles are pre-filled. Add custom keywords as needed.</small>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Render threshold rule
     */
    renderThresholdRule(ruleKey, label, rule, room) {
        const enabled = rule.enabled !== false;
        const requiredScore = rule.required_score || 20;
        
        return `
            <div class="rule-card ${!enabled ? 'disabled' : ''}" data-rule="${ruleKey}">
                <div class="rule-header">
                    <div class="rule-title">
                        <h4>${label}</h4>
                    </div>
                    <div class="rule-toggle">
                        <span class="toggle-label">Enabled</span>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="enabled" 
                                   ${enabled ? 'checked' : ''} />
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="rule-body">
                    <div class="rule-config">
                        <div class="config-group">
                            <label>Required Score</label>
                            <input type="number" 
                                   data-room="${room}" 
                                   data-rule="${ruleKey}" 
                                   data-field="required_score" 
                                   value="${requiredScore}" 
                                   min="0" 
                                   max="100" />
                            <small class="help-text">Minimum points needed to qualify for Problem Room</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Get default rule structure
     */
    getDefaultRule(ruleKey, room = 'problem') {
        const defaults = {
            problem: {
                revenue: { enabled: true, points: 10, values: [] },
                company_size: { enabled: true, points: 10, values: [] },
                industry_alignment: { enabled: true, points: 15, values: [] },
                target_states: { enabled: true, points: 5, values: [] },
                visited_target_pages: { enabled: false, points: 10, max_points: 30 },
                multiple_visits: { enabled: true, points: 5, minimum_visits: 2 },
                role_match: { enabled: false, points: 5, match_type: 'contains', target_roles: {} },
                minimum_threshold: { enabled: true, required_score: 20 }
            },
            solution: {
                email_open: { enabled: true, points: 2 },
                email_click: { enabled: true, points: 5 },
                email_multiple_click: { enabled: true, points: 8, minimum_clicks: 2 },
                page_visit: { enabled: true, points_per_visit: 3, max_points: 15 },
                key_page_visit: { enabled: true, points: 10, key_pages: [] },
                ad_engagement: { enabled: true, points: 5 }
            },
            offer: {
                demo_request: { enabled: true, points: 25 },
                contact_form: { enabled: true, points: 20 },
                pricing_page: { enabled: true, points: 15 },
                pricing_question: { enabled: true, points: 20 },
                partner_referral: { enabled: true, points: 15 },
                webinar_attendance: { enabled: false, points: 0 }
            }
        };
        
        return defaults[room][ruleKey] || { enabled: false, points: 0 };
    }
    
    /**
     * Attach rule-specific listeners
     */
    attachRuleListeners(room) {
        const container = document.getElementById(`${room}-rules`);
        
        // Toggle switches
        container.querySelectorAll('input[type="checkbox"][data-field="enabled"]').forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                const ruleKey = e.target.dataset.rule;
                const card = e.target.closest('.rule-card');
                card.classList.toggle('disabled', !e.target.checked);
                this.updateRuleField(room, ruleKey, 'enabled', e.target.checked);
            });
        });
        
        // Number inputs
        container.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', (e) => {
                const ruleKey = e.target.dataset.rule;
                const field = e.target.dataset.field;
                this.updateRuleField(room, ruleKey, field, parseInt(e.target.value));
                
                // Update points badge if it's the points field
                if (field === 'points') {
                    const card = e.target.closest('.rule-card');
                    const badge = card.querySelector('.rule-points');
                    if (badge) {
                        badge.textContent = `+${e.target.value} points`;
                    }
                }
            });
        });
        
        // Select inputs
        container.querySelectorAll('select[data-field]').forEach(select => {
            select.addEventListener('change', (e) => {
                const ruleKey = e.target.dataset.rule;
                const field = e.target.dataset.field;
                this.updateRuleField(room, ruleKey, field, e.target.value);
            });
        });
        
        // Add value buttons
        container.querySelectorAll('[data-add-btn]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const ruleKey = e.currentTarget.dataset.rule;
                const input = container.querySelector(`[data-room="${room}"][data-rule="${ruleKey}"][data-add-value]`);
                const value = input.value.trim();
                
                if (value) {
                    this.addValue(room, ruleKey, value);
                    input.value = '';
                    if (input.tagName === 'SELECT') {
                        input.selectedIndex = 0;
                    }
                }
            });
        });
        
        // Remove value buttons
        container.querySelectorAll('.value-chip button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const value = e.currentTarget.dataset.value;
                const valuesList = e.currentTarget.closest('.values-list');
                const ruleKey = valuesList.dataset.rule;
                this.removeValue(room, ruleKey, value);
            });
        });
        
        // Industry selector buttons
        container.querySelectorAll('.btn-industry-selector').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const ruleKey = e.currentTarget.dataset.rule;
                this.openIndustryModal(room, ruleKey);
            });
        });

        // Value selector buttons (Revenue, Company Size, States)
        container.querySelectorAll('.btn-value-selector').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const ruleKey = e.currentTarget.dataset.rule;
                const label = e.currentTarget.dataset.label;
                const availableValues = this.getAvailableValuesForRule(ruleKey);
                this.openValueSelectorModal(room, ruleKey, label, availableValues);
            });
        });

    }

    /**
     * Get available values for a rule
     */
    getAvailableValuesForRule(ruleKey) {
        const valuePresets = {
            revenue: [
                'Under $1M',
                '$1M - $5M', 
                '$5M - $10M',
                '$10M - $50M',
                '$50M - $100M',
                '$100M+',
                'Over $100M'
            ],
            company_size: [
                '1-10',
                '11-50',
                '51-200',
                '201-500',
                '501-1000',
                '1001-5000',
                '5000+',
                '1000+'
            ],
            target_states: [
                'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
                'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
                'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
                'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
                'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'
            ]
        };
        
        return valuePresets[ruleKey] || [];
    }    
    
    /**
     * Update a rule field
     */
    updateRuleField(room, ruleKey, field, value) {
        if (!this.rules[room][ruleKey]) {
            this.rules[room][ruleKey] = {};
        }
        this.rules[room][ruleKey][field] = value;
    }
    
    /**
     * Add value to rule
     */
    addValue(room, ruleKey, value) {
        if (!this.rules[room][ruleKey]) {
            this.rules[room][ruleKey] = {};
        }
        
        // Determine the correct field name
        let field = 'values';
        if (ruleKey === 'key_page_visit') {
            field = 'key_pages';
        }
        
        if (!this.rules[room][ruleKey][field]) {
            this.rules[room][ruleKey][field] = [];
        }
        
        if (!this.rules[room][ruleKey][field].includes(value)) {
            this.rules[room][ruleKey][field].push(value);
            this.renderRoomRules(room);
        }
    }
    
    /**
     * Remove value from rule
     */
    removeValue(room, ruleKey, value) {
        if (!this.rules[room][ruleKey]) return;
        
        // Determine the correct field name
        let field = 'values';
        if (ruleKey === 'key_page_visit') {
            field = 'key_pages';
        }
        
        if (this.rules[room][ruleKey][field]) {
            this.rules[room][ruleKey][field] = this.rules[room][ruleKey][field].filter(v => v !== value);
            this.renderRoomRules(room);
        }
    }
    
    /**
     * Render specific room rules
     */
    renderRoomRules(room) {
        if (room === 'problem') {
            this.renderProblemRoom();
        } else if (room === 'solution') {
            this.renderSolutionRoom();
        } else if (room === 'offer') {
            this.renderOfferRoom();
        }
    }
    
    /**
     * Initialize industry modal
     */
    initIndustryModal() {
        this.industryModal = document.getElementById('industry-modal');
        
        // Close button
        this.industryModal.querySelector('.modal-close').addEventListener('click', () => {
            this.closeIndustryModal();
        });
        
        // Cancel button
        document.getElementById('industry-cancel-btn').addEventListener('click', () => {
            this.closeIndustryModal();
        });
        
        // Save button
        document.getElementById('industry-save-btn').addEventListener('click', () => {
            this.saveIndustrySelection();
        });
        
        // Search functionality
        const searchInput = document.getElementById('industry-search-input');
        searchInput.addEventListener('input', (e) => {
            this.filterIndustries(e.target.value);
        });
        
        // Category checkboxes
        this.industryModal.querySelectorAll('.category-checkbox input').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const category = e.target.dataset.category;
                const checked = e.target.checked;
                this.industryModal.querySelectorAll(`.subcategory-checkbox input[data-category="${category}"]`).forEach(sub => {
                    sub.checked = checked;
                });
            });
        });
    }

    /**
     * Initialize generic value selector modal
     */
    initValueSelectorModal() {
        // Create modal HTML
        const modalHtml = `
            <div class="value-selector-modal" id="value-selector-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="value-selector-title">Select Values</h3>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="value-search">
                            <input type="text" id="value-search-input" placeholder="Search values..." />
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="value-selector-grid" id="value-selector-grid">
                            <!-- Values populated dynamically -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="value-cancel-btn">Cancel</button>
                        <button type="button" class="btn btn-primary" id="value-save-btn">
                            <i class="fas fa-check"></i> Apply Selection
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Append to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        this.valueSelectorModal = document.getElementById('value-selector-modal');
        
        // Close button
        this.valueSelectorModal.querySelector('.modal-close').addEventListener('click', () => {
            this.closeValueSelectorModal();
        });
        
        // Cancel button
        document.getElementById('value-cancel-btn').addEventListener('click', () => {
            this.closeValueSelectorModal();
        });
        
        // Save button
        document.getElementById('value-save-btn').addEventListener('click', () => {
            this.saveValueSelection();
        });
        
        // Search functionality
        const searchInput = document.getElementById('value-search-input');
        searchInput.addEventListener('input', (e) => {
            this.filterValues(e.target.value);
        });
    }

    /**
     * Open value selector modal
     */
    openValueSelectorModal(room, ruleKey, label, availableValues) {
        this.currentValueTarget = { room, ruleKey, availableValues };
        
        // Update title
        document.getElementById('value-selector-title').textContent = `Select ${label}`;
        
        // Get current values
        const currentValues = this.rules[room][ruleKey]?.values || [];
        
        // Render value grid
        const grid = document.getElementById('value-selector-grid');
        grid.innerHTML = availableValues.map(value => {
            const isSelected = currentValues.includes(value);
            return `
                <label class="value-option ${isSelected ? 'selected' : ''}">
                    <input type="checkbox" 
                        value="${value}" 
                        ${isSelected ? 'checked' : ''} />
                    <span>${value}</span>
                    <i class="fas fa-check"></i>
                </label>
            `;
        }).join('');
        
        // Attach checkbox listeners
        grid.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', (e) => {
                e.target.closest('.value-option').classList.toggle('selected', e.target.checked);
            });
        });
        
        this.valueSelectorModal.style.display = 'flex';
    }

    /**
     * Close value selector modal
     */
    closeValueSelectorModal() {
        this.valueSelectorModal.style.display = 'none';
        this.currentValueTarget = null;
        document.getElementById('value-search-input').value = '';
        this.filterValues('');
    }

    /**
     * Save value selection
     */
    saveValueSelection() {
        if (!this.currentValueTarget) return;
        
        const { room, ruleKey } = this.currentValueTarget;
        const selectedValues = [];
        
        this.valueSelectorModal.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
            selectedValues.push(cb.value);
        });
        
        if (!this.rules[room][ruleKey]) {
            this.rules[room][ruleKey] = {};
        }
        this.rules[room][ruleKey].values = selectedValues;
        
        this.renderRoomRules(room);
        this.closeValueSelectorModal();
    }

    /**
     * Filter values by search term
     */
    filterValues(searchTerm) {
        const term = searchTerm.toLowerCase();
        const options = this.valueSelectorModal.querySelectorAll('.value-option');
        
        options.forEach(option => {
            const text = option.textContent.toLowerCase();
            option.style.display = text.includes(term) ? 'flex' : 'none';
        });
    }    
    
    /**
     * Open industry modal
     */
    openIndustryModal(room, ruleKey) {
        this.currentIndustryTarget = { room, ruleKey };
        
        // Pre-select current values
        const currentValues = this.rules[room][ruleKey]?.values || [];
        this.industryModal.querySelectorAll('.subcategory-checkbox input').forEach(cb => {
            cb.checked = currentValues.includes(cb.dataset.value);
        });
        
        // Update category checkboxes
        this.industryModal.querySelectorAll('.category-checkbox input').forEach(cb => {
            const category = cb.dataset.category;
            const subcategories = this.industryModal.querySelectorAll(`.subcategory-checkbox input[data-category="${category}"]`);
            const allChecked = Array.from(subcategories).every(sub => sub.checked);
            cb.checked = allChecked;
        });
        
        this.industryModal.style.display = 'flex';
    }
    
    /**
     * Close industry modal
     */
    closeIndustryModal() {
        this.industryModal.style.display = 'none';
        this.currentIndustryTarget = null;
        document.getElementById('industry-search-input').value = '';
        this.filterIndustries('');
    }
    
    /**
     * Save industry selection
     */
    saveIndustrySelection() {
        if (!this.currentIndustryTarget) return;
        
        const { room, ruleKey } = this.currentIndustryTarget;
        const selectedValues = [];
        
        this.industryModal.querySelectorAll('.subcategory-checkbox input:checked').forEach(cb => {
            selectedValues.push(cb.dataset.value);
        });
        
        if (!this.rules[room][ruleKey]) {
            this.rules[room][ruleKey] = {};
        }
        this.rules[room][ruleKey].values = selectedValues;
        
        this.renderRoomRules(room);
        this.closeIndustryModal();
    }
    
    /**
     * Filter industries by search
     */
    filterIndustries(searchTerm) {
        const term = searchTerm.toLowerCase();
        
        this.industryModal.querySelectorAll('.industry-category').forEach(category => {
            let hasVisibleSub = false;
            
            category.querySelectorAll('.subcategory-checkbox').forEach(sub => {
                const text = sub.textContent.toLowerCase();
                const visible = text.includes(term);
                sub.style.display = visible ? 'flex' : 'none';
                if (visible) hasVisibleSub = true;
            });
            
            category.style.display = hasVisibleSub ? 'block' : 'none';
        });
    }
    
    /**
     * Validate rules
     */
    validateRules(room) {
        const errors = [];
        const rules = this.rules[room];
        
        // Add validation logic as needed
        
        this.showValidationErrors(room, errors);
        return errors.length === 0;
    }
    
    /**
     * Show validation errors
     */
    showValidationErrors(room, errors) {
        const container = document.getElementById(`${room}-validation`);
        const messageText = container.querySelector('.message-text');
        
        if (errors.length > 0) {
            messageText.textContent = errors.join('. ');
            container.style.display = 'flex';
        } else {
            container.style.display = 'none';
        }
    }
    
    /**
     * Save rules for room
     */
    async saveRules(room) {
        if (!this.validateRules(room)) {
            this.showNotification('error', this.config.strings?.validationError || 'Please fix validation errors before saving');
            return;
        }
        
        const form = document.getElementById(`${room}-rules-form`);
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        
        try {
            const response = await fetch(`${this.apiUrl}scoring-rules/${room}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce
                },
                body: JSON.stringify({
                    room_type: room,
                    rules_config: this.rules[room]
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to save rules');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('success', this.config.strings?.saveSuccess || 'Rules saved successfully');
            } else {
                throw new Error(data.message || 'Failed to save rules');
            }
            
        } catch (error) {
            console.error('Error saving rules:', error);
            this.showNotification('error', this.config.strings?.saveError || 'Failed to save rules');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
    
    /**
     * Reset rules to defaults
     */
    async resetRules(room) {
        if (!confirm(this.config.strings?.resetConfirm || 'Reset rules to global defaults? This cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiUrl}scoring-rules/${room}`, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': this.nonce
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to reset rules');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.rules[room] = data.data.rules_config;
                this.renderRoomRules(room);
                this.showNotification('success', 'Rules reset to defaults');
            } else {
                throw new Error(data.message || 'Failed to reset rules');
            }
            
        } catch (error) {
            console.error('Error resetting rules:', error);
            this.showNotification('error', 'Failed to reset rules');
        }
    }
    
    /**
     * Show notification
     */
    showNotification(type, message) {
        const container = document.querySelector('.notification-container');
        if (!container) return;
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
        
        notification.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
        
        // Close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (typeof rtrScoringConfig !== 'undefined') {
        new ScoringRulesManager(rtrScoringConfig);
    }
});