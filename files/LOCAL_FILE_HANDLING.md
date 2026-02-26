# VIP Filesystem Local File Handling

This document explains how to use the local file handling feature in the VIP Filesystem Stream Wrapper, which allows certain files to be stored locally in the temporary directory instead of being uploaded to the VIP Files Service.

## Overview

By default, all files accessed through the VIP Filesystem (`vip://` protocol) are uploaded to and served from the VIP Files Service. However, some use cases require files to be handled locally:

- Temporary cache files that don't need to persist across requests
- Large files that are processed and discarded
- Session files or other ephemeral data
- Files that should not be uploaded to remote storage for security or compliance reasons

## Global Helper Functions

Three global helper functions provide a clean API for managing local file handling:

### `wpvip_fs_local_file_add( $file_path )`

Add a file or pattern to be handled locally.

**Parameters:**
- `$file_path` (string) - Path to the file or a wildcard pattern

**Returns:**
- (bool) True if the file was added successfully, false otherwise

**Example:**
```php
// Add a specific file
wpvip_fs_local_file_add( 'vip://wp-content/uploads/temp-data.json' );

// Add all files in a directory with a pattern
wpvip_fs_local_file_add( 'vip://wp-content/uploads/cache/*.json' );

// Add all files in a directory
wpvip_fs_local_file_add( 'vip://wp-content/uploads/tmp/*' );
```

### `wpvip_fs_local_file_remove( $file_path )`

Remove a file or pattern from local handling (files will be handled by VIP Files Service instead).

**Parameters:**
- `$file_path` (string) - Path to the file or pattern to remove

**Returns:**
- (bool) True if the file was removed successfully, false otherwise

**Example:**
```php
// Remove a specific file from local handling
wpvip_fs_local_file_remove( 'vip://wp-content/uploads/temp-data.json' );

// Remove a pattern
wpvip_fs_local_file_remove( 'vip://wp-content/uploads/cache/*.json' );
```

### `wpvip_fs_local_file_list()`

Get the list of all files and patterns being handled locally.

**Returns:**
- (array) Associative array of file paths and patterns configured for local handling

**Example:**
```php
$local_files = wpvip_fs_local_file_list();
print_r( $local_files );
// Array (
//     [vip://wp-content/uploads/temp-data.json] => 1
//     [vip://wp-content/uploads/cache/*.json] => 1
// )
```

## Wildcard Pattern Syntax

The local file handling supports wildcard patterns using fnmatch syntax:

- `*` - Matches any number of characters except directory separators
- `?` - Matches a single character
- `[abc]` - Matches any character in the set

**Pattern Examples:**

```php
// All JSON files in cache directory (not subdirectories)
wpvip_fs_local_file_add( 'vip://wp-content/uploads/cache/*.json' );

// All files in tmp directory (not subdirectories)
wpvip_fs_local_file_add( 'vip://wp-content/uploads/tmp/*' );

// Files starting with "temp-" in uploads
wpvip_fs_local_file_add( 'vip://wp-content/uploads/temp-*' );

// Specific file types
wpvip_fs_local_file_add( 'vip://wp-content/uploads/*.log' );
```

## When to Call These Functions

The helper functions can be called at any point after the VIP Filesystem MU plugin loads (which happens very early in the WordPress bootstrap process).

**Important**: These functions require the VIP Filesystem Stream Wrapper to be enabled via the `VIP_FILESYSTEM_USE_STREAM_WRAPPER` constant. If called when the Stream Wrapper is not loaded, they will:
- Trigger a `_doing_it_wrong()` notice
- Return `false` (for add/remove) or an empty array (for list)

You can safely call these functions in:
- Theme's `functions.php`
- Plugin initialization hooks
- Other MU-plugin files
- WP-CLI commands

**Example in theme's functions.php:**

```php
<?php
// In your theme's functions.php

// Configure local file handling for temporary cache
add_action( 'init', function() {
    // These files will be stored locally instead of uploaded
    wpvip_fs_local_file_add( 'vip://wp-content/uploads/cache/*.json' );
    wpvip_fs_local_file_add( 'vip://wp-content/uploads/tmp/*' );
} );
```

## Use Cases

### 1. Temporary Cache Files

