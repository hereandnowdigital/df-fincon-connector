<div class="wrap">
  <h1><?php esc_html_e( 'Settings', 'df-fincon' ); ?></h1>
  
  <div class="df-fincon-settings-section">
    <p><?php esc_html_e( 'Configure import and sync settings below:', 'df-fincon' ); ?></p>
    
    <h2 class="nav-tab-wrapper">
      <a href="#" class="nav-tab nav-tab-active" data-tab="product"><?php esc_html_e( 'Product Import Settings', 'df-fincon' ); ?></a>
      <a href="#" class="nav-tab" data-tab="customer"><?php esc_html_e( 'Customer Import Settings', 'df-fincon' ); ?></a>
      <a href="#" class="nav-tab" data-tab="order"><?php esc_html_e( 'Order Sync Settings', 'df-fincon' ); ?></a>
    </h2>

    <div class="tab-content" id="tab-product">
      <form method="post" action="options.php" id="df-fincon-product-settings-form">
        <?php settings_fields( \DF_FINCON\Admin::OPTIONS_GROUP_PRODUCTS ); ?>
        <?php do_settings_sections( \DF_FINCON\Admin::OPTIONS_NAME_PRODUCTS ); ?>
        
        <p class="submit">
          <?php submit_button( null, 'primary', 'df-fincon-save-settings', false ); ?>
        </p>
        
        <div id="df-fincon-sync-status" style="margin-top: 15px; font-weight: bold;"></div>
      </form>
    </div>
    
    <div class="tab-content" id="tab-customer" style="display:none;">
      <form method="post" action="options.php" id="df-fincon-customer-settings-form">
        <?php settings_fields( \DF_FINCON\Admin::OPTIONS_GROUP_CUSTOMERS ); ?>
        <?php do_settings_sections( \DF_FINCON\Admin::OPTIONS_NAME_CUSTOMERS ); ?>
        <p class="submit">
          <?php submit_button( null, 'primary', 'df-fincon-save-customer-settings', false ); ?>
        </p>
      </form>
    </div>

    <div class="tab-content" id="tab-order" style="display:none;">
      <form method="post" action="options.php" id="df-fincon-order-settings-form">
        <?php settings_fields( \DF_FINCON\Admin::OPTIONS_GROUP_ORDERS ); ?>
        <?php do_settings_sections( \DF_FINCON\Admin::OPTIONS_NAME_ORDERS ); ?>
        <p class="submit">
          <?php submit_button( null, 'primary', 'df-fincon-save-order-settings', false ); ?>
        </p>
      </form>
    </div>
  </div>
</div>

<style>
.df-fincon-settings-section {
  margin-top: 20px;
  background: #fff;
  border: 1px solid #ccd0d4;
  border-radius: 4px;
  padding: 20px;
  box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
</style>
