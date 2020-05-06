<?php
/**
 * BP REST: BP_REST_Activity_Details_Endpoint class
 *
 * @package BuddyPress
 * @since 1.3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity endpoints.
 *
 * @since 1.3.5
 */
class BP_REST_Activity_Details_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 1.3.5
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->activity->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 1.3.5
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/details',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}


	/**
	 * Retrieve activities details.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 1.3.5
	 *
	 * @api {GET} /wp-json/buddyboss/v1/activity/details Activities Details
	 * @apiName GetBBActivitiesDetails
	 * @apiGroup Activity
	 * @apiDescription Retrieve activities details(includes nav, filters and post_in)
	 * @apiVersion 1.0.0
	 */
	public function get_items( $request ) {

		$retval = array();

		$retval['nav']     = $this->get_activities_tabs();
		$retval['filters'] = $this->get_activities_filters();
		$retval['post_in'] = $this->get_activities_post_in();

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a list of activity details is fetched via the REST API.
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		do_action( 'bp_rest_activity_details_get_items', $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to activity items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return bool|WP_Error
	 * @since 1.3.5
	 */
	public function get_items_permissions_check( $request ) {
		$retval = true;

		if ( ! bp_is_active( 'activity' ) ) {
			$retval = new WP_Error(
				'bp_rest_component_required',
				__( 'Sorry, Activity component was not enabled.', 'buddyboss' ),
				array(
					'status' => '404',
				)
			);
		}

		/**
		 * Filter the activity details permissions check.
		 *
		 * @param bool|WP_Error $retval Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_details_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @return array
	 * @since 1.3.5
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_activity_details',
			'type'       => 'object',
			'properties' => array(
				'nav'     => array(
					'context'     => array( 'embed', 'view' ),
					'description' => __( 'Activity directory tabs.', 'buddyboss' ),
					'type'        => 'object',
					'readonly'    => true,
					'items'       => array(
						'type' => 'array',
					),
				),
				'filters' => array(
					'context'     => array( 'embed', 'view' ),
					'description' => __( 'Activity Filter options', 'buddyboss' ),
					'type'        => 'array',
					'readonly'    => true,
				),
				'post_in' => array(
					'context'     => array( 'embed', 'view' ),
					'description' => __( 'Activity contains from.', 'buddyboss' ),
					'type'        => 'array',
					'readonly'    => true,
				),
			),
		);

		/**
		 * Filters the activity details schema.
		 *
		 * @param string $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_activity_details_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get list of activity tabs.
	 *
	 * @return array
	 */
	public function get_activities_tabs() {
		$nav_items = bp_nouveau_get_activity_directory_nav_items();
		$nav       = array();

		if ( ! empty( $nav_items ) ) {
			foreach ( $nav_items as $key => $item ) {
				$nav[ $key ]['title']    = $item['text'];
				$nav[ $key ]['position'] = $item['position'];
				$nav[ $key ]['count']    = $this->get_activity_tab_count( $item['slug'] );
			}
		}

		return $nav;
	}

	/**
	 * Get Activity tab count.
	 *
	 * @param string $slug Component slug.
	 *
	 * @return int
	 */
	protected function get_activity_tab_count( $slug ) {
		$count   = 0;
		$user_id = get_current_user_id();

		switch ( $slug ) {
			case 'all':
				$count = bp_get_total_member_count();
				break;
			case 'favorites':
				$count = bp_get_total_favorite_count_for_user( $user_id );
				break;
			case 'friends':
				$count = bp_get_total_friend_count( $user_id );
				break;
			case 'groups':
				$count = bp_get_total_group_count_for_user( $user_id );
				break;
			case 'mentions':
				$count = (int) bp_get_user_meta( $user_id, 'bp_new_mention_count', true );
				break;
			case 'following':
				$count = $this->rest_bp_get_following_ids( array( 'user_id' => $user_id ) );
				break;
		}

		return $count;
	}

	/**
	 * Returns a comma separated list of user_ids for a given user's following.
	 *
	 * @param mixed $args Arguments can be passed as an associative array or as a URL argument string.
	 *
	 * @return mixed      Comma-seperated string of user IDs on success. Integer zero on failure.
	 */
	private function rest_bp_get_following_ids( $args ) {
		$following_ids;
		if ( bp_is_active( 'follow' ) ) {
			$following_ids = bp_get_following_ids( $args );
		} else {
			$following_ids = ( function_exists( 'bp_get_following' ) ? bp_get_following( $args ) : '' );
		}

		if ( ! empty( $following_ids ) ) {
			if ( is_array( $following_ids ) ) {
				return count( $following_ids );
			} else {
				return count( explode( ',', $following_ids ) );
			}
		}

		return 0;
	}

	/**
	 * Get list of filters supported for activity component.
	 *
	 * @return array
	 */
	public function get_activities_filters() {

		// BuddyPress Docs @https://wordpress.org/plugins/buddypress-docs/ .
		if ( function_exists( 'bp_docs_load_activity_filter_options' ) ) {
			bp_docs_load_activity_filter_options();
		}

		$filters = array( '-1' => __( '-- Everything --', 'buddyboss' ) ) + bp_nouveau_get_activity_filters();
		return $filters;
	}

	/**
	 * Get Activity Post in details.
	 *
	 * @return array
	 */
	public function get_activities_post_in() {
		$post_in    = array();
		$post_in[0] = __( 'My Profile', 'buddyboss' );

		if ( bp_is_active( 'groups' ) ) {
			$args   = array(
				'user_id' => get_current_user_id(),
				'type'    => 'alphabetical',
			);
			$groups = groups_get_groups( $args );

			if ( ! empty( $groups ) && ! empty( $groups['groups'] ) ) {
				foreach ( $groups['groups'] as $group ) {
					$post_in[ $group->id ] = $group->name;
				}
			}
		}

		return $post_in;
	}
}
