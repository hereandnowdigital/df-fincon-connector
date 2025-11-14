<?php
/**
 * Cron Functionality
 * 
 * @author  Elizabeth Meyer <elizabeth@hereandnowdigital.co.za>
 * @package df-fincon-connector
 * Text Domain: df-fincon
 * 
 */

namespace DF_FINCON;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) )
  exit;

class Cron {

    const HOOK = 'df_fincon_product_sync';

    public static function init() {
        // Schedule event if not already scheduled
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( strtotime('23:00:00'), 'daily', self::HOOK );
        }

        // Hook our batch process
        add_action( self::HOOK, [ __CLASS__, 'run_nightly_sync' ] );
    }

    public static function run_nightly_sync() {
        ProductBatchImporter::process_next_batch();
    }

    public static function clear_scheduled_hook() {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }
}

