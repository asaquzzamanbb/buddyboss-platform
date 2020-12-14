<?php
/**
 * BuddyBoss Suspend Forum Classes
 *
 * @since   BuddyBoss 2.0.0
 * @package BuddyBoss\Suspend
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Database interaction class for the BuddyBoss Suspend Forum.
 *
 * @since BuddyBoss 2.0.0
 */
class BP_Suspend_Forum extends BP_Suspend_Abstract {

	/**
	 * Item type
	 *
	 * @var string
	 */
	public static $type = 'forum';

	/**
	 * BP_Suspend_Forum constructor.
	 *
	 * @since BuddyBoss 2.0.0
	 */
	public function __construct() {

		$this->item_type = self::$type;

		// Manage hidden list.
		add_action( "bp_suspend_hide_{$this->item_type}", array( $this, 'manage_hidden_forum' ), 10, 3 );
		add_action( "bp_suspend_unhide_{$this->item_type}", array( $this, 'manage_unhidden_forum' ), 10, 4 );

		$forum_post_type = bbp_get_forum_post_type();
		add_action( "save_post_{$forum_post_type}", array( $this, 'update_forum_after_save' ), 10, 2 );

		/**
		 * Suspend code should not add for WordPress backend or IF component is not active or Bypass argument passed for admin
		 */
		if ( ( is_admin() && ! wp_doing_ajax() ) || self::admin_bypass_check() ) {
			return;
		}

		$this->alias = $this->alias . 'f'; // f = Forum.

		add_filter( 'posts_join', array( $this, 'update_join_sql' ), 10, 2 );
		add_filter( 'posts_where', array( $this, 'update_where_sql' ), 10, 2 );

		add_filter( 'bp_forums_search_join_sql', array( $this, 'update_join_sql' ), 10 );
		add_filter( 'bp_forums_search_where_sql', array( $this, 'update_where_sql' ), 10, 2 );

		add_filter( 'bbp_forums_forum_pre_validate', array( $this, 'restrict_single_item' ), 10, 2 );
	}

