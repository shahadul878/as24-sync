<?php
/**
 * Import Orchestrator - Main Coordinator Enforcing Step-by-Step Flow
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Import_Orchestrator {
    
    /**
     * Start import - Main entry point
     * Enforces step-by-step flow:
     * 1. Validate API connection (BLOCK if fails)
     * 2. Get total listings count
     * 3. Collect all listing IDs
     * 4. Process listings one by one
     * 5. Queue images for cron
     * 
     * @return array|WP_Error Import result or error
     */
    public static function start_import() {
        AS24_Logger::info('=== Starting Import Process ===', 'import');
        
        // Step 1: Validate API connection (MANDATORY)
        AS24_Logger::info('[Step 1] Validating API connection...', 'import');
        $validation = AS24_API_Validator::validate_connection();
        
        if (is_wp_error($validation)) {
            AS24_Logger::error('[Step 1] API validation failed: ' . $validation->get_error_message(), 'import');
            AS24_Progress_Tracker::update_progress('step1_failed', array(
                'error' => $validation->get_error_message()
            ));
            return $validation;
        }
        
        AS24_Logger::info('[Step 1] API validation passed', 'import');
        AS24_Progress_Tracker::update_progress('step1_complete', $validation);
        
        // Step 2: Get total listings count
        AS24_Logger::info('[Step 2] Getting total listings count...', 'import');
        $total = AS24_Queue_Manager::get_total_listings();
        
        if (is_wp_error($total)) {
            AS24_Logger::error('[Step 2] Failed to get total count: ' . $total->get_error_message(), 'import');
            AS24_Progress_Tracker::update_progress('step2_failed', array(
                'error' => $total->get_error_message()
            ));
            return $total;
        }
        
        AS24_Logger::info(sprintf('[Step 2] Total listings: %d', $total), 'import');
        AS24_Progress_Tracker::update_progress('step2_complete', array('total' => $total));
        
        // Step 3: Collect all listing IDs
        AS24_Logger::info('[Step 3] Collecting all listing IDs...', 'import');
        $ids = AS24_Queue_Manager::collect_all_listing_ids();
        
        if (is_wp_error($ids)) {
            AS24_Logger::error('[Step 3] Failed to collect IDs: ' . $ids->get_error_message(), 'import');
            AS24_Progress_Tracker::update_progress('step3_failed', array(
                'error' => $ids->get_error_message()
            ));
            return $ids;
        }
        
        AS24_Logger::info(sprintf('[Step 3] Collected %d listing IDs', count($ids)), 'import');
        AS24_Progress_Tracker::update_progress('step3_complete', array('total_ids' => count($ids)));
        
        // Step 4: Initialize import state
        $import_state = array(
            'status' => 'running',
            'step' => 'processing',
            'total' => count($ids),
            'processed' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'start_time' => time(),
            'last_update' => time()
        );
        update_option('as24_import_state', $import_state);
        
        // Schedule first listing processing
        wp_schedule_single_event(time(), 'as24_process_single_listing');
        
        AS24_Logger::info('[Step 4] Import started - processing listings one by one', 'import');
        AS24_Progress_Tracker::update_progress('step4_started', $import_state);
        
        // Log import start to database
        AS24_Sync_History::add_record(
            'import',
            'running',
            array('total' => count($ids)),
            'Import started successfully',
            array('total_ids' => count($ids))
        );
        
        return array(
            'status' => 'started',
            'total' => count($ids),
            'message' => __('Import started successfully. Processing listings one by one...', 'as24-sync')
        );
    }
    
    /**
     * Process next listing from queue (called by cron)
     */
    public static function process_next_listing() {
        $import_state = get_option('as24_import_state');
        
        if (!$import_state || $import_state['status'] !== 'running') {
            AS24_Logger::info('No active import process', 'import');
            return;
        }
        
        // Get next listing ID from queue
        $next_listing = AS24_Queue_Manager::get_next_listing_id();
        
        if (!$next_listing || !is_array($next_listing)) {
            // Queue empty - import complete
            self::complete_import($import_state);
            return;
        }
        
        $listing_id = isset($next_listing['id']) ? $next_listing['id'] : null;
        
        if (empty($listing_id)) {
            AS24_Logger::warning('Invalid listing data from queue, skipping', 'import');
            // Schedule next listing
            wp_schedule_single_event(time() + 1, 'as24_process_single_listing');
            return;
        }
        AS24_Logger::info(sprintf('[Step 4] Processing listing %d of %d (ID: %s)', 
            $import_state['processed'] + 1, $import_state['total'], $listing_id), 'import');
        
        // Process the listing
        $result = AS24_Listing_Processor::process_single_listing($listing_id);
        
        if (is_wp_error($result)) {
            $import_state['errors']++;
            AS24_Logger::error(sprintf('[Step 4] Failed to process listing %s: %s', 
                $listing_id, $result->get_error_message()), 'import');
        } else {
            $import_state['processed']++;
            
            if ($result['action'] === 'imported') {
                $import_state['imported']++;
            } elseif ($result['action'] === 'updated') {
                $import_state['updated']++;
            } else {
                $import_state['skipped']++;
            }
            
            // Mark as complete
            AS24_Queue_Manager::mark_complete($listing_id);
        }
        
        $import_state['last_update'] = time();
        update_option('as24_import_state', $import_state);
        
        // Update progress
        $progress = round(($import_state['processed'] / $import_state['total']) * 100, 2);
        AS24_Progress_Tracker::update_progress('step4_progress', array(
            'processed' => $import_state['processed'],
            'total' => $import_state['total'],
            'progress' => $progress,
            'imported' => $import_state['imported'],
            'updated' => $import_state['updated'],
            'errors' => $import_state['errors']
        ));
        
        // Schedule next listing processing
        wp_schedule_single_event(time() + 1, 'as24_process_single_listing');
    }
    
    /**
     * Complete import process
     * 
     * @param array $import_state Import state
     */
    private static function complete_import($import_state) {
        $duration = time() - $import_state['start_time'];
        
        $import_state['status'] = 'completed';
        $import_state['duration'] = $duration;
        $import_state['completed_at'] = current_time('mysql');
        
        update_option('as24_import_state', $import_state);
        
        AS24_Logger::info(sprintf('=== Import Complete: %d processed, %d imported, %d updated, %d errors in %d seconds ===', 
            $import_state['processed'], 
            $import_state['imported'], 
            $import_state['updated'], 
            $import_state['errors'],
            $duration), 'import');
        
        AS24_Progress_Tracker::update_progress('step4_complete', $import_state);
        
        // Log to database
        AS24_Sync_History::add_record(
            'import',
            'completed',
            array(
                'processed' => $import_state['processed'],
                'imported' => $import_state['imported'],
                'updated' => $import_state['updated'],
                'errors' => $import_state['errors'],
                'duration' => $duration
            ),
            sprintf('Import completed: %d imported, %d updated, %d errors', 
                $import_state['imported'], 
                $import_state['updated'], 
                $import_state['errors']),
            $import_state
        );
        
        // Update last import time
        as24_sync()->update_setting('last_import', time());
        
        // Run comparison and auto-actions if enabled
        self::run_post_import_comparison();
    }
    
    /**
     * Resume import from last position
     * 
     * @return array|WP_Error Resume result or error
     */
    public static function resume_import() {
        $import_state = get_option('as24_import_state');
        
        if (!$import_state || $import_state['status'] !== 'running') {
            return new WP_Error('no_active_import', __('No active import to resume.', 'as24-sync'));
        }
        
        // Check if queue exists
        if (!AS24_Queue_Manager::has_queue()) {
            return new WP_Error('no_queue', __('No listing queue found. Please start a new import.', 'as24-sync'));
        }
        
        // Schedule next listing processing
        wp_schedule_single_event(time(), 'as24_process_single_listing');
        
        AS24_Logger::info('Import resumed', 'import');
        
        return array(
            'status' => 'resumed',
            'message' => __('Import resumed successfully.', 'as24-sync')
        );
    }
    
    /**
     * Get import status
     * 
     * @return array Import status
     */
    /**
     * Run comparison and auto-actions after import completes
     * 
     * @return void
     */
    private static function run_post_import_comparison() {
        $settings = get_option('as24_sync_settings', array());
        
        // Check if comparison should run on import complete
        if (!isset($settings['run_comparison_on_complete']) || !$settings['run_comparison_on_complete']) {
            return;
        }
        
        AS24_Logger::info('Running post-import comparison...', 'sync');
        
        // Run comparison
        $comparison = AS24_Sync_Comparator::compare_listings();
        
        if (is_wp_error($comparison)) {
            AS24_Logger::error('Post-import comparison failed: ' . $comparison->get_error_message(), 'sync');
            return;
        }
        
        // Handle orphaned listings if auto-delete is enabled
        if (isset($settings['auto_delete_orphaned']) && $settings['auto_delete_orphaned']) {
            if (!empty($comparison['orphaned_local'])) {
                $action = $settings['orphaned_action'] ?? 'trash';
                if ($action !== 'none') {
                    AS24_Logger::info(sprintf('Auto-handling %d orphaned listings with action: %s', 
                        count($comparison['orphaned_local']), $action), 'sync');
                    
                    $result = AS24_Sync_Comparator::handle_orphaned_listings(
                        array_keys($comparison['orphaned_local']),
                        $action
                    );
                    
                    if ($result['success']) {
                        AS24_Logger::info('Auto-handled orphaned listings: ' . $result['message'], 'sync');
                    } else {
                        AS24_Logger::error('Failed to auto-handle orphaned listings: ' . $result['message'], 'sync');
                    }
                }
            }
        }
        
        // Import missing listings if auto-import is enabled
        if (isset($settings['auto_import_missing']) && $settings['auto_import_missing']) {
            if (!empty($comparison['missing_remote'])) {
                AS24_Logger::info(sprintf('Auto-importing %d missing listings', 
                    count($comparison['missing_remote'])), 'sync');
                
                $result = AS24_Sync_Comparator::import_missing_listings($comparison['missing_remote']);
                
                if ($result['success']) {
                    AS24_Logger::info('Auto-imported missing listings: ' . $result['message'], 'sync');
                } else {
                    AS24_Logger::error('Failed to auto-import missing listings: ' . $result['message'], 'sync');
                }
            }
        }
        
        AS24_Logger::info('Post-import comparison completed', 'sync');
    }
    
    /**
     * Get local listings count from WordPress database
     * 
     * @return int Number of listings in WordPress
     */
    public static function get_local_listings_count() {
        $count = wp_count_posts('listings');
        return isset($count->publish) ? intval($count->publish) : 0;
    }
    
    /**
     * Get import status
     */
    public static function get_import_status() {
        $import_state = get_option('as24_import_state');
        $queue_progress = AS24_Queue_Manager::get_queue_progress();
        
        if (!$import_state) {
            return array(
                'status' => 'idle',
                'message' => __('No import in progress.', 'as24-sync'),
                'total' => 0,
                'local_count' => self::get_local_listings_count()
            );
        }
        
        $status = array(
            'status' => $import_state['status'],
            'step' => isset($import_state['step']) ? $import_state['step'] : 'unknown',
            'total' => isset($import_state['total']) ? $import_state['total'] : 0,
            'local_count' => self::get_local_listings_count(),
            'processed' => isset($import_state['processed']) ? $import_state['processed'] : 0,
            'imported' => isset($import_state['imported']) ? $import_state['imported'] : 0,
            'updated' => isset($import_state['updated']) ? $import_state['updated'] : 0,
            'skipped' => isset($import_state['skipped']) ? $import_state['skipped'] : 0,
            'errors' => isset($import_state['errors']) ? $import_state['errors'] : 0,
            'progress_percent' => 0,
            'queue_progress' => $queue_progress
        );
        
        if ($status['total'] > 0) {
            $status['progress_percent'] = round(($status['processed'] / $status['total']) * 100, 2);
        }
        
        if ($import_state['status'] === 'running') {
            $status['message'] = sprintf(
                __('Processing %d of %d listings (%s%%)...', 'as24-sync'),
                $status['processed'],
                $status['total'],
                $status['progress_percent']
            );
        } elseif ($import_state['status'] === 'completed') {
            $status['message'] = sprintf(
                __('Import completed: %d imported, %d updated, %d errors', 'as24-sync'),
                $status['imported'],
                $status['updated'],
                $status['errors']
            );
        }
        
        return $status;
    }
    
    /**
     * Stop import
     * 
     * @return array Stop result
     */
    public static function stop_import() {
        $import_state = get_option('as24_import_state');
        
        if (!$import_state || $import_state['status'] !== 'running') {
            return array(
                'success' => false,
                'message' => __('No active import to stop.', 'as24-sync')
            );
        }
        
        // Clear scheduled events
        wp_clear_scheduled_hook('as24_process_single_listing');
        
        // Update status
        $import_state['status'] = 'stopped';
        $import_state['stopped_at'] = current_time('mysql');
        update_option('as24_import_state', $import_state);
        
        AS24_Logger::info('Import stopped by user', 'import');
        
        // Log import stop to database
        $duration = isset($import_state['start_time']) ? time() - $import_state['start_time'] : 0;
        AS24_Sync_History::add_record(
            'import',
            'stopped',
            array(
                'processed' => $import_state['processed'] ?? 0,
                'imported' => $import_state['imported'] ?? 0,
                'updated' => $import_state['updated'] ?? 0,
                'errors' => $import_state['errors'] ?? 0,
                'duration' => $duration
            ),
            'Import stopped by user',
            $import_state
        );
        
        return array(
            'success' => true,
            'message' => __('Import stopped successfully.', 'as24-sync')
        );
    }
}

