<div class="wrap">
  <h1><?php esc_html_e( 'Product Cron Log', 'df-fincon' ); ?></h1>
  
  <?php settings_errors( 'df_fincon_cron_log_products' ); ?>
  
  <p><?php esc_html_e( 'This page shows file-based logs for product cron sync operations. Each log file contains per-product details including SKU, WooCommerce ID, and status.', 'df-fincon' ); ?></p>
  
  <div class="notice notice-info">
    <p>
      <strong><?php esc_html_e( 'Product cron logging status:', 'df-fincon' ); ?></strong>
      <?php if ( $logging_enabled ) : ?>
        <span style="color: #22A300;"><?php esc_html_e( 'Enabled', 'df-fincon' ); ?></span>
        <br>
        <?php esc_html_e( 'Log files are being created in:', 'df-fincon' ); ?> <code><?php echo esc_html( \DF_FINCON\ProductCronLogger::get_log_dir() ); ?></code>
      <?php else : ?>
        <span style="color: #B32D2E;"><?php esc_html_e( 'Disabled', 'df-fincon' ); ?></span>
        <br>
        <?php esc_html_e( 'Enable "Product cron log" in Product Import Settings to start logging.', 'df-fincon' ); ?>
      <?php endif; ?>
    </p>
  </div>
  
  <?php if ( ! empty( $current_log_content ) ) : ?>
    <div class="df-fincon-log-viewer">
      <h2>
        <?php 
        printf( 
          esc_html__( 'Viewing: %s', 'df-fincon' ), 
          esc_html( $current_log_file ) 
        ); 
        ?>
        <a href="<?php echo esc_url( remove_query_arg( 'view' ) ); ?>" class="button" style="margin-left: 15px;">
          <?php esc_html_e( 'Back to List', 'df-fincon' ); ?>
        </a>
      </h2>
      
      <div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; margin: 15px 0; max-height: 600px; overflow-y: auto;">
        <pre style="white-space: pre-wrap; font-family: monospace; font-size: 13px; line-height: 1.4; margin: 0;"><?php echo esc_html( $current_log_content ); ?></pre>
      </div>
      
      <p>
        <a href="<?php echo esc_url( remove_query_arg( 'view' ) ); ?>" class="button">
          <?php esc_html_e( 'Back to List', 'df-fincon' ); ?>
        </a>
      </p>
    </div>
  <?php else : ?>
    <div class="df-fincon-log-files">
      <h2><?php esc_html_e( 'Log Files', 'df-fincon' ); ?></h2>
      
      <?php if ( ! empty( $log_files ) ) : ?>
        <p>
          <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'clear_all_logs' ), 'clear_all_product_cron_logs' ) ); ?>" 
             class="button" 
             onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete ALL product cron log files? This action cannot be undone.', 'df-fincon' ); ?>');">
            <?php esc_html_e( 'Delete All Logs', 'df-fincon' ); ?>
          </a>
          <span class="description" style="margin-left: 10px;">
            <?php esc_html_e( 'Total files:', 'df-fincon' ); ?> <?php echo count( $log_files ); ?>
          </span>
        </p>
        
        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th style="width: 40%;"><?php esc_html_e( 'File Name', 'df-fincon' ); ?></th>
              <th style="width: 20%;"><?php esc_html_e( 'Modified', 'df-fincon' ); ?></th>
              <th style="width: 15%;"><?php esc_html_e( 'Size', 'df-fincon' ); ?></th>
              <th style="width: 25%;"><?php esc_html_e( 'Actions', 'df-fincon' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $log_files as $file_info ) : ?>
              <tr>
                <td>
                  <strong><?php echo esc_html( $file_info['name'] ); ?></strong>
                </td>
                <td>
                  <?php echo esc_html( $file_info['modified_date'] ); ?>
                </td>
                <td>
                  <?php
                  $size = $file_info['size'];
                  if ( $size < 1024 ) {
                    echo esc_html( sprintf( '%d B', $size ) );
                  } elseif ( $size < 1048576 ) {
                    echo esc_html( sprintf( '%.1f KB', $size / 1024 ) );
                  } else {
                    echo esc_html( sprintf( '%.1f MB', $size / 1048576 ) );
                  }
                  ?>
                </td>
                <td>
                  <a href="<?php echo esc_url( add_query_arg( 'view', $file_info['name'] ) ); ?>" class="button button-small">
                    <?php esc_html_e( 'View', 'df-fincon' ); ?>
                  </a>
                  
                  <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete_log', 'file' => $file_info['name'] ] ), 'delete_product_cron_log' ) ); ?>" 
                     class="button button-small button-link-delete"
                     onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this log file?', 'df-fincon' ); ?>');">
                    <?php esc_html_e( 'Delete', 'df-fincon' ); ?>
                  </a>
                  
                  <a href="<?php echo esc_url( add_query_arg( 'download', $file_info['name'] ) ); ?>" class="button button-small">
                    <?php esc_html_e( 'Download', 'df-fincon' ); ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else : ?>
        <div class="notice notice-info">
          <p><?php esc_html_e( 'No product cron log files found. Log files will be created when scheduled product syncs run with logging enabled.', 'df-fincon' ); ?></p>
        </div>
      <?php endif; ?>
      
      <div class="df-fincon-log-info" style="margin-top: 30px; padding: 15px; background: #f5f5f5; border-left: 4px solid #0073AA;">
        <h3><?php esc_html_e( 'About Product Cron Logs', 'df-fincon' ); ?></h3>
        <p><?php esc_html_e( 'When enabled, product cron logs record:', 'df-fincon' ); ?></p>
        <ul style="list-style-type: disc; margin-left: 20px;">
          <li><?php esc_html_e( 'Date and time the cron started (using site timezone)', 'df-fincon' ); ?></li>
          <li><?php esc_html_e( 'Per-product details: SKU, WooCommerce ID, and status (created, updated, skipped)', 'df-fincon' ); ?></li>
          <li><?php esc_html_e( 'Date and time the cron finished successfully', 'df-fincon' ); ?></li>
          <li><?php esc_html_e( 'Summary of total products processed', 'df-fincon' ); ?></li>
        </ul>
        <p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=df-fincon-plugin-settings&tab=product' ) ); ?>" class="button">
            <?php esc_html_e( 'Configure Product Cron Log Settings', 'df-fincon' ); ?>
          </a>
        </p>
      </div>
    </div>
  <?php endif; ?>
</div>

<style>
.df-fincon-log-viewer pre {
  background: #1d2327;
  color: #f0f0f1;
  padding: 15px;
  border-radius: 4px;
}

.button-link-delete {
  color: #d63638;
  border-color: #d63638;
}

.button-link-delete:hover {
  background: #d63638;
  color: white;
  border-color: #d63638;
}
</style>