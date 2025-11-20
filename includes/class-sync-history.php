<?php
/**
 * Sync History - Database Logging
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Sync_History {
    
    /**
     * Add sync history record
     * 
     * @param string $operation_type Operation type (import, sync, etc.)
     * @param string $status Status (running, completed, failed, stopped)
     * @param array $stats Statistics array
     * @param string $message Optional message
     * @param array $details Optional details
     * @return int|false Record ID or false on failure
     */
    public static function add_record($operation_type, $status, $stats = array(), $message = '', $details = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'as24_sync_history';
        
        $data = array(
            'operation_type' => sanitize_text_field($operation_type),
            'status' => sanitize_text_field($status),
            'total_processed' => isset($stats['processed']) ? intval($stats['processed']) : 0,
            'total_imported' => isset($stats['imported']) ? intval($stats['imported']) : 0,
            'total_updated' => isset($stats['updated']) ? intval($stats['updated']) : 0,
            'total_removed' => isset($stats['removed']) ? intval($stats['removed']) : 0,
            'total_errors' => isset($stats['errors']) ? intval($stats['errors']) : 0,
            'duration' => isset($stats['duration']) ? floatval($stats['duration']) : 0,
            'message' => sanitize_text_field($message),
            'details' => !empty($details) ? maybe_serialize($details) : null,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            AS24_Logger::info(sprintf('Sync history record added: %s - %s', $operation_type, $status), 'general');
            return $wpdb->insert_id;
        }
        
        AS24_Logger::error('Failed to insert sync history record: ' . $wpdb->last_error, 'general');
        return false;
    }
    
    /**
     * Get sync history records
     * 
     * @param array $args Query arguments
     * @return array Records
     */
    public static function get_records($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'as24_sync_history';
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'status' => null,
            'operation_type' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if ($args['operation_type']) {
            $where[] = 'operation_type = %s';
            $where_values[] = $args['operation_type'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $query = $wpdb->prepare($query, $args['limit'], $args['offset']);
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get total count of records
     * 
     * @param array $args Query arguments
     * @return int Total count
     */
    public static function get_count($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'as24_sync_history';
        
        $where = array('1=1');
        $where_values = array();
        
        if (isset($args['status']) && $args['status']) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (isset($args['operation_type']) && $args['operation_type']) {
            $where[] = 'operation_type = %s';
            $where_values[] = $args['operation_type'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }
        
        $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        
        return (int) $wpdb->get_var($query);
    }
    
    /**
     * Clear all sync history records
     * 
     * @return int|false Number of deleted records or false on failure
     */
    public static function clear_all() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'as24_sync_history';
        
        $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        if ($result !== false) {
            AS24_Logger::info('Sync history cleared', 'general');
            return $result;
        }
        
        AS24_Logger::error('Failed to clear sync history: ' . $wpdb->last_error, 'general');
        return false;
    }
    
}

