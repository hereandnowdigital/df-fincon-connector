# DF Fincon Connector - Developer Documentation

## Project Overview

A WordPress plugin for bidirectional synchronization between WooCommerce and Fincon accounting software. Handles product and customer data synchronization with support for batch imports, scheduled syncs, and manual imports.

## Architecture

### Core Components

1. **Plugin Bootstrap** (`df-fincon-connector.php`)
   - Minimal bootstrap file defining constants
   - Loads autoloader and initializes Plugin singleton

2. **Autoloader** (`includes/_autoloader.php`)
   - Custom PSR-4-like autoloader for `DF_FINCON` namespace
   - Classes must be in `includes/` directory

3. **Main Classes** (all singletons with `::create()` factory)
   - `Plugin` - Main plugin initialization and dependency loading
   - `Admin` - WordPress admin interface and settings
   - `FinconApi` - API client with ConnectID session management
   - `FinconService` - Business logic for imports
   - `ProductSync` - Product synchronization logic
   - `CustomerSync` - Customer synchronization logic
   - `Logger` - WC_Logger wrapper with source context
   - `Cron` - Scheduled sync functionality
   - `Woo` - WooCommerce integration (roles, checkout fields)

### Key Architectural Patterns

#### Singleton Pattern
All main classes use singleton pattern with static `::create()` factory method:
```php
// Correct
$logger = Logger::create();

// Incorrect (will throw exception for Plugin class)
$logger = new Logger();
```

#### ConnectID Session Management
- Fincon API uses ConnectID tokens with 5-minute TTL (300 seconds)
- Stored as WordPress transient: `df_fincon_connector_connect_id`
- Automatic refresh on "Invalid connect ID" errors
- **Never call `FinconApi::login()` directly** - use `get_connect_id()`

#### State Management
Two separate state systems:
- **Batch State**: For cron/scheduled imports (`*_batch_state` options)
- **Manual Progress**: For UI-driven imports (`*_manual_import_progress` options)

Manual imports support resumption with `$resume = true` parameter.

## Data Models

### Product Synchronization

#### Stock Locations
Dynamic, admin-configurable locations via LocationManager:
- Default locations: `00` (Johannesburg), `01` (Cape Town), `03` (Durban)
- Configurable via Fincon Connector → Stock Locations admin page
- Each location has: code, name, short name, rep code, active status, default flag
- Stock quantities stored as meta: `_stock_{code}` (e.g., `_stock_00`)
- Total stock = sum of all active locations
- API calls use active location codes via `LocationManager::get_location_codes_string()`
- Default location for orders via `LocationManager::get_default_location()`

#### Location Selector for Orders
Customers can select their preferred location during checkout:
- **Cart Page**: Dropdown selector above cart table with AJAX updates
- **Checkout Page**: Required field in billing section, persists from cart selection
- **Session Persistence**: Selected location stored in WooCommerce session
- **Order Meta Storage**: Saved to order as:
  - `_fincon_location_code` - Selected location code
  - `_fincon_location_name` - Selected location name
  - `_fincon_rep_code` - Associated rep code
- **Display Locations**:
  - Customer My Account order view
  - Admin order edit screen (shows selected or default)
- **OrderSync Integration**: Uses selected location for Fincon API `LocNo` and `RepCode` fields
- **Fallback**: Uses default location if no selection made

**Key Methods**:
- `Woo::add_location_selector_to_cart()` - Cart page dropdown
- `Woo::add_location_selector_field()` - Checkout page field
- `Woo::save_location_to_order()` - Save to order meta
- `Woo::display_location_in_order_view()` - My Account display
- `Woo_Admin::display_customer_type_in_order()` - Admin display
- `OrderSync::get_order_locno()` - Get location for API calls

**AJAX Endpoint**: `wp_ajax_df_fincon_update_cart_location` - Updates cart location via AJAX

#### Price Structure
Six selling prices from Fincon:
- `SellingPrice1` → WooCommerce regular price
- `SellingPrice2-6` → Meta fields `_selling_price_2` through `_selling_price_6`

Promotional pricing uses separate fields:
- `ProPrice`, `ProPriceType`, `ProFromDate`, `ProToDate`, `ProQuantity`, `ProMaxQuantity`

#### Change Detection
- Compare `ChangeDate` + `ChangeTime` with `_fincon_changed_datetime` meta
- When `import_update_only_changed` enabled, skip unchanged records
- **Exception**: Stock updates always occur regardless of change detection

### Customer Synchronization

#### Role Assignment
Two custom roles based on `PriceStruc` field:
- `df_customer_retail` - `PriceStruc = 1`
- `df_customer_dealer` - `PriceStruc > 1`

Users keep base 'customer' role plus custom role.

#### Email Extraction
Priority order:
1. `Email` field
2. `Email1` field (fallback)

