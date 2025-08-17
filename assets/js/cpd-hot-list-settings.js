/**
 * Hot List Settings JavaScript - ENHANCED VERSION
 * Handles client selection, form interactions, and settings management
 * with visual enhancements for better visibility of selected settings
 */

(function($) {
    'use strict';

    const HotListSettings = {
        
        // Add initialization flag to prevent duplicate handlers
        initialized: false,
        
        // Add debounce utility
        debounceTimers: {},
        
        /**
         * Initialize the settings page
         */
        init: function() {
            // Prevent duplicate initialization
            if (this.initialized) {
                // console.log('Hot List Settings already initialized, skipping...');
                return;
            }
            
            // console.log('Initializing Enhanced Hot List Settings...');
            this.bindEvents();
            this.updateActiveFiltersCount();
            this.validateRequiredMatches();
            this.updateSummarySection();
            this.addVisualEnhancements();
            this.initialized = true;
        },

        /**
         * Debounce utility function
         */
        debounce: function(key, func, delay) {
            if (this.debounceTimers[key]) {
                clearTimeout(this.debounceTimers[key]);
            }
            this.debounceTimers[key] = setTimeout(func, delay);
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            // Use namespaced events to prevent duplicate bindings
            const eventNamespace = '.hotListSettings';
            
            // Unbind any existing handlers first
            $(document).off(eventNamespace);
            
            // Client selection (admin only) - with debouncing
            $(document).on('click' + eventNamespace, '.client-list-item', (e) => {
                this.debounce('clientSelection', () => {
                    this.handleClientSelection.call(e.target, e);
                }, 100);
            });
            
            // Form interactions - with debouncing
            $(document).on('change' + eventNamespace, 'input[type="checkbox"]', (e) => {
                this.debounce('filterChange', () => {
                    this.handleFilterChange.call(e.target);
                }, 50);
            });
            
            $(document).on('change' + eventNamespace, '#required-matches', (e) => {
                this.debounce('requiredMatches', () => {
                    this.handleRequiredMatchesChange();
                }, 100);
            });
            
            $(document).on('click' + eventNamespace, '#reset-settings', this.handleResetSettings.bind(this));
            $(document).on('submit' + eventNamespace, '#hot-list-settings-form', this.handleFormSubmit.bind(this));
            
            // Filter group interactions
            $(document).on('click' + eventNamespace, '.filter-header', this.toggleFilterGroup.bind(this));
            $(document).on('click' + eventNamespace, '.toggle-selected', this.toggleShowSelected.bind(this));
            
            // Select all/none functionality
            this.addSelectAllNoneButtons();
            this.addFilterSearch();
        },

        /**
         * Add visual enhancements to the page
         */
        addVisualEnhancements: function() {
            // Add summary sections
            this.addSummarySections();
            
            // Convert filter groups to collapsible
            this.makeFilterGroupsCollapsible();
            
            // Update visual states
            this.updateCheckboxStates();
            this.updateSelectedCounts();
        },

        /**
         * Add summary sections at the top of the form
         */
        addSummarySections: function() {
            // Check if summary already exists
            if ($('.quick-summary').length > 0) {
                return;
            }
            
            const quickSummaryHtml = `
                <div class="quick-summary">
                    <h3><i class="fas fa-fire"></i> Current Hot List Configuration</h3>
                    <div class="quick-stats">
                        <div class="quick-stat">
                            <span class="quick-stat-number" id="total-filters">0</span>
                            <span class="quick-stat-label">Active Filters</span>
                        </div>
                        <div class="quick-stat">
                            <span class="quick-stat-number" id="required-matches-display">1</span>
                            <span class="quick-stat-label">Required Matches</span>
                        </div>
                        <div class="quick-stat">
                            <span class="quick-stat-number" id="total-selected">0</span>
                            <span class="quick-stat-label">Total Selections</span>
                        </div>
                    </div>
                </div>
            `;
            
            const settingsSummaryHtml = `
                <div class="settings-summary">
                    <div class="summary-title">
                        <i class="fas fa-list-check"></i> Selected Filter Values
                    </div>
                    <div class="summary-grid">
                        <div class="summary-group">
                            <div class="summary-group-title">Company Revenue</div>
                            <div class="summary-values" id="revenue-summary">
                                <span class="summary-tag empty">None selected</span>
                            </div>
                        </div>
                        <div class="summary-group">
                            <div class="summary-group-title">Company Size</div>
                            <div class="summary-values" id="size-summary">
                                <span class="summary-tag empty">None selected</span>
                            </div>
                        </div>
                        <div class="summary-group">
                            <div class="summary-group-title">Industry</div>
                            <div class="summary-values" id="industry-summary">
                                <span class="summary-tag empty">None selected</span>
                            </div>
                        </div>
                        <div class="summary-group">
                            <div class="summary-group-title">States</div>
                            <div class="summary-values" id="state-summary">
                                <span class="summary-tag empty">None selected</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Insert before the form
            $('.settings-form').before(quickSummaryHtml + settingsSummaryHtml);
        },

        /**
         * Make filter groups collapsible with enhanced headers
         */
        makeFilterGroupsCollapsible: function() {
            $('.filter-group').each(function() {
                const $group = $(this);
                const $title = $group.find('.filter-title');
                const $content = $group.find('.checkbox-group').parent();
                
                // Skip if already converted
                if ($group.find('.filter-header').length > 0) {
                    return;
                }
                
                // Create new structure
                const titleText = $title.text();
                const headerHtml = `
                    <div class="filter-header">
                        <div class="filter-title">
                            ${titleText}
                            <span class="selected-count">0</span>
                        </div>
                        <i class="fas fa-chevron-down collapse-icon"></i>
                    </div>
                `;
                
                $title.replaceWith(headerHtml);
                $content.addClass('filter-content');
                
                // Add filter controls
                const controlsHtml = `
                    <div class="filter-controls">
                        <button type="button" class="toggle-selected">Show Selected Only</button>
                    </div>
                `;
                
                $content.prepend(controlsHtml);
            });
        },

        /**
         * Toggle filter group collapse state
         */
        toggleFilterGroup: function(e) {
            e.preventDefault();
            const $group = $(e.currentTarget).closest('.filter-group');
            $group.toggleClass('collapsed');
        },

        /**
         * Toggle show selected only mode
         */
        toggleShowSelected: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const $group = $button.closest('.filter-group');
            
            $group.toggleClass('show-selected-only');
            
            if ($group.hasClass('show-selected-only')) {
                $button.addClass('active').text('Show All');
            } else {
                $button.removeClass('active').text('Show Selected Only');
            }
        },

        /**
         * Update checkbox visual states
         */
        updateCheckboxStates: function() {
            $('.checkbox-item').each(function() {
                const $item = $(this);
                const $checkbox = $item.find('input[type="checkbox"]');
                
                if ($checkbox.is(':checked')) {
                    $item.addClass('selected');
                } else {
                    $item.removeClass('selected');
                }
            });
        },

        /**
         * Update selected counts for each filter group
         */
        updateSelectedCounts: function() {
            $('.filter-group').each(function() {
                const $group = $(this);
                const $selectedCount = $group.find('.selected-count');
                const checkedBoxes = $group.find('input[type="checkbox"]:checked');
                const hasAnySelected = $group.find('input[value="any"]:checked').length > 0;
                
                let count = 0;
                if (hasAnySelected) {
                    count = 1; // "Any" counts as 1 selection
                } else {
                    count = checkedBoxes.length;
                }
                
                $selectedCount.text(count);
                
                if (count === 0) {
                    $selectedCount.addClass('zero');
                } else {
                    $selectedCount.removeClass('zero');
                }
            });
        },

        /**
         * Handle client selection from the sidebar
         */
        handleClientSelection: function(e) {
            e.preventDefault();
            
            const $item = $(this);
            const clientId = $item.data('client-id');
            
            // Prevent duplicate selections
            if ($item.hasClass('active')) {
                // console.log('Client already selected, ignoring duplicate click');
                return;
            }
            
            // console.log('Hot List: Client selection changed to:', clientId);
            
            // Update active state
            $('.client-list-item').removeClass('active');
            $item.addClass('active');
            
            // Redirect to settings page with selected client
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('client_id', clientId);
            window.location.href = currentUrl.toString();
        },

        /**
         * Handle filter checkbox changes - ENHANCED
         */
        handleFilterChange: function() {
            const $checkbox = $(this);
            const filterGroup = $checkbox.attr('name').replace('[]', '');
            
            // Handle "Any" option logic
            if ($checkbox.val() === 'any' || $checkbox.val() === 'Any') {
                if ($checkbox.is(':checked')) {
                    // Uncheck all other options in this group
                    $(`input[name="${$checkbox.attr('name')}"]`).not($checkbox).prop('checked', false);
                }
            } else {
                // If any specific option is checked, uncheck "Any"
                if ($checkbox.is(':checked')) {
                    $(`input[name="${$checkbox.attr('name')}"][value="any"], input[name="${$checkbox.attr('name')}"][value="Any"]`).prop('checked', false);
                }
            }
            
            // Batch update all related elements
            HotListSettings.batchUpdate();
        },

        /**
         * Batch update to prevent multiple DOM updates - ENHANCED
         */
        batchUpdate: function() {
            this.debounce('batchUpdate', () => {
                this.updateCheckboxStates();
                this.updateSelectedCounts();
                this.updateActiveFiltersCount();
                this.updateSummarySection();
                this.validateRequiredMatches();
                
                // Update all select all buttons
                $('.filter-group').each(function() {
                    HotListSettings.updateSelectAllButtons($(this));
                });
            }, 10);
        },

        /**
         * Update the summary section with current selections
         */
        updateSummarySection: function() {
            // Update revenue summary
            this.updateFilterSummary('revenue', '#revenue-summary', {
                'any': 'Any',
                'below-500k': 'Below $500k',
                '500k-1m': '$500k - $1M',
                '1m-5m': '$1M - $5M',
                '5m-10m': '$5M - $10M',
                '10m-20m': '$10M - $20M',
                '20m-50m': '$20M - $50M',
                'above-50m': 'Above $50M'
            });
            
            // Update company size summary
            this.updateFilterSummary('company_size', '#size-summary', {
                'any': 'Any',
                '1-10': '1-10',
                '11-20': '11-20',
                '21-50': '21-50',
                '51-200': '51-200',
                '200-500': '200-500',
                '500-1000': '500-1000',
                '1000-5000': '1000-5000',
                'above-5000': 'Above 5000'
            });
            
            // Update industry summary
            this.updateFilterSummary('industry', '#industry-summary');
            
            // Update state summary
            this.updateFilterSummary('state', '#state-summary', {
                'any': 'Any',
                'AL': 'Alabama',
                'AK': 'Alaska',
                'AZ': 'Arizona',
                'AR': 'Arkansas',
                'CA': 'California',
                'CO': 'Colorado',
                'CT': 'Connecticut',
                'DE': 'Delaware',
                'FL': 'Florida',
                'GA': 'Georgia',
                'HI': 'Hawaii',
                'ID': 'Idaho',
                'IL': 'Illinois',
                'IN': 'Indiana',
                'IA': 'Iowa',
                'KS': 'Kansas',
                'KY': 'Kentucky',
                'LA': 'Louisiana',
                'ME': 'Maine',
                'MD': 'Maryland',
                'MA': 'Massachusetts',
                'MI': 'Michigan',
                'MN': 'Minnesota',
                'MS': 'Mississippi',
                'MO': 'Missouri',
                'MT': 'Montana',
                'NE': 'Nebraska',
                'NV': 'Nevada',
                'NH': 'New Hampshire',
                'NJ': 'New Jersey',
                'NM': 'New Mexico',
                'NY': 'New York',
                'NC': 'North Carolina',
                'ND': 'North Dakota',
                'OH': 'Ohio',
                'OK': 'Oklahoma',
                'OR': 'Oregon',
                'PA': 'Pennsylvania',
                'RI': 'Rhode Island',
                'SC': 'South Carolina',
                'SD': 'South Dakota',
                'TN': 'Tennessee',
                'TX': 'Texas',
                'UT': 'Utah',
                'VT': 'Vermont',
                'VA': 'Virginia',
                'WA': 'Washington',
                'WV': 'West Virginia',
                'WI': 'Wisconsin',
                'WY': 'Wyoming'
            });
        },

        /**
         * Update a specific filter summary
         */
        updateFilterSummary: function(filterName, summarySelector, labelMap = null) {
            const $summary = $(summarySelector);
            const selectedValues = [];
            
            $(`input[name="${filterName}[]"]:checked`).each(function() {
                const value = $(this).val();
                const label = labelMap && labelMap[value] ? labelMap[value] : value;
                selectedValues.push(label);
            });
            
            if (selectedValues.length === 0) {
                $summary.html('<span class="summary-tag empty">None selected</span>');
            } else {
                const tags = selectedValues.map(value => 
                    `<span class="summary-tag">${value}</span>`
                ).join('');
                $summary.html(tags);
            }
        },

        /**
         * Handle required matches dropdown change
         */
        handleRequiredMatchesChange: function() {
            this.validateRequiredMatches();
            this.updateQuickStats();
        },

        /**
         * Update the count of active filters - OPTIMIZED
         */
        updateActiveFiltersCount: function() {
            let activeFilters = 0;
            
            $('.filter-group').each(function() {
                const $group = $(this);
                const checkedBoxes = $group.find('input[type="checkbox"]:checked');
                const hasAnySelected = $group.find('input[value="any"]:checked, input[value="Any"]:checked').length > 0;
                
                // Count as active if any non-"any" option is selected, or if only "any" is selected
                if (checkedBoxes.length > 0 && !hasAnySelected) {
                    activeFilters++;
                } else if (hasAnySelected && checkedBoxes.length === 1) {
                    activeFilters++;
                }
            });
            
            // Update the hidden counter (for backward compatibility)
            if ($('#active-filters-count').length === 0) {
                $('body').append('<span id="active-filters-count" style="display: none;">' + activeFilters + '</span>');
            } else {
                $('#active-filters-count').text(activeFilters);
            }
            
            // Update quick stats
            this.updateQuickStats();
        },

        /**
         * Validate required matches against active filters
         */
        validateRequiredMatches: function() {
            const activeFilters = parseInt($('#active-filters-count').text()) || 0;
            const requiredMatches = parseInt($('#required-matches').val()) || 1;
            const $select = $('#required-matches');
            const $section = $('.required-matches-section');
            
            // Update available options
            $select.find('option').each(function() {
                const optionValue = parseInt($(this).val());
                if (optionValue > activeFilters) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
            
            // Adjust selection if current value is too high
            if (requiredMatches > activeFilters && activeFilters > 0) {
                $select.val(activeFilters);
            }
            
            // Add warning if no filters are active
            if (activeFilters === 0) {
                if (!$section.find('.no-filters-warning').length) {
                    $section.append(`
                        <div class="no-filters-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            No filters are currently active. All leads will be considered hot.
                        </div>
                    `);
                }
            } else {
                $section.find('.no-filters-warning').remove();
            }
            
            // Update matches text
            const $matchesText = $('#matches-text');
            if ($matchesText.length > 0) {
                const matchesText = activeFilters === 1 ? 'active filter' : 'active filters';
                $matchesText.html(`of <span id="active-filters-count">${activeFilters}</span> ${matchesText} to match`);
            }
        },

        /**
         * Add Select All/None buttons to filter groups
         */
        addSelectAllNoneButtons: function() {
            $('.filter-group').each(function() {
                const $group = $(this);
                const $checkboxes = $group.find('input[type="checkbox"]').not('[value="any"], [value="Any"]');
                const $controls = $group.find('.filter-controls');
                
                // Skip if buttons already exist
                if ($controls.find('.select-all-btn').length > 0) {
                    return;
                }
                
                if ($checkboxes.length > 5) { // Only add for groups with many options
                    const buttonsHtml = `
                        <button type="button" class="select-all-btn">Select All</button>
                        <button type="button" class="select-none-btn">Select None</button>
                    `;
                    
                    $controls.append(buttonsHtml);
                    
                    // Bind events
                    $controls.find('.select-all-btn').on('click', function(e) {
                        e.preventDefault();
                        $group.find('input[value="any"], input[value="Any"]').prop('checked', false);
                        $checkboxes.prop('checked', true);
                        HotListSettings.batchUpdate();
                    });
                    
                    $controls.find('.select-none-btn').on('click', function(e) {
                        e.preventDefault();
                        $group.find('input[type="checkbox"]').prop('checked', false);
                        HotListSettings.batchUpdate();
                    });
                }
            });
            
            // Initial state update
            $('.filter-group').each(function() {
                HotListSettings.updateSelectAllButtons($(this));
            });
        },

        /**
         * Update Select All/None button states
         */
        updateSelectAllButtons: function($group) {
            const $checkboxes = $group.find('input[type="checkbox"]').not('[value="any"], [value="Any"]');
            const $checkedBoxes = $checkboxes.filter(':checked');
            const $selectAllBtn = $group.find('.select-all-btn');
            const $selectNoneBtn = $group.find('.select-none-btn');
            
            if ($selectAllBtn.length === 0 || $selectNoneBtn.length === 0) {
                return; // Buttons don't exist for this group
            }
            
            if ($checkedBoxes.length === 0) {
                $selectAllBtn.prop('disabled', false).css('opacity', '1');
                $selectNoneBtn.prop('disabled', true).css('opacity', '0.5');
            } else if ($checkedBoxes.length === $checkboxes.length) {
                $selectAllBtn.prop('disabled', true).css('opacity', '0.5');
                $selectNoneBtn.prop('disabled', false).css('opacity', '1');
            } else {
                $selectAllBtn.prop('disabled', false).css('opacity', '1');
                $selectNoneBtn.prop('disabled', false).css('opacity', '1');
            }
        },

        /**
         * Add search functionality to long filter lists
         */
        addFilterSearch: function() {
            $('.filter-group').each(function() {
                const $group = $(this);
                const $checkboxes = $group.find('.checkbox-item');
                const $controls = $group.find('.filter-controls');
                
                // Skip if search already exists
                if ($controls.find('.filter-search').length > 0) {
                    return;
                }
                
                if ($checkboxes.length > 10) {
                    const searchHtml = `
                        <div class="filter-search">
                            <input type="text" placeholder="Search options..." class="search-input">
                        </div>
                    `;
                    
                    $controls.before(searchHtml);
                    
                    // Search functionality with debouncing
                    $group.find('.search-input').on('input', function() {
                        const searchTerm = $(this).val().toLowerCase();
                        
                        HotListSettings.debounce('filterSearch_' + $group.index(), () => {
                            $checkboxes.each(function() {
                                const $item = $(this);
                                const label = $item.find('label').text().toLowerCase();
                                
                                if (label.includes(searchTerm)) {
                                    $item.show();
                                } else {
                                    $item.hide();
                                }
                            });
                        }, 150);
                    });
                }
            });
        },

        /**
         * Handle reset settings button
         */
        handleResetSettings: function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to reset all settings to their defaults? This action cannot be undone.')) {
                // Uncheck all filters
                $('input[type="checkbox"]').prop('checked', false);
                
                // Check "Any" options
                $('input[value="any"], input[value="Any"]').prop('checked', true);
                
                // Reset required matches to 1
                $('#required-matches').val('1');
                
                // Batch update all related elements
                this.batchUpdate();
                
                // Show success message
                this.showNotification('Settings have been reset to defaults.', 'success');
            }
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            // Validate form first
            if (!this.validateForm()) {
                e.preventDefault();
                return false;
            }
            
            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Show loading state but don't prevent submission
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            
            // Let the form submit normally - don't prevent default
            // The PHP will handle the submission and redirect
            return true;
        },

        /**
         * Validate form before submission
         */
        validateForm: function() {
            const activeFilters = parseInt($('#active-filters-count').text()) || 0;
            
            if (activeFilters === 0) {
                this.showNotification('Please select at least one filter criteria or the "Any" option.', 'warning');
                return false;
            }
            
            return true;
        },

        /**
         * Show notification message - ENHANCED
         */
        showNotification: function(message, type) {
            // Remove existing notifications
            $('.settings-notification').remove();
            
            const typeClass = type === 'success' ? 'notice-success' : 
                           type === 'error' ? 'notice-error' : 
                           type === 'warning' ? 'notice-warning' : 'notice-info';
            
            const $notification = $(`
                <div class="settings-notification notice ${typeClass}">
                    <p style="margin: 0;">${message}</p>
                    <button type="button" class="notice-dismiss">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
            
            // Insert notification
            $('.settings-content').prepend($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Handle manual dismiss
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Scroll to notification
            $('html, body').animate({
                scrollTop: $notification.offset().top - 100
            }, 300);
        },

        /**
         * Cleanup method
         */
        destroy: function() {
            $(document).off('.hotListSettings');
            this.initialized = false;
            
            // Clear all debounce timers
            Object.values(this.debounceTimers).forEach(timer => {
                if (timer) clearTimeout(timer);
            });
            this.debounceTimers = {};
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // console.log('Enhanced Hot List Settings: Document ready, initializing...');
        HotListSettings.init();
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        HotListSettings.destroy();
    });

    // Make available globally for debugging
    window.HotListSettings = HotListSettings;

})(jQuery);