# Fincon Connector by Digital Fold – User Guide

## Overview
The Fincon Connector plugin integrates WooCommerce with Fincon accounting software, enabling bidirectional synchronization of products, customers, and orders between your e-commerce store and accounting system.

**Key Features:**
- **Product Synchronization**: Import products from Fincon to WooCommerce with automatic stock updates
- **Customer Management**: Sync Fincon customers as WordPress users with role-based pricing
- **Order Processing**: Send WooCommerce orders to Fincon as sales orders with invoice generation
- **Stock Location Management**: Manage multiple warehouse locations with individual stock levels
- **Scheduled Imports**: Automate sync processes with configurable cron schedules
- **Manual Controls**: Run imports on-demand via the WordPress admin interface

## Requirements
### System Requirements
- WordPress 6.8 or higher
- WooCommerce 8.0 or higher
- PHP 8.1 or higher
- MySQL 5.7 or higher

### Fincon Requirements
- Fincon accounting software with REST API enabled
- API access credentials (username, password, Data ID)
- Network access to Fincon server (typically port 4090)

### User Permissions
- WordPress administrator access
- `manage_woocommerce` capability required for plugin operations

## Installation
### Step 1: Upload Plugin
1. Download the plugin ZIP file
2. Navigate to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### Step 2: Verify Installation
1. Check that **Fincon** appears in the WordPress admin menu
2. Verify WooCommerce is active and functioning
3. Ensure no PHP errors appear in debug logs

### Step 3: Initial Configuration
1. Go to **Fincon → Settings**
2. Configure API connection settings (see Configuration section)
3. Test the connection before proceeding

## Configuration
### API Connection Settings
Navigate to **Fincon → Settings → API Settings** tab:

| Setting | Description | Example Value |
|---------|-------------|---------------|
| Server URL | Fincon server address (without port) | `http://fincon-server.local` |
| Server Port | Fincon API port (typically 4090) | `4090` |
| Fincon Username | API username | `api_user` |
| Fincon Password | API password | `********` |
| Data ID | Fincon database identifier | `Test` |

**Connection Test:** Use the **Test Connection** button to verify API connectivity before saving settings.

### Product Import Settings
Navigate to **Fincon → Settings → Product Import Settings** tab:

| Setting | Description | Default |
|---------|-------------|---------|
| Batch Size | Number of products to process per batch | 100 |
| Update only changed products | Skip products not modified since last import | Enabled |
| Web Only | Import only products marked for web in Fincon | Enabled |
| Enable Scheduled Sync | Automatically sync products on schedule | Disabled |
| Sync Frequency | How often to run automatic sync | Daily |
| Sync Time | Time to run scheduled sync | 23:00 |
| Sync Day | Day for weekly schedule (weekly only) | Monday |

### Customer Import Settings
Navigate to **Fincon → Settings → Customer Import Settings** tab:

| Setting | Description | Default |
|---------|-------------|---------|
| Batch Size | Number of customers to process per batch | 100 |
| Import only customers updated on Fincon | Skip unchanged customers | Enabled |
| WebList only | Import only customers marked for web in Fincon | Enabled |
| Enable Scheduled Customer Sync | Automatically sync customers on schedule | Disabled |
| Customer Sync Frequency | How often to run automatic sync | Daily |
| Customer Sync Time | Time to run scheduled sync | 23:00 |
| Customer Sync Day | Day for weekly schedule (weekly only) | Monday |

### Order Sync Settings
Navigate to **Fincon → Settings → Order Sync Settings** tab:

| Setting | Description | Default |
|---------|-------------|---------|
| Enable Fincon Order Sync | Send WooCommerce orders to Fincon | Disabled |
| Fincon debt account for B2C/Guests | Default account for guest orders | (Empty) |

## Fincon Setup
### Prerequisites in Fincon
1. **API Access**: Ensure REST API is enabled in Fincon
2. **User Account**: Create a dedicated API user with appropriate permissions
3. **Product Setup**: Products must have unique ItemNo values (used as SKU)
4. **Customer Setup**: Customers should have valid email addresses in Email or Email1 fields
5. **Price Structures**: Configure price lists (1-6) for retail/dealer pricing

### Product Configuration in Fincon
- **ItemNo**: Must be unique (maps to WooCommerce SKU)
- **Description**: Product name in WooCommerce
- **SellingPrice1**: Regular price (price list 1 for retail)
- **SellingPrice2-6**: Dealer price lists (optional)
- **StockLoc**: Stock location data for multi-warehouse support
- **WebOnly**: Flag to control which products appear online

