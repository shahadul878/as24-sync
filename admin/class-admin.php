<?php
/**
 * Admin Interface
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Admin {
    
    /**
     * Add admin menu pages
     */
    public static function add_menu_pages() {
        // Main dashboard page
        add_menu_page(
            __('AS24 Sync', 'as24-sync'),
            __('AS24 Sync', 'as24-sync'),
            'manage_options',
            'as24-sync',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-update',
            30
        );
        
        // Dashboard submenu (same as main)
        add_submenu_page(
            'as24-sync',
            __('Dashboard', 'as24-sync'),
            __('Dashboard', 'as24-sync'),
            'manage_options',
            'as24-sync',
            array(__CLASS__, 'render_dashboard')
        );
        
        // Settings submenu
        add_submenu_page(
            'as24-sync',
            __('Settings', 'as24-sync'),
            __('Settings', 'as24-sync'),
            'manage_options',
            'as24-sync-settings',
            array(__CLASS__, 'render_settings')
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook Current admin page hook
     */
    public static function enqueue_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'as24-sync') === false) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'as24-sync-admin',
            AS24_SYNC_URL . 'assets/css/admin.css',
            array(),
            AS24_SYNC_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'as24-sync-admin',
            AS24_SYNC_URL . 'assets/js/admin.js',
            array('jquery'),
            AS24_SYNC_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('as24-sync-admin', 'as24Sync', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('as24_sync_nonce'),
            'strings' => array(
                'importing' => __('Importing...', 'as24-sync'),
                'import_complete' => __('Import completed!', 'as24-sync'),
                'import_failed' => __('Import failed', 'as24-sync'),
                'connection_testing' => __('Testing connection...', 'as24-sync'),
                'connection_success' => __('Connection successful!', 'as24-sync'),
                'connection_failed' => __('Connection failed', 'as24-sync')
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public static function render_dashboard() {
        require_once AS24_SYNC_PATH . 'admin/views/dashboard.php';
    }
    
    /**
     * Render settings page
     */
    public static function render_settings() {
        // Handle form submission
        if (isset($_POST['as24_sync_save_settings']) && check_admin_referer('as24_sync_settings', 'as24_sync_settings_nonce')) {
            $settings = array(
                'api_username' => sanitize_text_field($_POST['api_username'] ?? ''),
                'api_password' => sanitize_text_field($_POST['api_password'] ?? ''),
                'auto_import' => isset($_POST['auto_import']) ? true : false,
                'import_frequency' => sanitize_text_field($_POST['import_frequency'] ?? 'daily'),
                'auto_delete_orphaned' => isset($_POST['auto_delete_orphaned']) ? true : false,
                'orphaned_action' => sanitize_text_field($_POST['orphaned_action'] ?? 'trash'),
                'auto_import_missing' => isset($_POST['auto_import_missing']) ? true : false,
                'run_comparison_on_complete' => isset($_POST['run_comparison_on_complete']) ? true : false
            );
            
            update_option('as24_sync_settings', $settings);
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'as24-sync') . '</p></div>';
        }
        
        require_once AS24_SYNC_PATH . 'admin/views/settings.php';
    }
}

