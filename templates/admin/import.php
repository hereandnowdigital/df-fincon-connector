<div class="wrap">
    <h1><?php esc_html_e( 'FinCon Product Import', 'df-fincon' ); ?></h1>
    <p><?php esc_html_e( 'Use this tool to import products from the FinCon API into WooCommerce. Start with a small count for testing.', 'df-fincon' ); ?></p>

    <div class="df-fincon-import-controls">
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="df_fincon_import_count"><?php esc_html_e( 'Number of Products to Import', 'df-fincon' ); ?></label></th>
                    <td>
                        <input type="number" id="df_fincon_import_count" value="10" min="1" max="50" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Limit the batch size (e.g., 10 for testing). Max 50 per run.', 'df-fincon' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="df_fincon_import_offset"><?php esc_html_e( 'Start Offset', 'df-fincon' ); ?></label></th>
                    <td>
                        <input type="number" id="df_fincon_import_offset" value="0" min="0" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Start the import from this item number (e.g., 0 for the beginning).', 'df-fincon' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <button id="df-fincon-import-btn" class="button button-primary"><?php esc_html_e( 'Import Products', 'df-fincon' ); ?></button>
        </p>
    </div>

    <hr>
    
    <h2><?php esc_html_e( 'Import Results/Feedback', 'df-fincon' ); ?></h2>
    <div id="df-fincon-import-feedback" style="border: 1px solid #ccc; padding: 15px; background-color: #fff;">
        <p><?php esc_html_e( 'Click the "Import Products" button to start the test import.', 'df-fincon' ); ?></p>
    </div>
</div>