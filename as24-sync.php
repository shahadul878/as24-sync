<?php
/**
 * Plugin Name: AS24 Sync
 * Plugin URI: https://github.com/shahadul878/as24-sync
 * Description: High-performance AutoScout24 synchronization with step-by-step import process, mandatory API validation, and optimized image processing. WP code standards compliant.
 * Version: 2.0.0
 * Author: H M Shahadul Islam
 * Author URI: https://github.com/shahadul878
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: as24-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AS24_SYNC_VERSION', '2.0.0');
define('AS24_SYNC_FILE', __FILE__);
define('AS24_SYNC_PATH', plugin_dir_path(__FILE__));
define('AS24_SYNC_URL', plugin_dir_url(__FILE__));
define('AS24_SYNC_BASENAME', plugin_basename(__FILE__));

// Core includes
require_once AS24_SYNC_PATH . 'includes/class-logger.php';
require_once AS24_SYNC_PATH . 'includes/class-queries.php';
require_once AS24_SYNC_PATH . 'includes/class-api-validator.php';
require_once AS24_SYNC_PATH . 'includes/class-queue-manager.php';
require_once AS24_SYNC_PATH . 'includes/class-field-mapper.php';
require_once AS24_SYNC_PATH . 'includes/class-listing-processor.php';
require_once AS24_SYNC_PATH . 'includes/class-image-queue.php';
require_once AS24_SYNC_PATH . 'includes/class-image-processor.php';
require_once AS24_SYNC_PATH . 'includes/class-import-orchestrator.php';
require_once AS24_SYNC_PATH . 'includes/class-progress-tracker.php';
require_once AS24_SYNC_PATH . 'includes/class-sync-history.php';
require_once AS24_SYNC_PATH . 'includes/class-listing-logs.php';
require_once AS24_SYNC_PATH . 'includes/class-sync-comparator.php';

// Admin includes
if (is_admin()) {
    require_once AS24_SYNC_PATH . 'admin/class-admin.php';
    require_once AS24_SYNC_PATH . 'admin/class-ajax-handler.php';
}

/**
 * Main Plugin Class
 */
class AS24_Sync {
    
    /**
     * Instance of this class
     * @var AS24_Sync
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     * @var array
     */
    private $settings = array();
    
