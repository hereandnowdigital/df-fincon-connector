<div class="wrap">
  <h1><?php esc_html_e( 'API Connection', 'df-fincon' ); ?></h1>
  <form method="post" action="options.php" id="df-fincon-api-settings-form">
    <?php settings_fields( \DF_FINCON\Admin::OPTIONS_GROUP_API ); ?>
    
    <div class="tab-content" id="tab-api">
        <?php do_settings_sections( \DF_FINCON\Admin::OPTIONS_NAME_API ); ?>
        
        <p class="submit">
          <?php submit_button( null, 'primary', 'df-fincon-save-settings', false ); ?>
          
          <button type="button" id="df-fincon-test-connection" class="button" data-nonce="<?php echo esc_attr( \wp_create_nonce( \DF_FINCON\Admin::TEST_NONCE ) ); ?>">
            <?php esc_html_e( 'Test Connection', 'df-fincon' ); ?>
          </button>
      </p>

        <div id="df-fincon-test-status" style="margin-top: 15px; font-weight: bold;"></div>
    </div>
  </form>
</div>