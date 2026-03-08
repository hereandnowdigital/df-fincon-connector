<div class="wrap">
  <h1><?php esc_html_e( 'Stock Locations Management', 'df-fincon' ); ?></h1>
  
  <?php if ( ! empty( $message ) ) : ?>
    <div id="message" class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
      <p><?php echo esc_html( $message ); ?></p>
    </div>
  <?php endif; ?>
  
  <div class="df-fincon-stock-locations">
    <div class="df-fincon-locations-header">
      <h2><?php esc_html_e( 'Stock Locations', 'df-fincon' ); ?></h2>
      <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'add' ] ) ); ?>" class="page-title-action">
        <?php esc_html_e( 'Add New Location', 'df-fincon' ); ?>
      </a>
    </div>
    
    <?php if ( empty( $locations ) ) : ?>
      <div class="notice notice-warning">
        <p><?php esc_html_e( 'No stock locations found. Add your first location to get started.', 'df-fincon' ); ?></p>
      </div>
    <?php else : ?>
      <table class="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th scope="col" class="manage-column column-code"><?php esc_html_e( 'Code', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-name"><?php esc_html_e( 'Name', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-short-name"><?php esc_html_e( 'Short Name', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-rep-code"><?php esc_html_e( 'Rep Code', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-active"><?php esc_html_e( 'Active', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-default"><?php esc_html_e( 'Default', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-sort-order"><?php esc_html_e( 'Sort Order', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'df-fincon' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $locations as $code => $location ) : ?>
            <tr>
              <td class="column-code">
                <code><?php echo esc_html( $location['code'] ); ?></code>
              </td>
              <td class="column-name">
                <strong><?php echo esc_html( $location['name'] ); ?></strong>
              </td>
              <td class="column-short-name">
                <?php echo esc_html( $location['short_name'] ); ?>
              </td>
              <td class="column-rep-code">
                <?php echo ! empty( $location['rep_code'] ) ? esc_html( $location['rep_code'] ) : '—'; ?>
              </td>
              <td class="column-active">
                <?php if ( ! empty( $location['active'] ) ) : ?>
                  <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                  <span class="screen-reader-text"><?php esc_html_e( 'Active', 'df-fincon' ); ?></span>
                <?php else : ?>
                  <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                  <span class="screen-reader-text"><?php esc_html_e( 'Inactive', 'df-fincon' ); ?></span>
                <?php endif; ?>
              </td>
              <td class="column-default">
                <?php if ( ! empty( $location['is_default'] ) ) : ?>
                  <span class="dashicons dashicons-star-filled" style="color: #f0ad4e;"></span>
                  <span class="screen-reader-text"><?php esc_html_e( 'Default Location', 'df-fincon' ); ?></span>
                <?php else : ?>
                  <span class="dashicons dashicons-star-empty" style="color: #ccc;"></span>
                  <span class="screen-reader-text"><?php esc_html_e( 'Not Default', 'df-fincon' ); ?></span>
                <?php endif; ?>
              </td>
              <td class="column-sort-order">
                <?php echo esc_html( $location['sort_order'] ?? 99 ); ?>
              </td>
              <td class="column-actions">
                <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'code' => $location['code'] ] ) ); ?>" class="button button-small">
                  <?php esc_html_e( 'Edit', 'df-fincon' ); ?>
                </a>
                <?php if ( empty( $location['is_default'] ) ) : ?>
                  <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'code' => $location['code'] ] ), 'delete_location_' . $location['code'] ) ); ?>" 
                     class="button button-small button-link-delete" 
                     onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this location?', 'df-fincon' ); ?>');">
                    <?php esc_html_e( 'Delete', 'df-fincon' ); ?>
                  </a>
                <?php endif; ?>
                <?php if ( empty( $location['is_default'] ) ) : ?>
                  <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'set_default', 'code' => $location['code'] ] ), 'set_default_' . $location['code'] ) ); ?>" 
                     class="button button-small">
                    <?php esc_html_e( 'Set Default', 'df-fincon' ); ?>
                  </a>
                <?php endif; ?>
                <?php if ( ! empty( $location['active'] ) ) : ?>
                  <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'deactivate', 'code' => $location['code'] ] ), 'toggle_active_' . $location['code'] ) ); ?>" 
                     class="button button-small">
                    <?php esc_html_e( 'Deactivate', 'df-fincon' ); ?>
                  </a>
                <?php else : ?>
                  <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'activate', 'code' => $location['code'] ] ), 'toggle_active_' . $location['code'] ) ); ?>" 
                     class="button button-small">
                    <?php esc_html_e( 'Activate', 'df-fincon' ); ?>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th scope="col" class="manage-column column-code"><?php esc_html_e( 'Code', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-name"><?php esc_html_e( 'Name', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-short-name"><?php esc_html_e( 'Short Name', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-rep-code"><?php esc_html_e( 'Rep Code', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-active"><?php esc_html_e( 'Active', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-default"><?php esc_html_e( 'Default', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-sort-order"><?php esc_html_e( 'Sort Order', 'df-fincon' ); ?></th>
            <th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'df-fincon' ); ?></th>
          </tr>
        </tfoot>
      </table>
    <?php endif; ?>
    
    <div class="df-fincon-locations-info">
      <h3><?php esc_html_e( 'About Stock Locations', 'df-fincon' ); ?></h3>
      <p>
        <?php esc_html_e( 'Stock locations are used to track inventory across different warehouses or branches. Each location has a unique numeric code that corresponds to locations in your Fincon system.', 'df-fincon' ); ?>
      </p>
      <ul>
        <li><?php esc_html_e( 'Location codes must be 2-6 numeric characters', 'df-fincon' ); ?></li>
        <li><?php esc_html_e( 'Exactly one location must be marked as default', 'df-fincon' ); ?></li>
        <li><?php esc_html_e( 'Only active locations are included in API calls', 'df-fincon' ); ?></li>
        <li><?php esc_html_e( 'Rep codes are optional numeric identifiers for sales representatives', 'df-fincon' ); ?></li>
      </ul>
    </div>
  </div>
</div>

<style>
.df-fincon-stock-locations {
  margin-top: 20px;
}
.df-fincon-locations-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.df-fincon-locations-info {
  margin-top: 30px;
  padding: 20px;
  background: #f9f9f9;
  border: 1px solid #ddd;
  border-radius: 4px;
}
.df-fincon-locations-info h3 {
  margin-top: 0;
}
.df-fincon-locations-info ul {
  margin-left: 20px;
}
.column-actions .button {
  margin: 2px;
}
.button-link-delete {
  color: #a00;
  border-color: #a00;
}
.button-link-delete:hover {
  color: #fff;
  background: #a00;
  border-color: #a00;
}
</style>