```php
// In your plugin
wpvip_fs_local_file_add( 'vip://wp-content/uploads/my-plugin-cache/*.json' );

// Now when you write cache files, they'll be stored locally
$cache_file = 'vip://wp-content/uploads/my-plugin-cache/data.json';
file_put_contents( $cache_file, json_encode( $data ) );
// File is written to temporary directory, not uploaded to VIP Files Service
```

### 2. Large Processing Files

```php
// For a CSV import plugin
wpvip_fs_local_file_add( 'vip://wp-content/uploads/import-*.csv' );

// Process large CSV locally without uploading
$import_file = 'vip://wp-content/uploads/import-12345.csv';
// ... process file ...
// File is automatically cleaned up when temp directory is cleared
```

### 3. Session Files

```php
// If storing session data in files
wpvip_fs_local_file_add( 'vip://wp-content/uploads/sessions/*' );
```

## Implementation Details

### Where Local Files Are Stored

Local files are stored in WordPress's temporary directory (typically `/tmp`) with the path structure preserved:

```
Original: vip://wp-content/uploads/cache/data.json
Local:    /tmp/wp-content/uploads/cache/data.json
```

### Performance Considerations

- **Exact file paths** are checked using O(1) hash lookup
- **Wildcard patterns** are checked using fnmatch (linear time)
- For best performance, use exact paths when possible

### Cleanup

Files stored in the temporary directory are subject to the system's temporary file cleanup policies. They will not persist across server restarts or deployments.

## Migration from Direct Static Method Calls

If you were previously using the Stream Wrapper's static methods directly:

```php
// Old way (still works)
\Automattic\VIP\Files\VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $path );

// New way (recommended)
wpvip_fs_local_file_add( $path );
```

The global functions provide the same functionality with a cleaner API and automatic buffering support.

## Security Considerations

> [!IMPORTANT] Security review
>
> Local file handling has several security implications:
>
> 1. **No Persistence**: Files stored locally are ephemeral and will be lost on server restart or deployment
> 2. **No Replication**: Local files exist only on a single application server and are not replicated
> 3. **Access Control**: Local files bypass VIP Files Service access controls
> 4. **Temporary Directory**: Files are stored in the system temporary directory which may have different permissions
>
> **Recommendations:**
> - Only use local handling for truly temporary data
> - Never use it for user-uploaded content that needs to persist
> - Be cautious with sensitive data in temporary files
> - Ensure proper cleanup of sensitive data when no longer needed
## Troubleshooting

### "_doing_it_wrong" Notice

If you see a `_doing_it_wrong()` notice about the VIP Filesystem Stream Wrapper not being loaded:

```
wpvip_fs_local_file_add was called incorrectly. VIP Filesystem Stream Wrapper is not loaded. 
Please ensure VIP_FILESYSTEM_USE_STREAM_WRAPPER is defined and set to true.
```

**Solution**: The Stream Wrapper feature is not enabled. Check that:
1. The constant `VIP_FILESYSTEM_USE_STREAM_WRAPPER` is defined and set to `true`
2. Your environment supports the Stream Wrapper feature
3. You're not calling these functions in a context where the MU plugin hasn't loaded yet

### Files Not Being Handled Locally

1. Verify the pattern matches your file path:
   ```php
   $path = 'vip://wp-content/uploads/cache/data.json';
   $is_local = \Automattic\VIP\Files\VIP_Filesystem_Local_Stream_Wrapper::is_local_file( $path );
   var_dump( $is_local ); // Should be true
   ```

2. Check that the file was added correctly:
   ```php
   $local_files = wpvip_fs_local_file_list();
   print_r( $local_files );
   ```

### Permission Issues

If you encounter permission errors, ensure the temporary directory is writable by the web server user.

## API Reference

For low-level access, the Stream Wrapper class provides static methods:

```php
// Add a local file
\Automattic\VIP\Files\VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $file_path );

// Remove a local file
\Automattic\VIP\Files\VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $file_path );

// Get all local files
\Automattic\VIP\Files\VIP_Filesystem_Local_Stream_Wrapper::get_local_files();

// Check if a file is local
\Automattic\VIP\Files\VIP_Filesystem_Local_Stream_Wrapper::is_local_file( $file_path );
```

However, the global helper functions (`wpvip_fs_local_file_*`) are recommended for most use cases.
