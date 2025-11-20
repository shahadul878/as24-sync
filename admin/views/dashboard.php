<?php
/**
 * Dashboard View
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$import_status = AS24_Import_Orchestrator::get_import_status();
$progress = AS24_Progress_Tracker::get_progress();
$sync_history = AS24_Sync_History::get_records(array('limit' => 20));
?>
<div class="wrap as24-sync-dashboard">
    <h1><?php _e('AS24 Sync Dashboard', 'as24-sync'); ?></h1>
    
    <div class="as24-dashboard-content">
        <!-- Statistics Cards -->
        <div class="as24-stats-grid">
            <div class="as24-stat-card as24-stat-card-total-listings">
                <div class="as24-stat-label"><?php _e('Total Listings', 'as24-sync'); ?></div>
                <div class="as24-total-listings-wrapper">
                    <div class="as24-total-listing-item">
                        <span class="as24-total-label"><?php _e('Remote:', 'as24-sync'); ?></span>
                        <span class="as24-stat-value" id="as24-total-listings-remote"><?php echo esc_html($import_status['total'] ?? 0); ?></span>
                    </div>
                    <div class="as24-total-listing-item">
                        <span class="as24-total-label"><?php _e('Local:', 'as24-sync'); ?></span>
                        <span class="as24-stat-value" id="as24-total-listings-local"><?php echo esc_html(AS24_Import_Orchestrator::get_local_listings_count()); ?></span>
                    </div>
                </div>
            </div>
            <div class="as24-stat-card">
                <div class="as24-stat-label"><?php _e('Processed', 'as24-sync'); ?></div>
                <div class="as24-stat-value" id="as24-processed"><?php echo esc_html($import_status['processed'] ?? 0); ?></div>
            </div>
            <div class="as24-stat-card">
                <div class="as24-stat-label"><?php _e('Imported', 'as24-sync'); ?></div>
                <div class="as24-stat-value" id="as24-imported"><?php echo esc_html($import_status['imported'] ?? 0); ?></div>
            </div>
            <div class="as24-stat-card">
                <div class="as24-stat-label"><?php _e('Updated', 'as24-sync'); ?></div>
                <div class="as24-stat-value" id="as24-updated"><?php echo esc_html($import_status['updated'] ?? 0); ?></div>
            </div>
            <div class="as24-stat-card">
                <div class="as24-stat-label"><?php _e('Errors', 'as24-sync'); ?></div>
                <div class="as24-stat-value as24-error" id="as24-errors"><?php echo esc_html($import_status['errors'] ?? 0); ?></div>
            </div>
            <div class="as24-stat-card">
                <div class="as24-stat-label"><?php _e('Progress', 'as24-sync'); ?></div>
                <div class="as24-stat-value" id="as24-progress-percent"><?php echo esc_html($import_status['progress_percent'] ?? 0); ?>%</div>
            </div>
        </div>
        
        <!-- Progress Display -->
        <div class="as24-progress-section">
            <h2><?php _e('Import Progress', 'as24-sync'); ?></h2>
            <?php AS24_Progress_Tracker::render_progress(); ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="as24-actions-section">
            <h2><?php _e('Actions', 'as24-sync'); ?></h2>
            <div class="as24-action-buttons">
                <button type="button" id="as24-start-import" class="button button-primary button-large">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Start Import', 'as24-sync'); ?>
                </button>
                <button type="button" id="as24-resume-import" class="button button-secondary button-large" style="display: none;">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Resume Import', 'as24-sync'); ?>
                </button>
                <button type="button" id="as24-stop-import" class="button button-secondary button-large" style="display: none;">
                    <span class="dashicons dashicons-controls-pause"></span>
                    <?php _e('Stop Import', 'as24-sync'); ?>
                </button>
                <button type="button" id="as24-refresh-status" class="button button-secondary button-large">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh Status', 'as24-sync'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=as24-sync-settings')); ?>" class="button button-secondary button-large">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Settings', 'as24-sync'); ?>
                </a>
            </div>
        </div>
        
        <!-- Status Messages -->
        <div id="as24-status-messages" class="as24-status-messages"></div>
        
        <!-- Unified Activity Logs -->
        <div class="as24-activity-logs-section">
            <h2><?php _e('Activity Logs', 'as24-sync'); ?></h2>
            <div class="as24-logs-tabs">
                <button type="button" class="as24-tab-button active" data-tab="combined"><?php _e('All Logs', 'as24-sync'); ?></button>
                <button type="button" class="as24-tab-button" data-tab="history"><?php _e('Sync History', 'as24-sync'); ?></button>
                <button type="button" class="as24-tab-button" data-tab="listings"><?php _e('Listing Logs', 'as24-sync'); ?></button>
            </div>
            <div class="as24-logs-controls">
                <div class="as24-logs-filters">
                    <select id="as24-logs-status-filter" class="as24-logs-filter">
                        <option value=""><?php _e('All Status', 'as24-sync'); ?></option>
                        <option value="running"><?php _e('Running', 'as24-sync'); ?></option>
                        <option value="completed"><?php _e('Completed', 'as24-sync'); ?></option>
                        <option value="stopped"><?php _e('Stopped', 'as24-sync'); ?></option>
                        <option value="failed"><?php _e('Failed', 'as24-sync'); ?></option>
                    </select>
                    <select id="as24-logs-action-filter" class="as24-logs-filter">
                        <option value=""><?php _e('All Actions', 'as24-sync'); ?></option>
                        <option value="imported"><?php _e('Imported', 'as24-sync'); ?></option>
                        <option value="updated"><?php _e('Updated', 'as24-sync'); ?></option>
                        <option value="error"><?php _e('Errors', 'as24-sync'); ?></option>
                    </select>
                    <input type="text" id="as24-logs-listing-id-filter" placeholder="<?php _e('Filter by Listing ID', 'as24-sync'); ?>" class="as24-logs-filter">
                </div>
                <div class="as24-logs-actions">
                    <label>
                        <input type="checkbox" id="as24-auto-refresh-logs">
                        <?php _e('Auto-refresh', 'as24-sync'); ?>
                    </label>
                    <button type="button" id="as24-refresh-logs" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span> <?php _e('Refresh', 'as24-sync'); ?>
                    </button>
                    <button type="button" id="as24-clear-logs" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Clear Logs', 'as24-sync'); ?>
                    </button>
                </div>
            </div>
            <div class="as24-logs-table-wrapper">
                <table class="wp-list-table widefat fixed striped as24-logs-table">
                    <thead>
                        <tr>
                            <th class="column-date"><?php _e('Date/Time', 'as24-sync'); ?></th>
                            <th class="column-type"><?php _e('Type', 'as24-sync'); ?></th>
                            <th class="column-status"><?php _e('Status/Action', 'as24-sync'); ?></th>
                            <th class="column-listing-id"><?php _e('Listing ID', 'as24-sync'); ?></th>
                            <th class="column-post-id"><?php _e('Post ID', 'as24-sync'); ?></th>
                            <th class="column-processed"><?php _e('Processed', 'as24-sync'); ?></th>
                            <th class="column-imported"><?php _e('Imported', 'as24-sync'); ?></th>
                            <th class="column-updated"><?php _e('Updated', 'as24-sync'); ?></th>
                            <th class="column-errors"><?php _e('Errors', 'as24-sync'); ?></th>
                            <th class="column-duration"><?php _e('Duration', 'as24-sync'); ?></th>
                            <th class="column-changes"><?php _e('Changes', 'as24-sync'); ?></th>
                            <th class="column-message"><?php _e('Message', 'as24-sync'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="as24-logs-tbody">
                        <tr>
                            <td colspan="12" class="as24-loading"><?php _e('Loading logs...', 'as24-sync'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