	/**
	 * Get Blocked member's forum ids
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param int $member_id Member id.
	 *
	 * @return array
	 */
	public static function get_member_forum_ids( $member_id ) {
		$forum_ids = array();

		$forum_query = new WP_Query(
			array(
				'fields'                 => 'ids',
				'post_type'              => bbp_get_forum_post_type(),
				'post_status'            => 'publish',
				'author'                 => $member_id,
				'posts_per_page'         => - 1,
				// Need to get all topics id of hidden forums.
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);

		if ( $forum_query->have_posts() ) {
			$forum_ids = $forum_query->posts;
		}

		return $forum_ids;
	}

	/**
	 * Prepare forum Join SQL query to filter blocked Forum
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param string $join_sql Forum Join sql.
	 * @param object $wp_query WP_Query object.
	 *
	 * @return string Join sql
	 */
	public function update_join_sql( $join_sql, $wp_query = null ) {
		global $wpdb;

		$action_name = current_filter();

		if ( 'bp_forums_search_join_sql' === $action_name ) {
			$join_sql .= $this->exclude_joint_query( 'p.ID' );

			/**
			 * Filters the hidden forum Where SQL statement.
			 *
			 * @since BuddyBoss 2.0.0
			 *
			 * @param array $join_sql Join sql query
			 * @param array $class    current class object.
			 */
			$join_sql = apply_filters( 'bp_suspend_forum_get_join', $join_sql, $this );

		} else {
			$forum_slug = bbp_get_forum_post_type();
			$post_types = wp_parse_slug_list( $wp_query->get( 'post_type' ) );
			if ( false === $wp_query->get( 'moderation_query' ) || ! empty( $post_types ) && in_array( $forum_slug, $post_types, true ) ) {
				$join_sql .= $this->exclude_joint_query( "{$wpdb->posts}.ID" );

				/**
				 * Filters the hidden forum Where SQL statement.
				 *
				 * @since BuddyBoss 2.0.0
				 *
				 * @param array $join_sql Join sql query
				 * @param array $class    current class object.
				 */
				$join_sql = apply_filters( 'bp_suspend_forum_get_join', $join_sql, $this );
			}
		}

		return $join_sql;
	}

	/**
	 * Prepare forum Where SQL query to filter blocked Forum
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param array       $where_conditions Forum Where sql.
	 * @param object|null $wp_query         WP_Query object.
	 *
	 * @return mixed Where SQL
	 */
	public function update_where_sql( $where_conditions, $wp_query = null ) {
		$action_name = current_filter();

		if ( 'bp_forums_search_where_sql' !== $action_name ) {
			$forum_slug = bbp_get_forum_post_type();
			$post_types = wp_parse_slug_list( $wp_query->get( 'post_type' ) );
			if ( false === $wp_query->get( 'moderation_query' ) || empty( $post_types ) || ! in_array( $forum_slug, $post_types, true ) ) {
				return $where_conditions;
			}
		}

		$where                  = array();
		$where['suspend_where'] = $this->exclude_where_query();

		/**
		 * Filters the hidden forum Where SQL statement.
		 *
		 * @since BuddyBoss 2.0.0
		 *
		 * @param array $where Query to hide suspended user's forum.
		 * @param array $class current class object.
		 */
		$where = apply_filters( 'bp_suspend_forum_get_where_conditions', $where, $this );

		if ( ! empty( array_filter( $where ) ) ) {
			if ( 'bp_forums_search_where_sql' === $action_name ) {
				$where_conditions['suspend_where'] = '( ' . implode( ' AND ', $where ) . ' )';
			} else {
				$where_conditions .= ' AND ( ' . implode( ' AND ', $where ) . ' )';
			}
		}

		return $where_conditions;
	}

	/**
	 * Restrict Single item
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param boolean $restrict Check the item is valid or not.
	 * @param object  $post     Current forum object.
	 *
	 * @return false
	 */
	public function restrict_single_item( $restrict, $post ) {

		if ( BP_Core_Suspend::check_suspended_content( (int) $post->id, self::$type ) ) {
			return false;
		}

		return $restrict;
	}

	/**
	 * Hide related content of forum
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param int      $forum_id      forum id.
	 * @param int|null $hide_sitewide item hidden sitewide or user specific.
	 * @param array    $args          parent args.
	 */
	public function manage_hidden_forum( $forum_id, $hide_sitewide, $args = array() ) {
		global $bp_background_updater;

		$suspend_args = wp_parse_args(
			$args,
			array(
				'item_id'   => $forum_id,
				'item_type' => self::$type,
			)
		);

		if ( ! is_null( $hide_sitewide ) ) {
			$suspend_args['hide_sitewide'] = $hide_sitewide;
		}

		BP_Core_Suspend::add_suspend( $suspend_args );

		if ( $this->backgroup_diabled || ! empty( $args ) ) {
			$this->hide_related_content( $forum_id, $hide_sitewide, $args );
		} else {
			$bp_background_updater->push_to_queue(
				array(
					'callback' => array( $this, 'hide_related_content' ),
					'args'     => array( $forum_id, $hide_sitewide, $args ),
				)
			);
			$bp_background_updater->save()->dispatch();
		}
	}

	/**
	 * Un-hide related content of forum
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param int      $forum_id forum id.
	 * @param int|null $hide_sitewide item hidden sitewide or user specific.
	 * @param int      $force_all     un-hide for all users.
	 * @param array    $args          parent args.
	 */
	public function manage_unhidden_forum( $forum_id, $hide_sitewide, $force_all, $args = array() ) {
		global $bp_background_updater;

		$suspend_args = wp_parse_args(
			$args,
			array(
				'item_id'   => $forum_id,
				'item_type' => self::$type,
			)
		);

		if ( ! is_null( $hide_sitewide ) ) {
			$suspend_args['hide_sitewide'] = $hide_sitewide;
		}

		BP_Core_Suspend::remove_suspend( $suspend_args );

		if ( $this->backgroup_diabled || ! empty( $args ) ) {
			$this->unhide_related_content( $forum_id, $hide_sitewide, $force_all, $args );
		} else {
			$bp_background_updater->push_to_queue(
				array(
					'callback' => array( $this, 'unhide_related_content' ),
					'args'     => array( $forum_id, $hide_sitewide, $force_all, $args ),
				)
			);
			$bp_background_updater->save()->dispatch();
		}
	}

	/**
	 * Get Activity's comment ids
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param int   $forum_id forum id.
	 * @param array $args     parent args.
	 *
	 * @return array
	 */
	protected function get_related_contents( $forum_id, $args = array() ) {
		$related_contents = array();

		if ( bp_is_active( 'activity' ) ) {
			$related_contents[ BP_Suspend_Activity::$type ] = BP_Suspend_Activity::get_bbpress_activity_ids( $forum_id );
		}

		if ( bp_is_active( 'forums' ) ) {
			$related_contents[ BP_Suspend_Forum_Topic::$type ] = BP_Suspend_Forum_Topic::get_forum_topics_ids( $forum_id );
		}

		return $related_contents;
	}

	/**
	 * Update the suspend table to add new entries.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function update_forum_after_save( $post_id, $post ) {

		if ( empty( $post_id ) || empty( $post->ID ) ) {
			return;
		}

		$sub_items     = bp_moderation_get_sub_items( $post_id, BP_Moderation_Forums::$moderation_type );
		$item_sub_id   = isset( $sub_items['id'] ) ? $sub_items['id'] : $post_id;
		$item_sub_type = isset( $sub_items['type'] ) ? $sub_items['type'] : BP_Moderation_Forums::$moderation_type;

		$suspended_record = BP_Core_Suspend::get_recode( $item_sub_id, $item_sub_type );

		if ( empty( $suspended_record ) ) {
			$suspended_record = BP_Core_Suspend::get_recode( $post->post_author, BP_Moderation_Members::$moderation_type );
		}

		if ( empty( $suspended_record ) ) {
			return;
		}

		self::handle_new_suspend_entry( $suspended_record, $post_id, $post->post_author );
	}
}