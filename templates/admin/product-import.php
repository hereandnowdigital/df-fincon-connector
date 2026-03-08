<div class="wrap">
  <h1><?php esc_html_e( 'Manual Product Import', 'df-fincon' ); ?></h1>
  <p><?php esc_html_e( 'Use this tool to import products from the FinCon API into WooCommerce. Start with a small count for testing.', 'df-fincon' ); ?></p>

  <div class="df-fincon-import-controls">
    <table class="form-table">
      <tbody>
        <tr>
          <th col-span="2"><?php _e("IMPORT SINGLE PRODUCT", 'df-fincon');?></th>
        </tr>  
        <tr>
          <th scope="row"><label for="df_fincon_import_product_item_code"><?php esc_html_e( 'Product item code', 'df-fincon' ); ?></label></th>
          <td>
            <input type="text" id="df_fincon_import_product_item_code" value="" class="regular-text" placeholder="<?php esc_html_e( 'E.g. A', 'df-fincon' ); ?>" />
            <p class="description"><?php esc_html_e( 'Enter item code (SKU) of product to import/update', 'df-fincon' ); ?></p>
          </td>
        </tr>            
        <tr>
          <th col-span="2"><?php _e("OR IMPORT BATCH", 'df-fincon');?></th>
        </tr>  
        <tr>
          <th scope="row"><label for="df_fincon_import_count"><?php esc_html_e( 'Number of products to import', 'df-fincon' ); ?></label></th>
          <td>
            <input type="number" id="df_fincon_import_count" value="0" min="0" class="small-text" />
            <p class="description"><?php esc_html_e( 'Enter 0 to import all products. The import will process in batches and can be resumed if interrupted.', 'df-fincon' ); ?></p>
          </td>
        </tr>
           
      </tbody>
    </table>
    
    <?php if ( ! empty( $progress['in_progress'] ) ) : ?>
      <div class="notice notice-info" style="margin: 15px 0;">
        <p><strong><?php esc_html_e( 'Import in Progress', 'df-fincon' ); ?></strong></p>
        <p>
          <?php 
          printf(
            esc_html__( 'Started: %s | Processed: %d products | Last RecNo: %d', 'df-fincon' ),
            esc_html( $progress['started_at'] ?? 'N/A' ),
            (int) ( $progress['total_processed'] ?? 0 ),
            (int) ( $progress['last_rec_no'] ?? 0 )
          );
          ?>
        </p>
      </div>
    <?php endif; ?>
    
    <p class="submit">
      <button id="df-fincon-import-btn" class="button button-primary"><?php esc_html_e( 'Start New Import', 'df-fincon' ); ?></button>
      <?php if ( ! empty( $progress['in_progress'] ) ) : ?>
        <button id="df-fincon-resume-import-btn" class="button button-secondary"><?php esc_html_e( 'Resume Import', 'df-fincon' ); ?></button>
        <button id="df-fincon-reset-import-btn" class="button"><?php esc_html_e( 'Reset Progress', 'df-fincon' ); ?></button>
      <?php endif; ?>
    </p>
  </div>

  <hr>
  
  <h2><?php esc_html_e( 'Import Results', 'df-fincon' ); ?></h2>
  <div id="df-fincon-import-feedback" style="border: 1px solid #ccc; padding: 15px; background-color: #fff;">
      <p><?php esc_html_e( 'Click the "Import Products" button to start the manual import.', 'df-fincon' ); ?></p>
  </div>
</div>