### Customer Configuration in Fincon
- **AccNo**: Unique account number (maps to user meta)
- **Email/Email1**: Primary contact email (semicolon separated for multiple)
- **PriceStruc**: Price structure (1=retail, 2-6=dealer)
- **Active**: Account status (Y/N)
- **OnHold**: Credit hold status (Y/N)

## WooCommerce Behaviour
### Product Import Process
1. **SKU Matching**: Products matched by Fincon ItemNo → WooCommerce SKU
2. **Creation**: New products created as draft WooCommerce simple products
3. **Update**: Existing products updated with latest Fincon data
4. **Stock Management**: Stock levels updated from all configured locations
5. **Price Application**: SellingPrice1 becomes regular price; SellingPrice2-6 stored as meta

### Customer Import Process
1. **Account Matching**: Customers matched by AccNo or email
2. **User Creation**: New WordPress users created with 'customer' role
3. **Role Assignment**: 
   - PriceStruc = 1 → `df_customer_retail` role
   - PriceStruc = 2-6 → `df_customer_dealer` role
4. **Meta Storage**: Fincon data stored as user meta fields
5. **Address Sync**: Billing/shipping addresses imported from Fincon

### Price List System
The plugin supports six price lists from Fincon:

| Price List | Customer Type | WooCommerce Price |
|------------|---------------|-------------------|
| 1 | Retail (B2C) | Regular price |
| 2-6 | Dealer (B2B) | Meta fields (`_selling_price_2` to `_selling_price_6`) |

**Promotional Pricing:** Supports promotional prices with date ranges via ProPrice, ProPriceType, ProFromDate, ProToDate fields.

## Order Flow
### Order Synchronization Process
1. **Trigger**: Order payment completion or status change to processing
2. **Validation**: Order validated for sync eligibility
3. **Data Preparation**: Order data formatted for Fincon API
4. **API Call**: Sales order created in Fincon via CreateSalesOrder
5. **Response Handling**: Fincon order/receipt numbers stored as order meta
6. **Invoice Tracking**: Invoice numbers tracked for PDF generation

### Payment Method Mapping
WooCommerce payment methods map to Fincon PayType:

| WooCommerce Method | Fincon PayType | Description |
|-------------------|----------------|-------------|
| Stripe, Yoco | C | Card payment |
| PayPal, Bank Transfer, COD | T | Transfer/electronic |
| Default | C | Card (fallback) |

### Location Selection
Orders include stock location information:
- Default location from LocationManager settings
- Custom location selection via checkout (if configured)
- Rep code mapping for sales tracking

## Admin Settings Explained
### Main Menu Structure
- **Fincon**: Parent menu item with settings access
- **Settings**: API and configuration management
- **Manual Product Import**: On-demand product sync interface
- **Manual Customer Import**: On-demand customer sync interface
- **Cron Log**: View scheduled task execution history
- **Stock Locations**: Manage warehouse configurations

### Manual Import Pages
**Product Import:**
- Start/stop manual import process
- View progress and statistics
- Resume interrupted imports

**Customer Import:**
- Batch import customer accounts
- View import results and errors
- Role assignment summary

### Stock Locations Management
Configure multiple warehouse locations:
- **Location Code**: Unique identifier (e.g., 00, 01, 03)
- **Short Name**: Display name (e.g., JHB, CPT, DBN)
- **Full Name**: Complete location description
- **Rep Code**: Sales representative code
- **Active Status**: Enable/disable location
- **Default Flag**: Set as default location

### Invoice Management
- **Invoice Status**: Track pending/available/downloaded invoices
- **PDF Download**: Access Fincon-generated invoice PDFs
- **Manual Fetch**: Force PDF retrieval from Fincon
- **Multiple Invoices**: Handle orders with multiple invoice documents

## Typical Workflow
### Initial Setup
1. Install and activate plugin
2. Configure API connection settings
3. Test Fincon connectivity
4. Configure stock locations
5. Set up product import settings
6. Configure customer import settings
7. Enable order sync if required

### Daily Operations
1. **Product Updates**: Scheduled sync imports product changes
2. **Customer Updates**: New customers automatically added
3. **Order Processing**: Completed orders sent to Fincon
4. **Invoice Management**: Download invoice PDFs as needed
5. **Stock Monitoring**: Real-time stock levels from multiple locations

### Maintenance Tasks
1. **Cron Log Review**: Check scheduled task execution
2. **Error Monitoring**: Review plugin logs for issues
3. **Location Updates**: Modify warehouse configurations
4. **Settings Adjustment**: Tune batch sizes and schedules

