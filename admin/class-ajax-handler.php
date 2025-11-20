<?php
/**
 * AJAX Handler
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Ajax_Handler {
    
    /**
     * Initialize AJAX hooks
     */
    public static function init_hooks() {
        add_action('wp_ajax_as24_test_connection', array(__CLASS__, 'ajax_test_connection'));
        add_action('wp_ajax_as24_start_import', array(__CLASS__, 'ajax_start_import'));
        add_action('wp_ajax_as24_get_progress', array(__CLASS__, 'ajax_get_progress'));
        add_action('wp_ajax_as24_resume_import', array(__CLASS__, 'ajax_resume_import'));
        add_action('wp_ajax_as24_stop_import', array(__CLASS__, 'ajax_stop_import'));
        add_action('wp_ajax_as24_get_sync_history', array(__CLASS__, 'ajax_get_sync_history'));
        add_action('wp_ajax_as24_get_listing_logs', array(__CLASS__, 'ajax_get_listing_logs'));
        add_action('wp_ajax_as24_clear_logs', array(__CLASS__, 'ajax_clear_logs'));
    }
    
    /**
     * Verify nonce and capabilities
     */
    private static function verify_request() {
        check_ajax_referer('as24_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'as24-sync')));
        }
    }
    
    /**
     * Test API connection
     */
    public static function ajax_test_connection() {
        self::verify_request();
        
        $validation = AS24_API_Validator::validate_connection();
        
        if (is_wp_error($validation)) {
            wp_send_json_error(array(
                'message' => $validation->get_error_message(),
                'errors' => $validation->get_error_data()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('API connection validated successfully!', 'as24-sync'),
            'total_listings' => $validation['total_listings'] ?? 0
        ));
    }
    
    /**
     * Start import
     */
    public static function ajax_start_import() {
        self::verify_request();
        
        // Reset previous import state
        AS24_Queue_Manager::reset_queue();
        delete_option('as24_import_state');
        delete_option('as24_import_progress');
        
        $result = AS24_Import_Orchestrator::start_import();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'error_data' => $result->get_error_data()
            ));
        }
        
        // Include import status in response
        $import_status = AS24_Import_Orchestrator::get_import_status();
        $result['import_status'] = $import_status;
        
        wp_send_json_success($result);
    }
    
    /**
     * Get progress
     */
    public static function ajax_get_progress() {
        self::verify_request();
        
        $progress = AS24_Progress_Tracker::get_progress();
        $import_status = AS24_Import_Orchestrator::get_import_status();
        $import_state = get_option('as24_import_state');
        
        // Get local listings count
        $local_count = AS24_Import_Orchestrator::get_local_listings_count();
        $import_status['local_count'] = $local_count;
        
        // Get last update timestamp
        $last_update = isset($import_state['last_update']) ? intval($import_state['last_update']) : 0;
        
        // If client sent last_update, check if it's still the same
        $client_last_update = isset($_POST['last_update']) ? intval($_POST['last_update']) : 0;
        
        // If timestamp hasn't changed, we can send minimal response
        if ($client_last_update > 0 && $client_last_update >= $last_update) {
            wp_send_json_success(array(
                'progress' => $progress,
                'import_status' => $import_status,
                'last_update' => $last_update,
                'unchanged' => true // Signal that data hasn't changed
            ));
            return;
        }
        
        wp_send_json_success(array(
            'progress' => $progress,
            'import_status' => $import_status,
            'last_update' => $last_update
        ));
    }
    
    /**
     * Resume import
     */
    public static function ajax_resume_import() {
        self::verify_request();
        
        $result = AS24_Import_Orchestrator::resume_import();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        // Include import status in response
        $import_status = AS24_Import_Orchestrator::get_import_status();
        $result['import_status'] = $import_status;
        
        wp_send_json_success($result);
    }
    
    /**
     * Stop import
     */
    public static function ajax_stop_import() {
        self::verify_request();
        
        $result = AS24_Import_Orchestrator::stop_import();
        
        if (!$result['success']) {
            wp_send_json_error($result);
        }
        
        // Include import status in response
        $import_status = AS24_Import_Orchestrator::get_import_status();
        $result['import_status'] = $import_status;
        
        wp_send_json_success($result);
    }
    
    /**
     * Get sync history logs
     */
    public static function ajax_get_sync_history() {
        self::verify_request();
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        
        $args = array(
            'limit' => $limit,
            'offset' => $offset,
            'status' => $status
        );
        
        $logs = AS24_Sync_History::get_records($args);
        $total = AS24_Sync_History::get_count($args);
        
        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total
        ));
    }
    
    /**
     * Get listing logs for real-time display
     */
    public static function ajax_get_listing_logs() {
        self::verify_request();
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $listing_id = isset($_POST['listing_id']) ? sanitize_text_field($_POST['listing_id']) : null;
        $action = isset($_POST['action_filter']) ? sanitize_text_field($_POST['action_filter']) : null;
        
        $args = array(
            'limit' => $limit,
            'offset' => $offset,
            'listing_id' => $listing_id,
            'action' => $action
        );
        
        $logs = AS24_Listing_Logs::get_logs($args);
        
        wp_send_json_success(array(
            'logs' => $logs
        ));
    }
    
    /**
     * Clear all logs (sync history and listing logs)
     */
    public static function ajax_clear_logs() {
        self::verify_request();
        
        $history_cleared = AS24_Sync_History::clear_all();
        $listing_logs_cleared = AS24_Listing_Logs::clear_all();
        
        if ($history_cleared !== false && $listing_logs_cleared !== false) {
            wp_send_json_success(array(
                'message' => __('All logs cleared successfully', 'as24-sync'),
                'history_cleared' => $history_cleared,
                'listing_logs_cleared' => $listing_logs_cleared
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to clear logs', 'as24-sync')
            ));
        }
    }
}

