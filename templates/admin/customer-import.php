<div class="wrap">
  <h1><?php esc_html_e( 'Manual Customer Import', 'df-fincon' ); ?></h1>
  <p><?php esc_html_e( 'Use this tool to import FinCon debtors as WooCommerce customers. Start with a small batch while testing.', 'df-fincon' ); ?></p>

  <div class="df-fincon-import-controls">
    <table class="form-table">
      <tbody>
        <tr>
          <th colspan="2"><?php esc_html_e( 'Import Single Customer', 'df-fincon' ); ?></th>
        </tr>
        <tr>
          <th scope="row"><label for="df_fincon_customer_accno"><?php esc_html_e( 'FinCon AccNo', 'df-fincon' ); ?></label></th>
          <td>
            <input type="text" id="df_fincon_customer_accno" value="" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. TES001', 'df-fincon' ); ?>" />
            <p class="description"><?php esc_html_e( 'Leave empty to run a batch import.', 'df-fincon' ); ?></p>
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <p class="submit">
              <button id="df-fincon-customer-import-btn" class="button button-primary">
                <?php esc_html_e( 'Run Customer Import', 'df-fincon' ); ?>
              </button>
            </p>
          </td>
        </tr>
       <tr>
          <td colspan="2"><hr/></td>
        </tr>
        <tr>
          <th colspan="2"><?php esc_html_e( 'Batch Import', 'df-fincon' ); ?></th>
        </tr>

        <tr>
          <th scope="row"><label for="df_fincon_customer_count"><?php esc_html_e( 'Number of customers to import', 'df-fincon' ); ?></label></th>
          <td>
            <input type="number" id="df_fincon_customer_count" value="<?php echo isset( $options['customer_batch_size'] ) ? esc_attr( $options['customer_batch_size'] ) : 50; ?>" min="1" class="small-text" />
            <p class="description"><?php esc_html_e( 'The number of debtor records to request per batch.', 'df-fincon' ); ?></p>
          </td>
        </tr>

        <tr>
          <th scope="row"><label for="df_fincon_customer_offset"><?php esc_html_e( 'Start offset (RecNo)', 'df-fincon' ); ?></label></th>
          <td>
            <input type="number" id="df_fincon_customer_offset" value="0" min="0" class="small-text" />
            <p class="description"><?php esc_html_e( 'Useful for resuming a batch from a specific RecNo.', 'df-fincon' ); ?></p>
          </td>
        </tr>

        <tr>
          <th scope="row"><?php esc_html_e( 'Options', 'df-fincon' ); ?></th>
          <td>
            <label>
              <input type="checkbox" id="df_fincon_customer_only_changed" value="1" <?php checked( ! empty( $options['customer_import_only_changed'] ) ); ?> />
              <?php esc_html_e( 'Import only changed customers', 'df-fincon' ); ?>
            </label>
            <br />
            <label>
              <input type="checkbox" id="df_fincon_customer_weblist_only" value="1" <?php checked( ! empty( $options['customer_weblist_only'] ) ); ?> />
              <?php esc_html_e( 'WebList only', 'df-fincon' ); ?>
            </label>
          </td>
        </tr>

      </tbody>
    </table>

    <p class="submit">
      <button id="df-fincon-customer-import-btn" class="button button-primary">
        <?php esc_html_e( 'Run Customer Import', 'df-fincon' ); ?>
      </button>
    </p>
  </div>

  <hr>

  <h2><?php esc_html_e( 'Import Results', 'df-fincon' ); ?></h2>
  <div id="df-fincon-customer-import-feedback" style="border: 1px solid #ccc; padding: 15px; background-color: #fff;">
    <p><?php esc_html_e( 'Click the "Run Customer Import" button to start importing.', 'df-fincon' ); ?></p>
  </div>
</div>

