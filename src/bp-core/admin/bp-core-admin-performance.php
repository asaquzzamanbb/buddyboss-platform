<?php
/**
 * BuddyBoss Performance panel.
 *
 * @package BuddyBoss\Core
 * @since BuddyBoss 1.1.8
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Render the BuddyBoss Performance page.
 *
 * @since BuddyBoss 1.1.8
 */
function bp_core_admin_performance() {

	bp_admin_performance_data_save();

	?>
    <div class="wrap">
        <h2 class="nav-tab-wrapper"><?php bp_core_admin_tabs( __( 'Performance', 'buddyboss' ) ); ?></h2>
        <form action="" method="post">

            <div class="bp-admin-card section-bp_performance"><h2><?php _e( 'Performance', 'buddyboss' ); ?></h2>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><?php _e( 'Opcode (PHP) Cache', 'buddyboss' ); ?></th>
                        <td>
                            <?php bp_admin_performance_setting_caching_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Database Cache', 'buddyboss' ); ?></th>
                        <td>
                            <?php _e( '(dummy text)', 'buddyboss' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <?php bp_performance_flush_cache_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <?php bp_performance_cache_tutorial(); ?>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <p class="submit">
                <input class="button-primary" type="submit" name="bp-admin-submit-performance" id="bp-admin-submit-performance"
                       value="<?php esc_attr_e( 'Save Settings', 'buddyboss' ); ?>"/>
            </p>

	        <?php wp_nonce_field( 'bp-admin-performance-settings' ); ?>

        </form>
    </div>

	<?php
}

/**
 * Caching setting field.
 *
 * @since BuddyBoss 1.1.8
 *
 */
function bp_admin_performance_setting_caching_callback() {
	?>

    <input id="bp-performance-enable-caching" name="bp-performance-enable-caching" type="checkbox" value="1" <?php checked( bp_performance_is_caching_enabled() ); ?> />
    <label for="bp-performance-enable-caching"><?php echo sprintf( __( 'Enable using %s caching method', 'buddyboss' ), bp_performance_caching_methods_dropdown() ); ?></label>
    <?php
	$cache_methods = array();

	if ( function_exists( 'opcache_reset' ) && ini_get( 'opcache.enable' ) ) {
		$cache_methods[] = __( '<a href="https://www.php.net/manual/en/intro.opcache.php">Zend OPcache</a>', 'buddyboss' );
    }

//	if ( function_exists( 'apc_store' ) || function_exists( 'apcu_store' ) ) {
//		$cache_methods[] = __( '<a href="https://www.php.net/manual/en/intro.apc.php">APC</a>', 'buddyboss' );
//	}

	if ( class_exists( 'Redis' ) ) {
		$cache_methods[] = __( '<a href="https://redis.io/">Redis</a>', 'buddyboss' );
	}

//	if ( class_exists( 'Memcache' ) ) {
//		$cache_methods[] = __( '<a href="https://www.php.net/manual/en/intro.memcache.php">Memcache</a>', 'buddyboss' );
//	}

	$cache_methods_str   = '';
	$cache_methods_count = 1;
	foreach ( $cache_methods as $cache_method ) {
        $cache_methods_str .= $cache_method;

        if ( $cache_methods_count < sizeof( $cache_methods ) ) {
            $cache_methods_str .= __( ' and ', 'buddyboss' );
        }
		$cache_methods_count ++;
	}

    if ( ! empty( $cache_methods_str ) ) {
        ?><p class="description"><?php echo sprintf( __( 'You have %s enabled on your server. BuddyBoss supports <a href="https://www.php.net/manual/en/intro.opcache.php">Zend OPcache</a> and <a href="https://redis.io/">Redis</a> only.', 'buddyboss' ), $cache_methods_str ); ?></p><?php
    } else {
        ?><p class="description"><?php _e( '<strong>You have no cache enabled on your server.</strong> Ask your web hosting to enable <a href="https://www.php.net/manual/en/intro.opcache.php">Zend OPcache</a> or <a href="https://redis.io/">Redis</a> on your server, and then return here to finish the configuration. Your site will load faster after.', 'buddyboss' ); ?></p><?php
    }
}

/**
 * Flush Caching setting field.
 *
 * @since BuddyBoss 1.1.9
 *
 */
function bp_performance_flush_cache_callback() {
	$performance_tab_url = wp_nonce_url( bp_get_admin_url( add_query_arg( array( 'page' => 'bp-performance', 'bp_flush_opcache_action' => 'bpflushopcacheall' ), 'admin.php' ) ), 'bp_flush_opcache_all' );
	?>

    <p>
        <a class="button" href="<?php echo esc_url( $performance_tab_url ); ?>"><?php _e( 'Flush Cache', 'buddyboss' ); ?></a>
    </p>

	<?php
}

/**
 * @since BuddyBoss 1.1.9
 */
function bp_performance_caching_methods_dropdown() {
	$caching_method = bp_performance_enabled_caching_method();
    ob_start();
    ?>
    <select name="bp-performance-caching-method">
        <option value=""><?php _e( 'Not Available', 'buddyboss' ); ?></option>
        <option <?php echo function_exists( 'opcache_reset' ) && ini_get( 'opcache.enable' ) ? '' : 'disabled'; ?> value="opcache" <?php echo 'opcache' == $caching_method? 'selected' : ''; ?>><?php esc_html_e( 'Zend OPcache', 'buddyboss' ) ?></option>
        <option <?php echo function_exists( 'apc_store' ) || function_exists( 'apcu_store' ) ? '' : 'disabled'; ?> value="apc" <?php echo 'apc' == $caching_method? 'selected' : ''; ?>><?php esc_html_e( 'APC', 'buddyboss' ) ?></option>
        <option <?php echo class_exists( 'Redis' ) ? '' : 'disabled'; ?> value="redis" <?php echo 'redis' == $caching_method? 'selected' : ''; ?>><?php esc_html_e( 'Redis', 'buddyboss' ) ?></option>
        <option <?php echo class_exists( 'Memcache' ) || class_exists( 'Memcached' ) ? '' : 'disabled'; ?> value="memcache" <?php echo 'memcache' == $caching_method? 'selected' : ''; ?>><?php esc_html_e( 'Memcache', 'buddyboss' ) ?></option>
    </select>
    <?php
    return ob_get_clean();
}

/**
 * Performance Caching tutorial
 *
 * @since BuddyBoss 1.1.9
 *
 */
function bp_performance_cache_tutorial() {
	?>

    <p>
        <a class="button" href="<?php echo bp_core_help_docs_link( 'performance/caching.md' ); ?>"><?php _e( 'View Tutorial', 'buddyboss' ); ?></a>
    </p>

	<?php
}

/**
 * Save performance tab data
 *
 * @since BuddyBoss 1.1.9
 */
function bp_admin_performance_data_save() {

	if ( isset( $_POST['bp-admin-submit-performance'] ) ) {
		// Check nonce before we do anything.
		check_admin_referer( 'bp-admin-performance-settings' );

		$enable_caching = isset( $_POST['bp-performance-enable-caching'] );
		$caching_method = ! empty( $_POST['bp-performance-caching-method'] ) ? $_POST['bp-performance-caching-method'] : false;

		if ( $enable_caching && ! empty( $caching_method ) ) {
		    bp_update_option( 'bp-performance-enable-caching', true );
		    bp_update_option( 'bp-performance-caching-method', $caching_method );
        } else {
			bp_delete_option( 'bp-performance-enable-caching' );
			bp_delete_option( 'bp-performance-caching-method' );
        }

		do_action( 'bp_admin_performance_data_save' );
	}
}