# Flag Retention Module

This Drupal module provides retention policies and clearing functionality for the Flag module.

## Features

### Retention Management
- **Global retention settings**: Set default retention periods for all flags
- **Per-flag retention settings**: Override global settings for specific flag types
- **Automatic cleanup**: Cron-based cleanup of old flags based on retention policies

### Flag Clearing
- **User flag clearing**: Allow users to clear their own flags
- **Admin flag clearing**: Administrators can clear all flags of a specific type
- **Bulk clearing**: Administrative interface for bulk flag operations
- **Age-based clearing**: Clear flags older than a specified number of days

### Permissions
- `administer flag retention`: Configure retention settings and policies
- `clear own flags`: Allow users to clear their own flags
- `clear all flags`: Allow clearing of all flags (admin permission)

## Installation

1. Place the module in your `modules/custom` directory
2. Enable the module: `drush en flag_retention`
3. Configure permissions at `/admin/people/permissions`
4. Configure global settings at `/admin/config/system/flag-retention`
5. Set per-flag retention settings at `/admin/structure/flags/retention`

## Configuration

### Global Settings
- **Default retention period**: Number of days to keep flags (0 = keep forever)
- **Enable user clearing**: Allow users to clear their own flags
- **Log clearing activity**: Log flag clearing for audit purposes
- **Cron batch size**: Number of flags to process per cron run

### Per-Flag Settings
- **Retention days**: Override global setting for specific flag types
- **Auto-clear**: Enable automatic cleanup via cron for this flag type

## Usage

### For Users
- **User profiles**: Visit your user profile and click "Clear flags" (if enabled)
- **Views integration**: Add the "Flag Retention Clear Link" field to user listing views
- **Views area**: Add the "Flag Retention Clear Area" to any view for a clear button
- **Block**: Place the "Clear My Flags" block anywhere on your site
- **Direct link**: Visit `/user/{your-id}/flag-clear` to access the clearing form

### For Administrators
- Manage retention settings at `/admin/structure/flags/retention`
- Perform bulk operations at `/admin/structure/flags/bulk-clear`
- Clear all flags of a specific type from the flag management page

### Automated Cleanup
- Configure retention settings for flag types
- Enable "Auto-clear" for automatic cleanup
- Old flags will be removed during cron runs based on retention periods

## Database Schema

The module creates a `flag_retention_settings` table to store per-flag retention policies:
- `flag_id`: The flag identifier
- `retention_days`: Number of days to retain flags (0 = never delete)
- `auto_clear`: Whether to automatically clear old flags via cron
- Timestamps for created/changed tracking

## Logging

When logging is enabled, the module logs:
- Automatic cleanup activities (via cron)
- Flag clearing operations
- Errors during cleanup operations

Check the Drupal logs at `/admin/reports/dblog` for flag retention activities.

## Requirements

- Drupal 10.x
- Flag module
- Core modules: user, system, datetime

## Views Integration

The module provides several ways to integrate with Drupal Views:

### Views Field: Flag Retention Clear Link
- **Purpose**: Add a "Clear Flags" link to user listing views
- **Configuration**: Choose between user clear or admin clear modes
- **Features**: Shows flag count, permission-aware display
- **Usage**: Add to any view that displays user entities

### Views Area: Flag Retention Clear Area
- **Purpose**: Add a "Clear My Flags" button to any view
- **Configuration**: Customizable button text and styling
- **Features**: Shows current user's flag count
- **Usage**: Add to header, footer, or empty area of any view

### Block: Clear My Flags
- **Purpose**: Standalone block for clearing flags
- **Configuration**: Show/hide counts and summaries
- **Features**: Complete flag breakdown by type
- **Usage**: Place in sidebars, content areas, or any block region

## Support

This module integrates with the Flag module's existing infrastructure and uses proper Drupal APIs for entity management, ensuring compatibility with other modules and proper event triggering.