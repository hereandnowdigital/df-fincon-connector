<div class="wrap">
  <h1><?php esc_html_e( 'Fincon Connector', 'df-fincon' ); ?></h1>
  
  <div class="df-fincon-dashboard">
    <div class="df-fincon-quick-links">
      <h2><?php esc_html_e( 'Quick Links', 'df-fincon' ); ?></h2>
      <div class="df-fincon-card-grid">
        <div class="df-fincon-card">
          <h3><?php esc_html_e( 'Settings', 'df-fincon' ); ?></h3>
          <p><?php esc_html_e( 'Configure import and sync settings', 'df-fincon' ); ?></p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=df-fincon-plugin-settings' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Configure', 'df-fincon' ); ?>
          </a>
        </div>
        
        <div class="df-fincon-card">
          <h3><?php esc_html_e( 'API Settings', 'df-fincon' ); ?></h3>
          <p><?php esc_html_e( 'Configure Fincon API connection details', 'df-fincon' ); ?></p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=df-fincon-api-settings' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Configure', 'df-fincon' ); ?>
          </a>
        </div>
        
        <div class="df-fincon-card">
          <h3><?php esc_html_e( 'Manual Product Import', 'df-fincon' ); ?></h3>
          <p><?php esc_html_e( 'Manually import products from Fincon', 'df-fincon' ); ?></p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=df-fincon-import' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Import Products', 'df-fincon' ); ?>
          </a>
        </div>
        
        <div class="df-fincon-card">
          <h3><?php esc_html_e( 'Manual Customer Import', 'df-fincon' ); ?></h3>
          <p><?php esc_html_e( 'Manually import customers from Fincon', 'df-fincon' ); ?></p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=df-fincon-customer-import' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Import Customers', 'df-fincon' ); ?>
          </a>
        </div>
               
        <div class="df-fincon-card">
          <h3><?php esc_html_e( 'Stock Locations', 'df-fincon' ); ?></h3>
          <p><?php esc_html_e( 'Manage stock locations and defaults', 'df-fincon' ); ?></p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=df-fincon-stock-locations' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'Manage Locations', 'df-fincon' ); ?>
          </a>
        </div>
        
        <div class="df-fincon-card">
          <h3><?php esc_html_e( 'Cron Log', 'df-fincon' ); ?></h3>
          <p><?php esc_html_e( 'View scheduled sync logs and status', 'df-fincon' ); ?></p>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=df-fincon-cron-log' ) ); ?>" class="button button-primary">
            <?php esc_html_e( 'View Logs', 'df-fincon' ); ?>
          </a>
        </div>

      </div>
    </div>
    
  </div>
</div>