Multiple emails separated by semicolons - only first email used. Customers without emails fail creation.

#### Meta Fields
- `_fincon_accno` - Fincon account number
- `_fincon_price_struc` - Price structure code
- `_fincon_active` - Active status (Y/N)
- `_fincon_on_hold` - On hold status (Y/N)
- `_fincon_customer_changed_timestamp` - Last change timestamp
- `delivery_instructions` - Delivery instructions (shown at checkout)

## API Integration

### Fincon API Client
- Base URL: `http://{host}:{port}/datasnap/rest/FinconAPI/`
- Functions wrapped in quotes: `%22Login%22`, `%22GetStock%22`, etc.
- ConnectID appended as first parameter for authenticated calls

### Error Handling
- All API methods return `WP_Error` on failure
- Automatic retry on ConnectID expiration
- Detailed logging with request/response details
- Passwords redacted in logs for Login calls

## Admin Interface

### Settings Pages
1. **API Settings** - Connection configuration
2. **Product Import Settings** - Batch size, change detection, scheduling
3. **Customer Import Settings** - Batch size, change detection
4. **Manual Import Pages** - Product and customer import UI
5. **Cron Log** - View scheduled sync history

### AJAX Endpoints
- `df_fincon_test_connection` - Test API connection
- `df_fincon_manual_import_products` - Manual product import
- `df_fincon_manual_import_customers` - Manual customer import

All endpoints require `manage_woocommerce` capability and proper nonces.

## Cron Scheduling

### Custom Schedules
- `every_5_minutes` - 300 seconds (for testing)
- `hourly`, `daily`, `weekly` - Standard WordPress schedules

### Schedule Calculation
First run time calculated based on:
- Frequency (every_5_minutes, hourly, daily, weekly)
- Time (for daily/weekly)
- Day (for weekly)

### Logging
Cron runs logged to `df_fincon_connector_cron_log` option (max 50 entries). Logs include:
- Start/end times
- Duration
- Status (success/failed)
- Summary message

## Development Guidelines

### Adding New Features

1. **New API Endpoints**
   - Add method to `FinconApi` class
   - Implement in `FinconService` for business logic
   - Add AJAX handler in `Admin` if needed for UI

2. **New Sync Logic**
   - Extend `ProductSync` or `CustomerSync`
   - Follow existing patterns for state management
   - Implement change detection if applicable

3. **Admin UI Changes**
   - Add settings fields in `Admin::register_settings_*` methods
   - Create template in `templates/admin/`
   - Add JavaScript in `assets/js/admin.js`

### Testing
1. **API Connection Test** - Use admin UI to verify credentials
2. **Manual Imports** - Test small batches via admin UI
3. **Cron Testing** - Use `every_5_minutes` schedule for quick testing

### Debugging
- Check ConnectID transient: `get_transient('df_fincon_connector_connect_id')`
- View cron logs: Admin → Fincon Connector → Cron Log
- Enable debug logging: `Logger::debug()` with backtrace option
- Check batch state options in database

## File Structure

```
df-fincon-connector/
├── df-fincon-connector.php          # Main plugin file
├── includes/                        # Core PHP classes
│   ├── _autoloader.php             # Custom autoloader
│   ├── Plugin.php                  # Main plugin class
│   ├── Admin.php                   # Admin interface
│   ├── FinconApi.php               # API client
│   ├── FinconService.php           # Business logic
│   ├── ProductSync.php             # Product synchronization
│   ├── CustomerSync.php            # Customer synchronization
│   ├── Logger.php                  # Logging wrapper
│   ├── Cron.php                    # Cron scheduling
│   ├── Woo.php                     # WooCommerce integration
│   ├── Woo_Admin.php               # WooCommerce admin functionality
│   ├── OrderSync.php               # Order synchronization
│   ├── LocationManager.php         # Stock location management
│   ├── Client.php                  # HTTP client
│   └── Shortcodes.php              # Shortcodes (if any)
├── templates/admin/                # Admin templates
│   ├── settings.php
│   ├── product-import.php
│   ├── customer-import.php
│   ├── cron-log.php
│   ├── stock-locations.php         # Stock locations management
│   └── stock-location-edit.php     # Edit stock location
├── assets/js/admin.js              # Admin JavaScript
└── _documentation/                 # API documentation
```

## Dependencies

- **PHP**: 8.1+
- **WordPress**: 6.8+
- **WooCommerce**: 8.0+
- **Extensions**: None beyond WordPress/WooCommerce

## Security

- All operations require `manage_woocommerce` capability
- Nonce verification on all AJAX endpoints
- Input sanitization via WordPress functions
- Passwords redacted in logs
- ConnectID with limited TTL (5 minutes)

## Performance Considerations

- Default batch size: 100 records
- Change detection reduces unnecessary updates
- ConnectID caching reduces API login calls
- State stored in options (not custom tables)