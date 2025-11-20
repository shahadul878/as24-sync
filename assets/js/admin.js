(function($) {
    'use strict';
    
    const AS24Sync = {
        pollingInterval: null,
        pollingTimeout: null,
        currentTab: 'combined',
        isRequestInProgress: false,
        lastRequestTime: 0,
        REQUEST_THROTTLE_MS: 2000, // Minimum 2 seconds between requests
        
        init: function() {
            this.bindEvents();
            // Load initial logs
            this.refreshAllLogs();
            // Stop any polling that might be running (safety cleanup)
            this.stopProgressPolling();
            // Check import status on page load (fragments style - no automatic polling)
            this.refreshImportStatus();
        },
        
        bindEvents: function() {
            // Test connection
            $('#as24-test-connection').on('click', this.testConnection.bind(this));
            
            // Start import - use event delegation to ensure it works
            $(document).on('click', '#as24-start-import', this.startImport.bind(this));
            
            // Also bind directly as fallback
            $('#as24-start-import').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                AS24Sync.startImport();
            });
            
            // Resume import
            $('#as24-resume-import').on('click', this.resumeImport.bind(this));
            
            // Stop import
            $('#as24-stop-import').on('click', this.stopImport.bind(this));
            
            // Logs tabs
            $('.as24-tab-button').on('click', function() {
                $('.as24-tab-button').removeClass('active');
                $(this).addClass('active');
                AS24Sync.currentTab = $(this).data('tab');
                AS24Sync.refreshAllLogs();
            });
            
            // Unified logs refresh
            $('#as24-refresh-logs').on('click', this.refreshAllLogs.bind(this));
            
            // Add refresh status button (fragments style - manual check)
            // Check if button exists, if not we'll add it via PHP
            $('#as24-refresh-status').on('click', function() {
                AS24Sync.refreshImportStatus();
            });
            
            // Clear logs
            $('#as24-clear-logs').on('click', this.clearLogs.bind(this));
            
            // Unified logs filters
            $('#as24-logs-status-filter, #as24-logs-action-filter, #as24-logs-listing-id-filter').on('change keyup', function() {
                AS24Sync.refreshAllLogs();
            });
            
            // Auto-refresh toggle - fragments style (only refresh when import completes)
            // No continuous polling, logs will refresh when import completes if checked
        },
        
        testConnection: function() {
            const $btn = $('#as24-test-connection');
            const $status = $('#as24-connection-status');
            
            $btn.prop('disabled', true).text(as24Sync.strings.connection_testing);
            $status.removeClass('success error').text('');
            
            $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_test_connection',
                    nonce: as24Sync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(as24Sync.strings.connection_success + ' (' + response.data.total_listings + ' listings)');
                    } else {
                        $status.addClass('error').text(as24Sync.strings.connection_failed + ': ' + response.data.message);
                    }
                },
                error: function() {
                    $status.addClass('error').text(as24Sync.strings.connection_failed);
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-network"></span> ' + 'Test Connection');
                }
            });
        },
        
        startImport: function() {
            if (!confirm('Are you sure you want to start the import? This will process all listings one by one.')) {
                return;
            }
            
            const $btn = $('#as24-start-import');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + (as24Sync.strings.importing || 'Starting...'));
            
            // Check if required variables are available
            if (typeof as24Sync === 'undefined') {
                console.error('AS24Sync: as24Sync object is not defined');
                AS24Sync.showMessage('error', 'JavaScript configuration error. Please refresh the page.');
                $btn.prop('disabled', false).html(originalHtml);
                return;
            }
            
            if (!as24Sync.ajaxurl || !as24Sync.nonce) {
                console.error('AS24Sync: Missing ajaxurl or nonce', as24Sync);
                AS24Sync.showMessage('error', 'AJAX configuration error. Please refresh the page.');
                $btn.prop('disabled', false).html(originalHtml);
                return;
            }
            
            $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_start_import',
                    nonce: as24Sync.nonce
                },
                timeout: 60000, // 60 second timeout
                success: function(response) {
                    console.log('AS24Sync: Import start response', response);
                    
                    if (response && response.success) {
                        AS24Sync.showMessage('success', response.data.message || 'Import started successfully');
                        // Update stats from response if available
                        if (response.data.import_status) {
                            AS24Sync.updateStats(response.data.import_status);
                        }
                        // Don't call refreshImportStatus here - updateStats will handle it
                        // Update button visibility
                        $('#as24-start-import').hide();
                        $('#as24-resume-import').show();
                        $('#as24-stop-import').show();
                    } else {
                        const errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Failed to start import';
                        AS24Sync.showMessage('error', errorMsg);
                        console.error('AS24Sync: Import start failed', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AS24Sync: AJAX error', {
                        status: status,
                        error: error,
                        xhr: xhr,
                        responseText: xhr.responseText
                    });
                    
                    let errorMsg = 'Failed to start import';
                    if (status === 'timeout') {
                        errorMsg = 'Request timed out. The import may have started but the response was delayed.';
                    } else if (xhr.responseText) {
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.data && errorResponse.data.message) {
                                errorMsg = errorResponse.data.message;
                            }
                        } catch (e) {
                            // Not JSON, use default message
                        }
                    }
                    
                    AS24Sync.showMessage('error', errorMsg);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },
        
        resumeImport: function() {
            $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_resume_import',
                    nonce: as24Sync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AS24Sync.showMessage('success', response.data.message);
                        // Update stats from response if available
                        if (response.data.import_status) {
                            AS24Sync.updateStats(response.data.import_status);
                        }
                        // Don't call refreshImportStatus here - updateStats will handle it
                    } else {
                        AS24Sync.showMessage('error', response.data.message);
                    }
                }
            });
        },
        
        stopImport: function() {
            if (!confirm('Are you sure you want to stop the import?')) {
                return;
            }
            
            $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_stop_import',
                    nonce: as24Sync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AS24Sync.showMessage('info', response.data.message);
                        // Update stats from response if available
                        if (response.data.import_status) {
                            AS24Sync.updateStats(response.data.import_status);
                        }
                        // Don't call refreshImportStatus here - updateStats will handle it
                        // Stop polling when import stops
                        AS24Sync.stopProgressPolling();
                    }
                }
            });
        },
        
        // REMOVED: startProgressPolling - No continuous polling (fragments style)
        // Progress is only checked on user actions or page load
        
        stopProgressPolling: function() {
            if (this.pollingTimeout !== null) {
                clearTimeout(this.pollingTimeout);
                this.pollingTimeout = null;
            }
            if (this.pollingInterval !== null) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
            if (this._pendingPollStart) {
                clearTimeout(this._pendingPollStart);
                this._pendingPollStart = null;
            }
            this.isRequestInProgress = false;
        },
        
        updateStats: function(importStatus) {
            if (!importStatus) {
                return;
            }
            
            // Update stat values
            $('#as24-total-listings-remote').text(importStatus.total || 0);
            $('#as24-total-listings-local').text(importStatus.local_count || 0);
            $('#as24-processed').text(importStatus.processed || 0);
            $('#as24-imported').text(importStatus.imported || 0);
            $('#as24-updated').text(importStatus.updated || 0);
            $('#as24-errors').text(importStatus.errors || 0);
            $('#as24-progress-percent').text((importStatus.progress_percent || 0) + '%');
            
            // Update progress bar
            this.updateProgressBar(importStatus.progress_percent || 0);
            
            // Update button visibility based on status
            if (importStatus.status === 'running') {
                $('#as24-start-import').hide();
                $('#as24-resume-import').hide();
                $('#as24-stop-import').show();
                // NO automatic polling - fragments style (only check on user action)
            } else if (importStatus.status === 'completed') {
                $('#as24-start-import').show();
                $('#as24-resume-import').hide();
                $('#as24-stop-import').hide();
                // Set progress to 100% when completed
                this.updateProgressBar(100);
                // Refresh logs when import completes (fragments style) if auto-refresh is enabled
                if ($('#as24-auto-refresh-logs').is(':checked')) {
                    this.refreshAllLogs();
                }
            } else {
                $('#as24-start-import').show();
                $('#as24-resume-import').show();
                $('#as24-stop-import').hide();
            }
        },
        
        updateProgressBar: function(percentage) {
            // Ensure percentage is between 0 and 100
            percentage = Math.max(0, Math.min(100, parseFloat(percentage) || 0));
            
            // Update progress bar fill width with smooth transition
            const $progressFill = $('.as24-progress-fill');
            $progressFill.css({
                'width': percentage + '%',
                'transition': 'width 0.3s ease'
            });
            
            // Update percentage text inside progress bar if it exists
            if ($progressFill.find('.as24-progress-text').length === 0) {
                $progressFill.html('<span class="as24-progress-text">' + percentage.toFixed(1) + '%</span>');
            } else {
                $progressFill.find('.as24-progress-text').text(percentage.toFixed(1) + '%');
            }
            
            // Update progress status message if available
            const $progressStatus = $('.as24-progress-status');
            if ($progressStatus.length) {
                if (percentage > 0 && percentage < 100) {
                    const processed = $('#as24-processed').text() || 0;
                    const total = $('#as24-total-listings').text() || 0;
                    if (total > 0) {
                        $progressStatus.text('Processing ' + processed + ' of ' + total + ' listings (' + percentage.toFixed(1) + '%)...');
                    } else {
                        $progressStatus.text('Processing... (' + percentage.toFixed(1) + '%)');
                    }
                } else if (percentage === 100) {
                    $progressStatus.text('Import completed successfully!');
                } else {
                    $progressStatus.text('Ready to start import');
                }
            }
        },
        
        refreshImportStatus: function() {
            // Throttle requests - don't make request if one was made recently
            const now = Date.now();
            if (this.isRequestInProgress || (now - this.lastRequestTime) < this.REQUEST_THROTTLE_MS) {
                return;
            }
            
            this.isRequestInProgress = true;
            this.lastRequestTime = now;
            
            const self = this;
            $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_get_progress',
                    nonce: as24Sync.nonce
                },
                success: function(response) {
                    if (response.success && response.data.import_status) {
                        // Always update stats (fragments style - only on user action)
                        AS24Sync.updateStats(response.data.import_status);
                    }
                },
                error: function() {
                    // Silently fail - status update is not critical
                },
                complete: function() {
                    self.isRequestInProgress = false;
                }
            });
        },
        
        showMessage: function(type, message) {
            const $messages = $('#as24-status-messages');
            const $message = $('<div class="as24-status-message ' + type + '">' + message + '</div>');
            
            $messages.append($message);
            
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        refreshAllLogs: function() {
            const $btn = $('#as24-refresh-logs');
            const tab = this.currentTab || 'combined';
            const status = $('#as24-logs-status-filter').val();
            const action = $('#as24-logs-action-filter').val();
            const listingId = $('#as24-logs-listing-id-filter').val();
            
            $btn.prop('disabled', true).find('.dashicons').addClass('spin');
            
            // Load both types of logs
            const historyPromise = $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_get_sync_history',
                    nonce: as24Sync.nonce,
                    limit: 50,
                    offset: 0,
                    status: (tab === 'combined' || tab === 'history') ? (status || '') : ''
                }
            });
            
            const listingLogsPromise = $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_get_listing_logs',
                    nonce: as24Sync.nonce,
                    limit: 50,
                    offset: 0,
                    listing_id: (tab === 'combined' || tab === 'listings') ? (listingId || '') : '',
                    action_filter: (tab === 'combined' || tab === 'listings') ? (action || '') : ''
                }
            });
            
            $.when(historyPromise, listingLogsPromise).done(function(historyResponse, listingResponse) {
                const historyLogs = historyResponse[0].success ? historyResponse[0].data.logs : [];
                const listingLogs = listingResponse[0].success ? listingResponse[0].data.logs : [];
                
                AS24Sync.renderUnifiedLogs(historyLogs, listingLogs, tab);
                // Don't refresh import status here - polling handles it
            }).fail(function() {
                AS24Sync.showMessage('error', 'Failed to refresh logs');
            }).always(function() {
                $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            });
        },
        
        renderUnifiedLogs: function(historyLogs, listingLogs, tab) {
            const $tbody = $('#as24-logs-tbody');
            let allLogs = [];
            
            // Combine logs based on active tab
            if (tab === 'combined') {
                // Add history logs as summary entries
                historyLogs.forEach(function(log) {
                    allLogs.push({
                        type: 'history',
                        date: log.created_at,
                        operation_type: log.operation_type,
                        status: log.status,
                        total_processed: log.total_processed,
                        total_imported: log.total_imported,
                        total_updated: log.total_updated,
                        total_errors: log.total_errors,
                        duration: log.duration,
                        message: log.message
                    });
                });
                
                // Add listing logs as detail entries
                listingLogs.forEach(function(log) {
                    allLogs.push({
                        type: 'listing',
                        date: log.created_at,
                        listing_id: log.listing_id,
                        post_id: log.post_id,
                        action: log.action,
                        changes: log.changes,
                        message: log.message
                    });
                });
                
                // Sort by date (newest first)
                allLogs.sort(function(a, b) {
                    return new Date(b.date) - new Date(a.date);
                });
            } else if (tab === 'history') {
                historyLogs.forEach(function(log) {
                    allLogs.push({
                        type: 'history',
                        date: log.created_at,
                        operation_type: log.operation_type,
                        status: log.status,
                        total_processed: log.total_processed,
                        total_imported: log.total_imported,
                        total_updated: log.total_updated,
                        total_errors: log.total_errors,
                        duration: log.duration,
                        message: log.message
                    });
                });
            } else if (tab === 'listings') {
                listingLogs.forEach(function(log) {
                    allLogs.push({
                        type: 'listing',
                        date: log.created_at,
                        listing_id: log.listing_id,
                        post_id: log.post_id,
                        action: log.action,
                        changes: log.changes,
                        message: log.message
                    });
                });
            }
            
            if (allLogs.length === 0) {
                $tbody.html('<tr><td colspan="12" class="as24-no-logs">No logs found.</td></tr>');
                return;
            }
            
            let html = '';
            allLogs.forEach(function(log) {
                const date = new Date(log.date);
                const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                
                if (log.type === 'history') {
                    // History log row
                    let duration = '-';
                    if (log.duration !== null && log.duration !== undefined && log.duration !== '') {
                        const durationNum = parseFloat(log.duration);
                        if (!isNaN(durationNum) && durationNum > 0) {
                            duration = durationNum.toFixed(2) + ' sec';
                        }
                    }
                    
                    html += '<tr class="as24-log-history">';
                    html += '<td class="column-date">' + dateStr + '</td>';
                    html += '<td class="column-type"><span class="as24-badge as24-badge-type">' + 
                        (log.operation_type ? log.operation_type.charAt(0).toUpperCase() + log.operation_type.slice(1) : 'Sync') + '</span></td>';
                    html += '<td class="column-status"><span class="as24-badge as24-badge-' + log.status + '">' + 
                        (log.status ? log.status.charAt(0).toUpperCase() + log.status.slice(1) : '-') + '</span></td>';
                    html += '<td class="column-listing-id">-</td>';
                    html += '<td class="column-post-id">-</td>';
                    html += '<td class="column-processed">' + (log.total_processed || 0) + '</td>';
                    html += '<td class="column-imported">' + (log.total_imported || 0) + '</td>';
                    html += '<td class="column-updated">' + (log.total_updated || 0) + '</td>';
                    html += '<td class="column-errors">';
                    if (log.total_errors > 0) {
                        html += '<span class="as24-error-count">' + log.total_errors + '</span>';
                    } else {
                        html += (log.total_errors || 0);
                    }
                    html += '</td>';
                    html += '<td class="column-duration">' + duration + '</td>';
                    html += '<td class="column-changes">-</td>';
                    html += '<td class="column-message">' + (log.message || '-') + '</td>';
                    html += '</tr>';
                } else {
                    // Listing log row
                    let changesHtml = '-';
                    if (log.changes && Array.isArray(log.changes) && log.changes.length > 0) {
                        changesHtml = '<ul class="as24-changes-list">';
                        log.changes.forEach(function(change) {
                            changesHtml += '<li><strong>' + (change.label || change.field) + ':</strong> ' + 
                                (change.old || '-') + ' → ' + (change.new || '-') + '</li>';
                        });
                        changesHtml += '</ul>';
                    }
                    
                    const postIdLink = log.post_id ? 
                        '<a href="' + as24Sync.adminUrl + 'post.php?post=' + log.post_id + '&action=edit" target="_blank">' + log.post_id + '</a>' : 
                        '-';
                    
                    html += '<tr class="as24-log-listing">';
                    html += '<td class="column-date">' + dateStr + '</td>';
                    html += '<td class="column-type">-</td>';
                    html += '<td class="column-status"><span class="as24-badge as24-badge-' + log.action + '">' + 
                        (log.action ? log.action.charAt(0).toUpperCase() + log.action.slice(1) : '-') + '</span></td>';
                    html += '<td class="column-listing-id"><code>' + (log.listing_id || '-') + '</code></td>';
                    html += '<td class="column-post-id">' + postIdLink + '</td>';
                    html += '<td class="column-processed">-</td>';
                    html += '<td class="column-imported">-</td>';
                    html += '<td class="column-updated">-</td>';
                    html += '<td class="column-errors">-</td>';
                    html += '<td class="column-duration">-</td>';
                    html += '<td class="column-changes">' + changesHtml + '</td>';
                    html += '<td class="column-message">' + (log.message || '-') + '</td>';
                    html += '</tr>';
                }
            });
            
            $tbody.html(html);
        },
        
        clearLogs: function() {
            if (!confirm('Are you sure you want to clear all logs? This action cannot be undone.')) {
                return;
            }
            
            const $btn = $('#as24-clear-logs');
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Clearing...');
            
            $.ajax({
                url: as24Sync.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_clear_logs',
                    nonce: as24Sync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AS24Sync.showMessage('success', response.data.message || 'All logs cleared successfully');
                        // Refresh logs to show empty state
                        AS24Sync.refreshAllLogs();
                    } else {
                        AS24Sync.showMessage('error', response.data.message || 'Failed to clear logs');
                    }
                },
                error: function() {
                    AS24Sync.showMessage('error', 'Failed to clear logs');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    };
    
    $(document).ready(function() {
        AS24Sync.init();
    });
    
})(jQuery);

