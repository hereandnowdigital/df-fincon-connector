<div class="wrap">
  <h1><?php esc_html_e( 'FinCon Connector Settings', 'df-fincon' ); ?></h1>
  <form method="post" action="options.php" id="df-fincon-settings-form">
      <?php settings_fields( 'df_fincon_settings_group' ); ?>
      
      <h2 class="nav-tab-wrapper">
          <a href="#" class="nav-tab nav-tab-active" data-tab="api"><?php esc_html_e( 'API Settings', 'df-fincon' ); ?></a>
          <!-- <a href="#" class="nav-tab" data-tab="product"><?php esc_html_e( 'Product Sync', 'df-fincon' ); ?></a>
          <a href="#" class="nav-tab" data-tab="customer"><?php esc_html_e( 'Customer Sync', 'df-fincon' ); ?></a> -->
      </h2>

      <div class="tab-content" id="tab-api">
          <?php do_settings_sections( 'df-fincon-settings-page' ); ?>
          
          <p class="submit">
              <?php submit_button( null, 'primary', 'df-fincon-save-settings', false ); ?>
              
              <button type="button" id="df-fincon-test-connection" class="button" data-nonce="<?php echo esc_attr( \wp_create_nonce( \DF_FINCON\Admin::TEST_NONCE ) ); ?>">
                  <?php esc_html_e( 'Test Connection', 'df-fincon' ); ?>
              </button>
          </p>

          <div id="df-fincon-test-status" style="margin-top: 15px; font-weight: bold;"></div>
      </div>
      
     <div class="tab-content" id="tab-product" style="display:none;">
          <h2><?php esc_html_e( 'Manual Product Synchronization', 'df-fincon' ); ?></h2>
          <p class="description"><?php esc_html_e( 'Use this to manually trigger a sync, often for initial setup or debugging specific batches of products.', 'df-fincon' ); ?></p>
          
          <table class="form-table">
              <tbody>
                  <tr>
                      <th scope="row"><label for="manual_sync_count"><?php esc_html_e( 'Count', 'df-fincon' ); ?></label></th>
                      <td>
                          <input type="number" id="manual_sync_count" class="small-text" value="100" min="1" max="1000" />
                          <p class="description"><?php esc_html_e( 'Number of Fincon products to retrieve in this batch (Max 1000).', 'df-fincon' ); ?></p>
                      </td>
                  </tr>
                  <tr>
                      <th scope="row"><label for="manual_sync_offset"><?php esc_html_e( 'Offset (RecNo)', 'df-fincon' ); ?></label></th>
                      <td>
                          <input type="number" id="manual_sync_offset" class="small-text" value="0" min="0" />
                          <p class="description"><?php esc_html_e( 'Record number (RecNo) to start fetching from (0 for the beginning).', 'df-fincon' ); ?></p>
                      </td>
                  </tr>
              </tbody>
          </table>
          
          <p class="submit">
              <button type="button" id="df-fincon-manual-sync-products" class="button button-secondary" data-nonce="<?php echo esc_attr( \wp_create_nonce( 'df_fincon_manual_sync_nonce' ) ); ?>">
                  <?php esc_html_e( 'Start Product Sync', 'df-fincon' ); ?>
              </button>
          </p>
          
          <div id="df-fincon-sync-status" style="margin-top: 15px; font-weight: bold;"></div>
          
      </div>

        <div class="tab-content" id="tab-customer" style="display:none;">
          <?php // TBC content ?>
          <p><?php esc_html_e( 'Customer sync settings will appear here.', 'df-fincon' ); ?></p>
            <p class="submit">
              <?php submit_button( null, 'primary', 'df-fincon-save-customer', false ); ?>
          </p>
      </div>
  </form>
</div>
