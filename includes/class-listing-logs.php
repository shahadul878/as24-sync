<?php
/**
 * Listing Logs - Real-time change tracking
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Listing_Logs {
    
    /**
     * Add listing log entry
     * 
     * @param string $listing_id AutoScout24 listing ID
     * @param int $post_id WordPress post ID
     * @param string $action Action (imported, updated, error)
     * @param array $changes Array of changed fields
     * @param string $message Optional message
     * @return int|false Record ID or false on failure
     */
    public static function add_log($listing_id, $post_id = null, $action = 'updated', $changes = array(), $message = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'as24_listing_logs';
        
        $data = array(
            'listing_id' => sanitize_text_field($listing_id),
            'post_id' => $post_id ? intval($post_id) : null,
            'action' => sanitize_text_field($action),
            'changes' => !empty($changes) ? maybe_serialize($changes) : null,
            'message' => sanitize_text_field($message),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get recent listing logs
     * 
     * @param array $args Query arguments
     * @return array Records
     */
    public static function get_logs($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'as24_listing_logs';
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'listing_id' => null,
            'action' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $where_values = array();
        
        if ($args['listing_id']) {
            $where[] = 'listing_id = %s';
            $where_values[] = $args['listing_id'];
        }
        
        if ($args['action']) {
            $where[] = 'action = %s';
            $where_values[] = $args['action'];
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
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Unserialize changes
        foreach ($results as &$result) {
            if (!empty($result['changes'])) {
                $result['changes'] = maybe_unserialize($result['changes']);
            }
        }
        
        return $results;
    }
    
    /**
     * Compare old and new data to detect changes
     * 
     * @param array $old_data Old listing data
     * @param array $new_data New listing data
     * @return array Array of changed fields
     */
    public static function detect_changes($old_data, $new_data) {
        $changes = array();
        
        // Compare meta fields
        $meta_fields = array(
            'mileage' => 'Mileage',
            'price' => 'Price',
            'stm_genuine_price' => 'Price',
            'engine_power' => 'Engine Power',
            'fuel-consumption' => 'Fuel Consumption',
            'fuel-economy' => 'Fuel Economy',
            'as24-updated-at' => 'Last Updated'
        );
        
        foreach ($meta_fields as $key => $label) {
            $old_value = isset($old_data[$key]) ? $old_data[$key] : null;
            $new_value = isset($new_data[$key]) ? $new_data[$key] : null;
            
            if ($old_value != $new_value) {
                $changes[] = array(
                    'field' => $key,
                    'label' => $label,
                    'old' => $old_value,
                    'new' => $new_value
                );
            }
        }
        
        // Compare post title
        if (isset($old_data['post_title']) && isset($new_data['post_title'])) {
            if ($old_data['post_title'] != $new_data['post_title']) {
                $changes[] = array(
                    'field' => 'post_title',
                    'label' => 'Title',
                    'old' => $old_data['post_title'],
                    'new' => $new_data['post_title']
                );
            }
        }
        
        return $changes;
    }
    
    /**
     * Clear all listing logs
     * 
     * @return int|false Number of deleted records or false on failure
     */
    public static function clear_all() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'as24_listing_logs';
        
        $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
        
        if ($result !== false) {
            return $result;
        }
        
        return false;
    }
}

