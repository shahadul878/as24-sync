# AS24 Sync 2.0

High-performance AutoScout24 synchronization plugin for WordPress with step-by-step import process, mandatory API validation, and optimized image processing.

## Features

### Step-by-Step Import Process
1. **API Connection Validation** (Mandatory) - Validates credentials and endpoint before any import
2. **Total Count** - Fetches and displays total listings available
3. **ID Collection** - Collects ALL listing IDs upfront before processing
4. **Sequential Processing** - Processes listings one by one with progress saving
5. **Image Queue** - Images queued for background cron processing

### Key Benefits
- **Smooth Processing** - No timeouts, handles large datasets
- **Error Recovery** - Resume from last position if interrupted
- **Progress Tracking** - Real-time progress updates via AJAX
- **Image Optimization** - Images processed via cron, non-blocking
- **WP Code Standards** - Fully compliant with WordPress coding standards

## Installation

1. Upload the `as24-sync` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Go to **AS24 Sync → Settings** to configure
4. Enter your AutoScout24 API credentials
5. Click "Test Connection" to verify
6. Go to **AS24 Sync → Dashboard** to start import

## Usage

### Step 1: Configure API Credentials
1. Navigate to **AS24 Sync → Settings**
2. Enter your AutoScout24 username and password
3. Click "Test Connection" to verify
4. Save settings

### Step 2: Start Import
1. Navigate to **AS24 Sync → Dashboard**
2. Click "Start Import"
3. The plugin will:
   - Validate API connection
   - Get total listings count
   - Collect all listing IDs
   - Process listings one by one
   - Queue images for cron processing

### Progress Tracking
- Real-time progress updates every 2 seconds
- Step-by-step status indicators
- Statistics cards showing processed/imported/updated/errors
- Progress bar with percentage

## Architecture

### Core Classes
- `AS24_API_Validator` - Step 1: API validation
- `AS24_Queue_Manager` - Steps 2-3: Total count and ID collection
- `AS24_Listing_Processor` - Step 4: Process listings one by one
- `AS24_Image_Queue` - Image queue management
- `AS24_Image_Processor` - Cron + async image processing
- `AS24_Import_Orchestrator` - Main coordinator
- `AS24_Progress_Tracker` - Progress tracking

### Processing Flow
1. API validation (blocks if fails)
2. Get total count
3. Collect all IDs (stored in transient)
4. Process one listing at a time (from queue)
5. Queue images (processed via cron)

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- AutoScout24 API credentials
- Motors theme (for listings post type)

## License

GPL v2 or later

## Author

H M Shahadul Islam  
Email: shahadul.islam1@gmail.com  
GitHub: https://github.com/shahadul878

