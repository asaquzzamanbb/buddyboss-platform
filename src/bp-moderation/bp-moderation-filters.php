<?php
/**
 * Filters related to the Moderation component.
 *
 * @since   BuddyBoss 2.0.0
 * @package BuddyBoss\Moderation
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

new BP_Moderation_Activity();
new BP_Moderation_Activity_Comment();
new BP_Moderation_Groups();
new BP_Moderation_Members();
new BP_Moderation_Members_Suspend();
new BP_Moderation_Forums();
new BP_Moderation_Forum_Topics();
new BP_Moderation_Forum_Replies();
new BP_Moderation_Document();
new BP_Moderation_Media();
new BP_Moderation_Messages();

/**
 * Update modebypass Param
 * @since BuddyBoss 2.0.0
 *
 * @param Array $params Array of key/value pairs for AJAX usage.
 */
function bp_moderation_js_strings( $params ) {
	$params['modbypass'] = filter_input( INPUT_GET, 'modbypass', FILTER_SANITIZE_NUMBER_INT );

	return $params;
}

add_filter( 'bp_core_get_js_strings', 'bp_moderation_js_strings' );

/**
 * Function to handle frontend report form submission.
 *
 * @since BuddyBoss 2.0.0
 */
function bp_moderation_content_report() {
	$response = array(
		'success' => false,
		'message' => '',
	);

	$nonce     = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
	$item_id   = filter_input( INPUT_POST, 'content_id', FILTER_SANITIZE_NUMBER_INT );
	$item_type = filter_input( INPUT_POST, 'content_type', FILTER_SANITIZE_STRING );
	$category  = filter_input( INPUT_POST, 'report_category', FILTER_SANITIZE_STRING );
	if ( 'other' !== $category ) {
		$category = filter_input( INPUT_POST, 'report_category', FILTER_SANITIZE_NUMBER_INT );
	}
	$item_note = filter_input( INPUT_POST, 'note', FILTER_SANITIZE_STRING );

	if ( empty( $item_id ) || empty( $item_type ) || empty( $category ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_missing_data', esc_html__( 'Required field missing.', 'buddyboss' ) );
	}

	if ( bp_moderation_report_exist( $item_id, $item_type ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_already_reported', esc_html__( 'Already reported this item.', 'buddyboss' ) );
	}

	if ( wp_verify_nonce( $nonce, 'bp-moderation-content' ) && ! is_wp_error( $response['message'] ) ) {
		$moderation = bp_moderation_add(
			array(
				'content_id'   => $item_id,
				'content_type' => $item_type,
				'category_id'  => $category,
				'note'         => $item_note,
			)
		);

		if ( ! empty( $moderation->id ) && ! empty( $moderation->report_id ) ) {
			$response['success']    = true;
			$response['moderation'] = $moderation;

			$response['button'] = bp_moderation_get_report_button(
				array(
					'button_attr' => array(
						'data-bp-content-id'   => $item_id,
						'data-bp-content-type' => $item_type,
					),
				),
				false
			);
		}

		$response['message'] = $moderation->errors;
	}

	if ( empty( $response['success'] ) && empty( $response['message'] ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_missing_error', esc_html__( 'Sorry, Something happened wrong', 'buddyboss' ) );
	}

	echo wp_json_encode( $response );
	exit();
}

add_action( 'wp_ajax_bp_moderation_content_report', 'bp_moderation_content_report' );
add_action( 'wp_ajax_nopriv_bp_moderation_content_report', 'bp_moderation_content_report' );


/**
 * Function to handle frontend block member form submission.
 *
 * @since BuddyBoss 2.0.0
 */
function bp_moderation_block_member() {
	$response = array(
		'success' => false,
		'message' => '',
	);

	$nonce     = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
	$item_id   = filter_input( INPUT_POST, 'content_id', FILTER_SANITIZE_NUMBER_INT );
	$item_type = filter_input( INPUT_POST, 'content_type', FILTER_SANITIZE_STRING );

	if ( empty( $item_id ) || empty( $item_type ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_missing_data', esc_html__( 'Required field missing.', 'buddyboss' ) );
	}

	if ( bp_moderation_report_exist( $item_id, $item_type ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_already_reported', esc_html__( 'Already reported this item.', 'buddyboss' ) );
	}

	if ( wp_verify_nonce( $nonce, 'bp-moderation-content' ) && ! is_wp_error( $response['message'] ) ) {
		$moderation = bp_moderation_add(
			array(
				'content_id'   => $item_id,
				'content_type' => $item_type,
				'category_id'  => 0,
				'note'         => '',
			)
		);

		if ( ! empty( $moderation->id ) && ! empty( $moderation->report_id ) ) {
			$response['success']    = true;
			$response['moderation'] = $moderation;

			if ( bp_is_friend( $item_id ) ) {
				friends_remove_friend( bp_loggedin_user_id(), $item_id );
			}

			if ( bp_is_following( array( 'leader_id' => $item_id, 'follower_id' => bp_loggedin_user_id() ) ) ) {
				bp_stop_following( array( 'leader_id' => $item_id, 'follower_id' => bp_loggedin_user_id() ) );
			}

			$response['button'] = bp_moderation_get_report_button(
				array(
					'button_attr' => array(
						'data-bp-content-id'   => $item_id,
						'data-bp-content-type' => $item_type,
					),
				),
				false
			);
		}

		$response['message'] = $moderation->errors;
	}

	if ( empty( $response['success'] ) && empty( $response['message'] ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_missing_error', esc_html__( 'Sorry, Something happened wrong', 'buddyboss' ) );
	}

	echo wp_json_encode( $response );
	exit();

}

add_action( 'wp_ajax_bp_moderation_block_member', 'bp_moderation_block_member' );
add_action( 'wp_ajax_nopriv_bp_moderation_block_member', 'bp_moderation_block_member' );

/**
 * Function to handle frontend unblock user request.
 *
 * @since BuddyBoss 2.0.0
 */
function bp_moderation_unblock_user() {
	$response = array(
		'success' => false,
		'message' => '',
	);

	$nonce          = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
	$type           = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_STRING );
	$id             = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );
	$moderation_obj = new BP_Moderation( $id, $type );
	$moderation_obj->populate();

	if ( $moderation_obj->check_moderation_report_exist( $moderation_obj->id, get_current_user_id() ) ) {

		if ( wp_verify_nonce( $nonce, 'bp-unblock-user' ) ) {

			$unlock = bp_moderation_delete_reported_item( $id, $type );

			if ( ! empty( $unlock ) ) {
				$response['success'] = true;
				$response['message'] = esc_html__( 'User unblocked successfully', 'buddyboss' );
			}
		}
	}

	if ( empty( $response['success'] ) && empty( $response['message'] ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_block_error', esc_html__( 'Sorry, Something happened wrong', 'buddyboss' ) );
	}

	echo wp_json_encode( $response );
	exit();
}

add_action( 'wp_ajax_bp_moderation_unblock_user', 'bp_moderation_unblock_user' );
add_action( 'wp_ajax_nopriv_bp_moderation_unblock_user', 'bp_moderation_unblock_user' );

/**
 * Function to handle moderation request from frontend
 *
 * @since BuddyBoss 2.0.0
 */
function bp_moderation_content_actions_request() {
	$response = array(
		'success' => false,
		'message' => '',
	);

	$nonce      = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
	$type       = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_STRING );
	$sub_action = filter_input( INPUT_POST, 'sub_action', FILTER_SANITIZE_STRING );
	$id         = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );

	if ( wp_verify_nonce( $nonce, 'bp-hide-unhide-moderation' ) ) {
		$action = bp_moderation_hide_unhide_request( $id, $type, $sub_action );
		if ( true === $action ) {
			$response['success'] = true;
			if ( 'user' === $type ) {
				$response['message'] = esc_html__( 'Member has been successfully suspended.', 'buddyboss' );
			} else {
				$response['message'] = esc_html__( 'Content has been successfully hidden.', 'buddyboss' );
			}
		}
	}

	if ( empty( $response['success'] ) && empty( $response['message'] ) ) {
		$response['message'] = new WP_Error( 'bp_moderation_content_actions_request', esc_html__( 'Sorry, Something happened wrong', 'buddyboss' ) );
	}

	echo wp_json_encode( $response );
	exit();
}

add_action( 'wp_ajax_bp_moderation_content_actions_request', 'bp_moderation_content_actions_request' );
add_action( 'wp_ajax_nopriv_bp_moderation_content_actions_request', 'bp_moderation_content_actions_request' );

/**
 * Function to Popup markup for moderation content report
 *
 * @since BuddyBoss 2.0.0
 */
function bb_moderation_content_report_popup() {
	include BP_PLUGIN_DIR . 'src/bp-moderation/screens/content-report-form.php';
	include BP_PLUGIN_DIR . 'src/bp-moderation/screens/block-member-form.php';
}

add_action( 'wp_footer', 'bb_moderation_content_report_popup' );

/**
 * Function to add the content in member block form.
 *
 * @since BuddyBoss 2.0.0
 */
function bp_moderation_block_member_form_content() {
	?>
    <span>
        <?php
        esc_html_e( 'This member will no longer be able to:', 'buddyboss' );
        ?>
    </span>
    <ul>
        <li>
			<?php
			esc_html_e( 'See your post', 'buddyboss' );
			?>
        </li>
        <li>
			<?php
			esc_html_e( 'Tag you', 'buddyboss' );
			?>
        </li>
        <li>
			<?php
			esc_html_e( 'Invite you in event and groups', 'buddyboss' );
			?>
        </li>
        <li>
			<?php
			esc_html_e( 'Message you', 'buddyboss' );
			?>
        </li>
        <li>
			<?php
			esc_html_e( 'Add you as a friend', 'buddyboss' );
			?>
        </li>
    </ul>
    <span>
        <?php
        esc_html_e( 'If you\'re friends, blocking member will also unfriend them', 'buddyboss' );
        ?>
    </span>
	<?php
}

add_action( 'bp_moderation_block_member_before_form', 'bp_moderation_block_member_form_content' );