<?php
/**
 * Settings View
 * 
 * @package AS24_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('as24_sync_settings', array());
$api_username = $settings['api_username'] ?? '';
$api_password = $settings['api_password'] ?? '';
$auto_import = $settings['auto_import'] ?? false;
$import_frequency = $settings['import_frequency'] ?? 'daily';
?>
<div class="wrap as24-sync-settings">
    <h1><?php _e('AS24 Sync Settings', 'as24-sync'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('as24_sync_settings', 'as24_sync_settings_nonce'); ?>
        <input type="hidden" name="as24_sync_save_settings" value="1">
        
        <div class="as24-settings-content">
            <!-- API Credentials Section -->
            <div class="as24-settings-section">
                <h2><?php _e('API Credentials', 'as24-sync'); ?></h2>
                <p class="description">
                    <?php _e('Enter your AutoScout24 API credentials. These will be stored securely.', 'as24-sync'); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_username"><?php _e('Username', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="api_username" 
                                   name="api_username" 
                                   value="<?php echo esc_attr($api_username); ?>" 
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_password"><?php _e('Password', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="api_password" 
                                   name="api_password" 
                                   value="<?php echo esc_attr($api_password); ?>" 
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button type="button" id="as24-test-connection" class="button button-secondary">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php _e('Test Connection', 'as24-sync'); ?>
                    </button>
                    <span id="as24-connection-status" class="as24-connection-status"></span>
                </p>
            </div>
            
            <!-- Import Settings Section -->
            <div class="as24-settings-section">
                <h2><?php _e('Import Settings', 'as24-sync'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="auto_import"><?php _e('Auto Import', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="auto_import" 
                                       name="auto_import" 
                                       value="1" 
                                       <?php checked($auto_import, true); ?>>
                                <?php _e('Enable automatic import', 'as24-sync'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="import_frequency"><?php _e('Import Frequency', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <select id="import_frequency" name="import_frequency">
                                <option value="hourly" <?php selected($import_frequency, 'hourly'); ?>>
                                    <?php _e('Hourly', 'as24-sync'); ?>
                                </option>
                                <option value="twicedaily" <?php selected($import_frequency, 'twicedaily'); ?>>
                                    <?php _e('Twice Daily', 'as24-sync'); ?>
                                </option>
                                <option value="daily" <?php selected($import_frequency, 'daily'); ?>>
                                    <?php _e('Daily', 'as24-sync'); ?>
                                </option>
                                <option value="weekly" <?php selected($import_frequency, 'weekly'); ?>>
                                    <?php _e('Weekly', 'as24-sync'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Sync Comparison Settings Section -->
            <div class="as24-settings-section">
                <h2><?php _e('Sync Comparison Settings', 'as24-sync'); ?></h2>
                <p class="description">
                    <?php _e('Configure how the system handles listings that exist only locally or only remotely.', 'as24-sync'); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="auto_delete_orphaned"><?php _e('Auto-delete Orphaned Listings', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="auto_delete_orphaned" 
                                       name="auto_delete_orphaned" 
                                       value="1" 
                                       <?php checked($settings['auto_delete_orphaned'] ?? false, true); ?>>
                                <?php _e('Automatically handle orphaned listings (exist locally but not in remote)', 'as24-sync'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, orphaned listings will be automatically processed based on the action below.', 'as24-sync'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="orphaned_action"><?php _e('Action for Orphaned', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <select id="orphaned_action" name="orphaned_action">
                                <option value="trash" <?php selected($settings['orphaned_action'] ?? 'trash', 'trash'); ?>>
                                    <?php _e('Trash', 'as24-sync'); ?>
                                </option>
                                <option value="draft" <?php selected($settings['orphaned_action'] ?? 'trash', 'draft'); ?>>
                                    <?php _e('Archive (Draft)', 'as24-sync'); ?>
                                </option>
                                <option value="mark" <?php selected($settings['orphaned_action'] ?? 'trash', 'mark'); ?>>
                                    <?php _e('Mark as Orphaned', 'as24-sync'); ?>
                                </option>
                                <option value="none" <?php selected($settings['orphaned_action'] ?? 'trash', 'none'); ?>>
                                    <?php _e('None (Do Nothing)', 'as24-sync'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('What action to take when orphaned listings are detected.', 'as24-sync'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="auto_import_missing"><?php _e('Auto-import Missing', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="auto_import_missing" 
                                       name="auto_import_missing" 
                                       value="1" 
                                       <?php checked($settings['auto_import_missing'] ?? false, true); ?>>
                                <?php _e('Automatically import missing listings (exist in remote but not locally)', 'as24-sync'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, missing listings will be automatically imported when detected.', 'as24-sync'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="run_comparison_on_complete"><?php _e('Run Comparison on Import Complete', 'as24-sync'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="run_comparison_on_complete" 
                                       name="run_comparison_on_complete" 
                                       value="1" 
                                       <?php checked($settings['run_comparison_on_complete'] ?? false, true); ?>>
                                <?php _e('Automatically run comparison when import completes', 'as24-sync'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, the system will automatically compare local vs remote listings after each import completes.', 'as24-sync'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Submit Button -->
            <p class="submit">
                <button type="submit" class="button button-primary button-large">
                    <?php _e('Save Settings', 'as24-sync'); ?>
                </button>
            </p>
        </div>
    </form>
</div>