    /**
     * Get singleton instance
     * 
     * @return AS24_Sync
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
        $this->init_hooks();
        
        // Initialize logger
        AS24_Logger::init();
        
        // Log plugin loaded
        AS24_Logger::info('Plugin loaded - version ' . AS24_SYNC_VERSION, 'general');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(AS24_SYNC_FILE, array($this, 'activate'));
        register_deactivation_hook(AS24_SYNC_FILE, array($this, 'deactivate'));
        
        // Init hook
        add_action('init', array($this, 'init'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array('AS24_Admin', 'add_menu_pages'));
            add_action('admin_enqueue_scripts', array('AS24_Admin', 'enqueue_assets'));
            
            // Add settings link on plugins page
            add_filter('plugin_action_links_' . AS24_SYNC_BASENAME, array($this, 'add_plugin_action_links'));
            
            // AJAX hooks
            AS24_Ajax_Handler::init_hooks();
        }
        
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Background image processing hook
        add_action('as24_process_image_queue', array('AS24_Image_Processor', 'process_image_queue'), 10, 1);
        
        // Background listing processing hook
        add_action('as24_process_single_listing', array('AS24_Import_Orchestrator', 'process_next_listing'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('as24-sync', false, dirname(AS24_SYNC_BASENAME) . '/languages');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        AS24_Logger::info('=== Plugin Activation Started ===', 'general');
        
        // Set default settings
        $default_settings = array(
            'api_username' => '',
            'api_password' => '',
            'auto_import' => false,
            'import_frequency' => 'daily',
            'auto_delete_orphaned' => false,
            'orphaned_action' => 'trash',
            'auto_import_missing' => false,
            'run_comparison_on_complete' => false,
            'version' => AS24_SYNC_VERSION,
            'installed_at' => current_time('mysql')
        );
        
        add_option('as24_sync_settings', $default_settings);
        
        // Create necessary database tables
        $this->create_tables();
        
        // Schedule image processing cron
        if (!wp_next_scheduled('as24_process_image_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'as24_process_image_queue');
        }
        
        AS24_Logger::info('=== Plugin Activation Completed ===', 'general');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        AS24_Logger::info('=== Plugin Deactivation Started ===', 'general');
        
        // Clear all scheduled cron jobs
        wp_clear_scheduled_hook('as24_process_image_queue');
        wp_clear_scheduled_hook('as24_process_single_listing');
        
        AS24_Logger::info('=== Plugin Deactivation Completed ===', 'general');
    }
    
    /**
     * Create necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sync history table for tracking operations
        $table_name = $wpdb->prefix . 'as24_sync_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            total_processed int(11) DEFAULT 0,
            total_imported int(11) DEFAULT 0,
            total_updated int(11) DEFAULT 0,
            total_removed int(11) DEFAULT 0,
            total_errors int(11) DEFAULT 0,
            duration float DEFAULT 0,
            message text,
            details longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY operation_type (operation_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Listing logs table for real-time change tracking
        $listing_logs_table = $wpdb->prefix . 'as24_listing_logs';
        
        $sql_listing_logs = "CREATE TABLE IF NOT EXISTS $listing_logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            listing_id varchar(100) NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            action varchar(20) NOT NULL,
            changes longtext,
            message text,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY listing_id (listing_id),
            KEY post_id (post_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql_listing_logs);
        
        AS24_Logger::info('Database tables created/verified', 'general');
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $this->settings = get_option('as24_sync_settings', array());
    }
    
    /**
     * Get plugin setting
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Update plugin setting
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        return update_option('as24_sync_settings', $this->settings);
    }
    
    /**
     * Add action links on plugins page
     * 
     * @param array $links Existing plugin action links
     * @return array Modified links
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=as24-sync') . '">' . 
                '<strong style="color: #0073aa;">' . __('Dashboard', 'as24-sync') . '</strong>' .
            '</a>',
            '<a href="' . admin_url('admin.php?page=as24-sync-settings') . '">' . 
                __('Settings', 'as24-sync') . 
            '</a>'
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Add custom cron intervals
     * 
     * @param array $schedules Existing cron schedules
     * @return array Modified cron schedules
     */
    public function add_cron_intervals($schedules) {
        $schedules['five_minutes'] = array(
            'interval' => 300, // 5 minutes in seconds
            'display' => __('Every 5 minutes', 'as24-sync')
        );
        return $schedules;
    }
    
    /**
     * Get API credentials
     * 
     * @return array|false Array with username and password or false if not set
     */
    public function get_api_credentials() {
        $username = $this->get_setting('api_username');
        $password = $this->get_setting('api_password');
        
        if (empty($username) || empty($password)) {
            return false;
        }
        
        return array(
            'username' => $username,
            'password' => $password
        );
    }
}

/**
 * Get main plugin instance
 * 
 * @return AS24_Sync
 */
function as24_sync() {
    return AS24_Sync::instance();
}


/**
 * Register the widget with Elementor
 */
function autoscout24_register_elementor_widget() {
	// Only proceed if Elementor is loaded
	if (!class_exists('\Elementor\Plugin')) {
		return;
	}

	// Include the widget file
	require_once AS24_IMPORTER_PLUGIN_PATH . 'includes/elementor/price-details-widget.php';

	// Register the widget
	\Elementor\Plugin::instance()->widgets_manager->register(new AS24_Elementor_Widget_Price_Details());
}

// Register the widget category
function autoscout24_register_elementor_category($elements_manager) {
	$elements_manager->add_category(
		'autoscout24',
		array(
			'title' => __('AutoScout24', 'autoscout24-importer'),
			'icon'  => 'fa fa-car',
		)
	);
}
add_action('elementor/elements/categories_registered', 'autoscout24_register_elementor_category');

// Hook into Elementor's widgets_registered action
add_action('elementor/widgets/widgets_registered', 'autoscout24_register_elementor_widget');

// Initialize plugin
as24_sync();