## Troubleshooting
### Common Issues
#### Connection Failures
- **Symptoms**: Test connection fails, API errors in logs
- **Solutions**:
  1. Verify server URL and port accessibility
  2. Check Fincon API user credentials
  3. Confirm Data ID matches Fincon configuration
  4. Test network connectivity to Fincon server

#### Product Import Issues
- **Symptoms**: Products not importing, stock not updating
- **Solutions**:
  1. Check product ItemNo/SKU mapping
  2. Verify WebOnly flag in Fincon
  3. Review change detection settings
  4. Check batch size and memory limits

#### Customer Import Issues
- **Symptoms**: Customers not created, role assignment incorrect
- **Solutions**:
  1. Verify email addresses in Fincon records
  2. Check PriceStruc values (1-6)
  3. Review WebList only setting
  4. Check for duplicate email addresses

#### Order Sync Issues
- **Symptoms**: Orders not sending to Fincon, sync errors
- **Solutions**:
  1. Verify order sync is enabled in settings
  2. Check B2C debt account for guest orders
  3. Review payment method mapping
  4. Check Fincon API response for specific errors

### Logs and Debugging
#### WordPress Debug Log
Enable debugging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

#### Plugin-Specific Logs
- **Cron Log**: View in **Fincon → Cron Log**
- **WooCommerce Logs**: Check under **WooCommerce → Status → Logs**
- **API Logs**: Enable in FinconApi class (requires code modification)

#### Common Error Messages
- **"Invalid connect ID"**: Session expired, automatic retry should resolve
- **"Login failed"**: API credentials incorrect
- **"No valid items"**: Order products missing SKUs
- **"Missing B2C account"**: Guest order sync requires debt account configuration

## FAQ
### General Questions
**Q: Can I run the plugin without WooCommerce?**
A: No, WooCommerce is required for product, customer, and order functionality.

**Q: Is the plugin compatible with HPOS (High-Performance Order Storage)?**
A: Yes, the plugin declares HPOS compatibility in the main plugin file.

**Q: What happens if Fincon is offline during a scheduled sync?**
A: The sync will fail and log an error. It will retry on the next scheduled run.

### Product Questions
**Q: How are products matched between Fincon and WooCommerce?**
A: Products are matched using Fincon ItemNo as WooCommerce SKU.

**Q: Can I manually edit products after import?**
A: Yes, but changes may be overwritten by subsequent Fincon imports unless change detection is enabled.

**Q: How are promotional prices handled?**
A: Promotional prices from Fincon (ProPrice fields) are applied based on date ranges and price list assignments.

### Customer Questions
**Q: What happens if a customer has multiple email addresses?**
A: Only the first email (before semicolon) from Email or Email1 field is used.

**Q: Can customers log in with their Fincon account number?**
A: No, customers use their email address to log in. The account number is stored as meta data.

**Q: How are dealer discounts applied?**
A: Dealers see prices from their assigned price list (2-6) instead of retail prices.

### Order Questions
**Q: When are orders sent to Fincon?**
A: Orders sync when payment is complete or status changes to processing (except COD which waits for payment).

**Q: Can I manually sync an order?**
A: Yes, use the **Sync to Fincon Now** button in the order edit screen Fincon meta box.

**Q: How are invoices retrieved from Fincon?**
A: Invoices are fetched via cron job or manually from the invoice management page.

### Technical Questions
**Q: What is the ConnectID and how long does it last?**
A: ConnectID is a session token for Fincon API with 5-minute TTL. The plugin automatically refreshes it.

**Q: Can I customize the API endpoint mappings?**
A: API endpoints are hardcoded in the FinconApi class. Customization requires code modification.

**Q: How are stock locations configured?**
A: Locations are managed in **Fincon → Stock Locations** with codes, names, and rep codes.

## Support and Resources
### Documentation
- This user guide
- API documentation in `_documentation/api/` folder
- Code comments in plugin source files

### Getting Help
- **Plugin Support**: Contact Digital Fold for plugin-specific issues
- **Fincon Support**: Contact your Fincon provider for API/configuration questions
- **WooCommerce Support**: WooCommerce documentation for e-commerce functionality

### Updates and Maintenance
- **Regular Updates**: Check for plugin updates in WordPress admin
- **Backup Strategy**: Always backup before updating
- **Testing Environment**: Test changes in staging before production

---

*Last Updated: Version 0.1.0 | Document Version: 0.1*