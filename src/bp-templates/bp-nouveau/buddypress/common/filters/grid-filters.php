<?php
/**
 * BP Nouveau Component's grid filters template.
 *
 * @since BuddyBoss 1.0.0
 */

global $post;

if ( bp_is_members_directory() || bp_is_user() || ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'profile') ) )  {
	$current_value = bp_get_option( 'bp-profile-layout-format', 'list_grid' );
} elseif ( bp_is_groups_directory() || bp_is_group() ) {
	$current_value = bp_get_option( 'bp-group-layout-format', 'list_grid' );
}
if ( 'list_grid' === $current_value ) {


	$list                  = false;
	$default_current_value = '';
	if ( isset( $_POST['extras'] ) && ! empty( $_POST['extras']['layout'] ) && 'list' === $_POST['extras']['layout'] ) {
		$list                  = true;
		$default_current_value = 'list';
	}

	if ( ! $list ) {
		if ( bp_is_members_directory() || bp_is_user() || ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'profile') ) ) {
			$default_current_value = bp_profile_layout_default_format( 'grid' );
		} elseif ( bp_is_groups_directory() || bp_is_group() ) {
			$default_current_value = bp_profile_layout_default_format( 'grid' );
		}
	}
	$component = bp_current_component();
	if ( bp_is_group() && 'members' === bp_current_action() ) {
		$component = 'group_members';
	}
	?>
<div class="grid-filters" data-object="<?php echo esc_attr( $component ); ?>">
	<a href="#" class="layout-view layout-grid-view bp-tooltip <?php echo ( 'grid' === $default_current_value ) ? 'active' : ''; ?>" data-view="grid" data-bp-tooltip-pos="up" data-bp-tooltip="<?php _e( 'Grid View',
		'buddyboss' ); ?>"> <i class="dashicons dashicons-screenoptions" aria-hidden="true"></i> </a>

	<a href="#" class="layout-view layout-list-view bp-tooltip <?php echo ( 'list' === $default_current_value ) ? 'active' : ''; ?>" data-view="list" data-bp-tooltip-pos="up" data-bp-tooltip="<?php _e( 'List View',
		'buddyboss' ); ?>"> <i class="dashicons dashicons-menu" aria-hidden="true"></i> </a>
	</div><?php
}
