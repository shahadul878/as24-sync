# AS24 Sync - Complete User Documentation

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Getting Started](#getting-started)
4. [Configuration](#configuration)
5. [Using the Dashboard](#using-the-dashboard)
6. [Import Process](#import-process)
7. [Sync Analysis & Comparison](#sync-analysis--comparison)
8. [Activity Logs](#activity-logs)
9. [Troubleshooting](#troubleshooting)
10. [FAQ](#faq)

---

## Overview

**AS24 Sync** is a high-performance WordPress plugin that synchronizes vehicle listings from AutoScout24 to your WordPress website. The plugin is designed to handle large datasets efficiently with a step-by-step import process that prevents timeouts and ensures reliable data synchronization.

### Key Features

- **Step-by-Step Import Process**: Validates API connection, collects all listing IDs, and processes listings sequentially
- **Progress Tracking**: Real-time progress updates with detailed statistics
- **Error Recovery**: Resume imports from the last position if interrupted
- **Background Image Processing**: Images are processed via cron jobs to avoid blocking the import
- **Sync Comparison**: Compare local vs remote listings to identify orphaned or missing listings
- **Automatic Synchronization**: Schedule automatic imports at regular intervals
- **Comprehensive Logging**: Track all import activities with detailed logs
- **WP Code Standards Compliant**: Built following WordPress coding standards

### Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- AutoScout24 API credentials (username and password)
- Motors theme (for listings post type) - recommended

---

## Installation

### Step 1: Upload the Plugin

1. Download or clone the `as24-sync` plugin folder
2. Upload the folder to `/wp-content/plugins/` directory on your WordPress installation
3. Ensure the folder structure is: `/wp-content/plugins/as24-sync/`

### Step 2: Activate the Plugin

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins** → **Installed Plugins**
3. Find **AS24 Sync** in the list
4. Click **Activate**

### Step 3: Verify Installation

After activation, you should see:
- A new menu item **AS24 Sync** in your WordPress admin sidebar
- The plugin creates necessary database tables automatically
- A cron job is scheduled for image processing (runs every 5 minutes)

---

## Getting Started

### First-Time Setup

1. **Navigate to Settings**
   - Go to **AS24 Sync** → **Settings** in your WordPress admin menu
   - Or click the **Settings** link on the Plugins page

2. **Enter API Credentials**
   - Enter your AutoScout24 API **Username**
   - Enter your AutoScout24 API **Password**
   - Click **Test Connection** to verify your credentials
   - Wait for the connection status message

3. **Save Settings**
   - Click **Save Settings** at the bottom of the page
   - Your credentials are stored securely in the WordPress database

### Understanding the Interface

The plugin provides two main pages:

- **Dashboard** (`AS24 Sync` → `Dashboard`): Main control center for imports and monitoring
- **Settings** (`AS24 Sync` → `Settings`): Configuration and API credentials

---

## Configuration

### API Credentials

**Location**: AS24 Sync → Settings → API Credentials

#### Username
- Your AutoScout24 API username
- Required field
- Must be valid AutoScout24 credentials

#### Password
- Your AutoScout24 API password
- Required field
- Stored securely in WordPress database

#### Test Connection
- Click this button to verify your API credentials
- The plugin will attempt to connect to AutoScout24 API
- You'll see a success or error message indicating the connection status
- **Important**: Always test your connection before starting an import

### Import Settings

**Location**: AS24 Sync → Settings → Import Settings

#### Auto Import
- **Enable**: Check this box to enable automatic imports
- When enabled, the plugin will automatically import listings based on the frequency setting
- **Default**: Disabled (manual imports only)

#### Import Frequency
- **Hourly**: Imports run every hour
- **Twice Daily**: Imports run twice per day (every 12 hours)
- **Daily**: Imports run once per day (recommended)
- **Weekly**: Imports run once per week
- **Default**: Daily

**Note**: Auto-import only works if WordPress cron is functioning properly. Some hosting providers disable WordPress cron, requiring you to set up a server-level cron job.

### Sync Comparison Settings

**Location**: AS24 Sync → Settings → Sync Comparison Settings

#### Auto-delete Orphaned Listings
- **Enable**: Automatically handle listings that exist locally but not in AutoScout24
- When enabled, orphaned listings are processed based on the action setting below
- **Default**: Disabled

#### Action for Orphaned
- **Trash**: Move orphaned listings to WordPress trash
- **Archive (Draft)**: Change orphaned listings to draft status
- **Mark as Orphaned**: Add a meta tag to identify orphaned listings (no status change)
- **None (Do Nothing)**: Take no action on orphaned listings
- **Default**: Trash

#### Auto-import Missing
- **Enable**: Automatically import listings that exist in AutoScout24 but not locally
- When enabled, missing listings are automatically imported when detected
- **Default**: Disabled

#### Run Comparison on Import Complete
- **Enable**: Automatically run comparison when import completes
- When enabled, the system compares local vs remote listings after each import
- If auto-actions are enabled, they will be executed automatically
- **Default**: Disabled

---

## Using the Dashboard

The Dashboard is your main control center for managing imports and monitoring sync status.

### Accessing the Dashboard

Navigate to **AS24 Sync** → **Dashboard** in your WordPress admin menu.

### Dashboard Sections

#### 1. Statistics Cards

The top section displays key statistics:

- **Total Listings**
  - **Remote**: Number of listings available in AutoScout24
  - **Local**: Number of listings currently in your WordPress database
- **Processed**: Total number of listings processed during current import
- **Imported**: Number of new listings imported
- **Updated**: Number of existing listings updated
- **Errors**: Number of errors encountered during import
- **Progress**: Percentage of import completion

These statistics update in real-time during an active import.

#### 2. Sync Analysis

This section helps you compare local and remote listings.

**Compare Listings Button**
- Click to compare local listings with AutoScout24 listings
- The comparison identifies:
  - **Orphaned Local**: Listings in WordPress but not in AutoScout24
  - **Missing Remote**: Listings in AutoScout24 but not in WordPress
  - **Synced**: Listings that exist in both places

**Orphaned Local Listings**
- Shows listings that exist locally but not in AutoScout24
- Select an action from the dropdown:
  - Move to Trash
  - Change to Draft
  - Mark as Orphaned
  - Delete Permanently
- Click **Handle Orphaned** to execute the action

**Missing Remote Listings**
- Shows listings that exist in AutoScout24 but not locally
- Click **Import Missing** to import these listings

**Refresh Analysis Button**
- Click to refresh the comparison results
- Useful after making changes to listings

#### 3. Import Progress

Displays the current import status with:
- Current step indicator (API Validation, Total Count, ID Collection, Processing)
- Progress bar showing percentage completion
- Real-time status messages
- Step-by-step progress indicators

#### 4. Actions

**Start Import**
- Begins a new import process
- Only available when no import is running
- The plugin will:
  1. Validate API connection
  2. Get total listings count
  3. Collect all listing IDs
  4. Process listings one by one

**Resume Import**
- Resumes a stopped or interrupted import
- Only available when an import was stopped
- Continues from the last processed listing

**Stop Import**
- Stops the current import process
- Only available when an import is running
- Progress is saved, allowing you to resume later

**Refresh Status**
- Manually refreshes the import status
- Updates all statistics and progress indicators

**Settings**
- Quick link to the Settings page

#### 5. Activity Logs

Comprehensive logging system with three tabs:

**All Logs Tab**
- Combined view of all activity
- Shows sync history and listing logs together
- Chronologically sorted

**Sync History Tab**
- High-level import operations
- Shows import start, completion, and summary statistics
- Includes duration and overall results

**Listing Logs Tab**
- Individual listing operations
- Shows each listing's import, update, or error
- Includes detailed change information

**Log Filters**
- **Status Filter**: Filter by Running, Completed, Stopped, or Failed
- **Action Filter**: Filter by Imported, Updated, or Errors
- **Listing ID Filter**: Search for specific listing IDs

**Log Controls**
- **Auto-refresh**: Automatically refresh logs every few seconds
- **Refresh**: Manually refresh the logs
- **Clear Logs**: Delete all log entries (use with caution)

**Log Columns**
- **Date/Time**: When the action occurred
- **Type**: Sync History or Listing Log
- **Status/Action**: Current status or action taken
- **Listing ID**: AutoScout24 listing identifier
- **Post ID**: WordPress post ID (if imported)
- **Processed**: Number of listings processed
- **Imported**: Number of listings imported
- **Updated**: Number of listings updated
- **Errors**: Number of errors
- **Duration**: Time taken (for sync operations)
- **Changes**: What changed (for listing updates)
- **Message**: Detailed message about the action

---

## Import Process

The plugin uses a step-by-step import process to ensure reliability and prevent timeouts.

### Step 1: API Connection Validation (Mandatory)

**What happens:**
- The plugin validates your API credentials
- Tests connection to AutoScout24 API endpoint
- Verifies authentication

**If it fails:**
- Import stops immediately
- Error message is displayed
- Check your credentials in Settings

**If it succeeds:**
- Proceeds to Step 2
- Progress indicator shows "Step 1 Complete"

### Step 2: Total Count

**What happens:**
- Fetches the total number of listings available from AutoScout24
- Displays this count in the statistics card

**If it fails:**
- Import stops
- Error message indicates the issue
- Check API connection and permissions

**If it succeeds:**
- Proceeds to Step 3
- Total count is displayed

### Step 3: ID Collection

**What happens:**
- Collects ALL listing IDs from AutoScout24
- Stores IDs in a queue for processing
- This may take a few minutes for large datasets

**If it fails:**
- Import stops
- Error message is displayed
- May indicate API rate limiting or connection issues

**If it succeeds:**
- All IDs are stored in the queue
- Proceeds to Step 4
- Progress shows "Step 3 Complete"

### Step 4: Sequential Processing

**What happens:**
- Processes listings one by one from the queue
- For each listing:
  - Fetches full listing data from AutoScout24
  - Checks if listing exists locally
  - Creates new post or updates existing post
  - Maps all fields to WordPress post meta
  - Queues images for background processing
- Progress updates after each listing
- Statistics update in real-time

**Processing Details:**
- Each listing is processed individually
- Progress is saved after each listing
- If interrupted, you can resume from the last position
- Images are queued separately and processed via cron

**If errors occur:**
- Error count increments
- Listing is skipped
- Processing continues with next listing
- Error details are logged

### Image Processing

**Background Processing:**
- Images are not downloaded during the main import
- Images are added to a queue
- A cron job processes images every 5 minutes
- This prevents the import from timing out

**Image Queue:**
- Managed automatically by the plugin
- Processed in batches
- Failed images are retried automatically

### Import Completion

**When import completes:**
- Status changes to "Completed"
- Final statistics are displayed
- Summary is logged to Sync History
- If enabled, comparison runs automatically
- Auto-actions execute if configured

---

## Sync Analysis & Comparison

The Sync Analysis feature helps you maintain consistency between your local WordPress listings and AutoScout24.

### Running a Comparison

1. Go to **AS24 Sync** → **Dashboard**
2. Scroll to the **Sync Analysis** section
3. Click **Compare Listings**
4. Wait for the comparison to complete (may take a few moments)
5. Review the results

### Understanding Comparison Results

**Orphaned Local Listings**
- Listings that exist in WordPress but not in AutoScout24
- These may be:
  - Manually created listings
  - Listings removed from AutoScout24
  - Listings with ID mismatches

**Missing Remote Listings**
- Listings that exist in AutoScout24 but not in WordPress
- These may be:
  - New listings added to AutoScout24
  - Listings that failed to import
  - Listings that were deleted locally

**Synced Listings**
- Listings that exist in both places
- These are properly synchronized

### Handling Orphaned Listings

1. After comparison, review the orphaned listings count
2. Select an action from the dropdown:
   - **Move to Trash**: Moves listings to WordPress trash (can be restored)
   - **Change to Draft**: Changes status to draft (hidden from frontend)
   - **Mark as Orphaned**: Adds meta tag (no status change)
   - **Delete Permanently**: Permanently deletes listings (cannot be undone)
3. Click **Handle Orphaned**
4. Confirm the action
5. Review the results in Activity Logs

### Importing Missing Listings

1. After comparison, review the missing listings count
2. Click **Import Missing**
3. The plugin will import all missing listings
4. Progress is tracked in Activity Logs
5. Statistics update in real-time

### Automatic Comparison

If **Run Comparison on Import Complete** is enabled in Settings:
- Comparison runs automatically after each import
- If auto-actions are enabled, they execute automatically
- No manual intervention required

---

## Activity Logs

The Activity Logs section provides comprehensive tracking of all plugin activities.

### Log Types

**Sync History**
- High-level import operations
- Shows start, completion, and summary
- Includes overall statistics and duration

**Listing Logs**
- Individual listing operations
- Shows each listing's action (imported, updated, error)
- Includes detailed change information

### Viewing Logs

**All Logs Tab**
- Combined chronological view
- Shows all activities together
- Most comprehensive view

**Sync History Tab**
- Import operation summaries
- Good for understanding overall import performance
- Shows duration and statistics

**Listing Logs Tab**
- Individual listing details
- Good for troubleshooting specific listings
- Shows what changed in each listing

### Filtering Logs

**By Status**
- Filter by Running, Completed, Stopped, or Failed
- Useful for finding specific import states

**By Action**
- Filter by Imported, Updated, or Errors
- Useful for finding specific operation types

**By Listing ID**
- Search for specific listing IDs
- Useful for tracking individual listings

### Auto-Refresh

- Enable auto-refresh to see logs update in real-time
- Useful during active imports
- Refreshes every few seconds automatically

### Clearing Logs

- Click **Clear Logs** to delete all log entries
- **Warning**: This action cannot be undone
- Use only when you want to start fresh
- Old logs are useful for troubleshooting

### Understanding Log Entries

**Date/Time**: When the action occurred
**Type**: Whether it's a sync operation or listing operation
**Status/Action**: Current status or action taken
**Listing ID**: AutoScout24 identifier
**Post ID**: WordPress post ID (if imported)
**Processed/Imported/Updated/Errors**: Statistics
**Duration**: Time taken (for sync operations)
**Changes**: What changed (for updates)
**Message**: Detailed information

---

## Troubleshooting

### Common Issues and Solutions

#### Issue: Connection Test Fails

**Symptoms:**
- "Test Connection" button shows error
- Import fails at Step 1

**Solutions:**
1. Verify your API credentials are correct
2. Check that your AutoScout24 account has API access enabled
3. Ensure your server can make outbound HTTPS connections
4. Check if your firewall is blocking API requests
5. Verify API endpoint is accessible

#### Issue: Import Stops at Step 2 or 3

**Symptoms:**
- Import starts but fails to get total count or collect IDs
- Error message about API connection

**Solutions:**
1. Check API rate limits (may need to wait)
2. Verify API credentials are still valid
3. Check server error logs for detailed errors
4. Ensure WordPress cron is functioning
5. Try again after a few minutes

#### Issue: Import Processes Slowly

**Symptoms:**
- Import takes a very long time
- Progress updates infrequently

**Solutions:**
1. This is normal for large datasets
2. The plugin processes listings one by one to prevent timeouts
3. Check server performance and resources
4. Ensure WordPress cron is running properly
5. Consider running imports during off-peak hours

#### Issue: Images Not Appearing

**Symptoms:**
- Listings import but images are missing
- Images show as broken

**Solutions:**
1. Images are processed via cron (every 5 minutes)
2. Wait for cron to process the image queue
3. Check if WordPress cron is functioning
4. Verify server has write permissions for uploads directory
5. Check Activity Logs for image processing errors
6. Manually trigger cron if needed

#### Issue: Import Stops Midway

**Symptoms:**
- Import was running but stopped
- Status shows "Stopped" or "Failed"

**Solutions:**
1. Check Activity Logs for error messages
2. Use **Resume Import** to continue from last position
3. Check server error logs
4. Verify API connection is still active
5. Ensure WordPress cron is running

#### Issue: Orphaned Listings Appear

**Symptoms:**
- Comparison shows many orphaned listings
- Listings exist locally but not in AutoScout24

**Solutions:**
1. This is normal if listings were manually created
2. This is normal if listings were removed from AutoScout24
3. Use the comparison tool to review orphaned listings
4. Choose appropriate action (trash, draft, etc.)
5. Enable auto-delete orphaned in Settings if desired

#### Issue: Missing Listings

**Symptoms:**
- Comparison shows listings in AutoScout24 but not locally
- Some listings never imported

**Solutions:**
1. These may be new listings added to AutoScout24
2. These may be listings that failed to import
3. Use **Import Missing** button to import them
4. Check Activity Logs for error messages
5. Enable auto-import missing in Settings if desired

#### Issue: Auto-Import Not Working

**Symptoms:**
- Auto-import is enabled but not running
- No automatic imports occur

**Solutions:**
1. Verify WordPress cron is functioning
2. Some hosting providers disable WordPress cron
3. Set up a server-level cron job to trigger WordPress cron
4. Check cron schedule in Settings
5. Verify API credentials are saved correctly

#### Issue: Statistics Not Updating

**Symptoms:**
- Dashboard shows old statistics
- Progress not updating during import

**Solutions:**
1. Click **Refresh Status** button
2. Check browser console for JavaScript errors
3. Ensure AJAX is working in WordPress
4. Try refreshing the page
5. Check if import is actually running

### Getting Help

If you encounter issues not covered here:

1. **Check Activity Logs**: Most issues are logged with detailed messages
2. **Review Error Messages**: Error messages often contain helpful information
3. **Check Server Logs**: WordPress and PHP error logs may have additional details
4. **Verify Requirements**: Ensure WordPress, PHP, and theme requirements are met
5. **Test API Connection**: Always verify API credentials are working

---

## FAQ

### General Questions

**Q: What is AS24 Sync?**
A: AS24 Sync is a WordPress plugin that synchronizes vehicle listings from AutoScout24 to your WordPress website.

**Q: Do I need AutoScout24 API access?**
A: Yes, you need valid AutoScout24 API credentials (username and password) to use this plugin.

**Q: Is this plugin free?**
A: The plugin is licensed under GPL v2 or later. Check the plugin repository for current licensing information.

**Q: What WordPress version do I need?**
A: WordPress 5.8 or higher is required.

**Q: What PHP version do I need?**
A: PHP 7.4 or higher is required.

### Import Questions

**Q: How long does an import take?**
A: Import time depends on the number of listings. The plugin processes listings one by one to prevent timeouts. Large imports (1000+ listings) may take several hours.

**Q: Can I stop an import and resume later?**
A: Yes! Click **Stop Import** to pause, then use **Resume Import** to continue from where you left off.

**Q: What happens if the import is interrupted?**
A: Progress is saved after each listing. You can resume the import using the **Resume Import** button.

**Q: Will the import timeout?**
A: No. The plugin processes listings one by one and uses background processing to prevent timeouts.

**Q: How often should I run imports?**
A: This depends on how often your AutoScout24 listings change. Daily is recommended for most users.

### Image Questions

**Q: When are images downloaded?**
A: Images are queued during import and processed via cron every 5 minutes. This prevents import timeouts.

**Q: Why don't I see images immediately after import?**
A: Images are processed in the background. Wait a few minutes for the cron job to process them, or check Activity Logs for image processing status.

**Q: What if images fail to download?**
A: Failed images are automatically retried. Check Activity Logs for specific error messages.

### Settings Questions

**Q: Are my API credentials secure?**
A: Yes, credentials are stored in the WordPress database using WordPress's built-in security functions.

**Q: Can I change import frequency?**
A: Yes, go to Settings → Import Settings → Import Frequency.

**Q: What's the difference between auto-import and manual import?**
A: Auto-import runs automatically on a schedule. Manual import requires you to click "Start Import" in the Dashboard.

### Comparison Questions

**Q: What are orphaned listings?**
A: Orphaned listings exist in WordPress but not in AutoScout24. They may be manually created or removed from AutoScout24.

**Q: What are missing listings?**
A: Missing listings exist in AutoScout24 but not in WordPress. They may be new listings or failed imports.

**Q: Should I delete orphaned listings?**
A: This depends on your needs. If listings were manually created, you may want to keep them. If they were removed from AutoScout24, you may want to trash or delete them.

**Q: How often should I run comparisons?**
A: This depends on your workflow. You can enable automatic comparison after each import, or run it manually when needed.

### Technical Questions

**Q: Does this plugin work with any WordPress theme?**
A: The plugin is designed to work with the Motors theme, which provides the "listings" post type. Other themes may require customization.

**Q: Can I customize field mappings?**
A: Field mappings are handled by the plugin's field mapper class. Advanced customization may require code modifications.

**Q: Does the plugin use WordPress cron?**
A: Yes, the plugin uses WordPress cron for background image processing and scheduled imports.

**Q: What database tables does the plugin create?**
A: The plugin creates two tables:
- `wp_as24_sync_history`: Tracks import operations
- `wp_as24_listing_logs`: Tracks individual listing changes

**Q: Can I delete the plugin's database tables?**
A: The tables are automatically managed by the plugin. Deleting them manually may cause issues. Uninstalling the plugin should handle cleanup.

### Support Questions

**Q: Where can I get support?**
A: Check the plugin's GitHub repository or contact the author.

**Q: How do I report bugs?**
A: Report bugs through the plugin's GitHub repository issue tracker.

**Q: Can I contribute to the plugin?**
A: Check the plugin's GitHub repository for contribution guidelines.

---

## Additional Resources

### Plugin Information

- **Plugin Name**: AS24 Sync
- **Version**: 2.0.0
- **Author**: H M Shahadul Islam
- **Author Email**: shahadul.islam1@gmail.com
- **GitHub**: https://github.com/shahadul878/as24-sync
- **License**: GPL v2 or later

### WordPress Admin Locations

- **Dashboard**: `AS24 Sync` → `Dashboard` (or `AS24 Sync` main menu)
- **Settings**: `AS24 Sync` → `Settings`
- **Quick Access**: Click "Dashboard" or "Settings" links on the Plugins page

### Best Practices

1. **Always test API connection** before starting an import
2. **Review Activity Logs** regularly to monitor plugin health
3. **Run comparisons** periodically to maintain data consistency
4. **Enable auto-import** only if you have reliable WordPress cron
5. **Backup your database** before major imports or bulk operations
6. **Monitor server resources** during large imports
7. **Keep WordPress and PHP updated** for security and performance

---

## Changelog

### Version 2.0.0

- Initial release with step-by-step import process
- API connection validation
- Sequential listing processing
- Background image processing
- Sync comparison feature
- Comprehensive activity logging
- Auto-import scheduling
- Orphaned and missing listing handling

---

## License

This plugin is licensed under the GPL v2 or later.

---

**Last Updated**: 2024

**Documentation Version**: 1.0

For the latest information, please visit the plugin's GitHub repository.

