<?php
/**
 * BuddyBoss Moderation Groups Classes
 *
 * @since   BuddyBoss 2.0.0
 * @package BuddyBoss\Moderation
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Database interaction class for the BuddyBoss moderation Groups.
 *
 * @since BuddyBoss 2.0.0
 */
class BP_Moderation_Groups extends BP_Moderation_Abstract {

	/**
	 * Item type
	 *
	 * @var string
	 */
	public static $moderation_type = 'groups';

	/**
	 * BP_Moderation_Group constructor.
	 *
	 * @since BuddyBoss 2.0.0
	 */
	public function __construct() {
		parent::$moderation[ self::$moderation_type ] = self::class;
		$this->item_type                              = self::$moderation_type;

		add_filter( 'bp_moderation_content_types', array( $this, 'add_content_types' ) );

		// Delete group moderation data when group is deleted.
		add_action( 'groups_delete_group', array( $this, 'sync_moderation_data_on_delete' ), 10 );

		/**
		 * Moderation code should not add for WordPress backend or IF Bypass argument passed for admin
		 */
		if ( ( is_admin() && ! wp_doing_ajax() ) || self::admin_bypass_check() ) {
			return;
		}

		/**
		 * If moderation setting enabled for this content then it'll filter hidden content.
		 * And IF moderation setting enabled for member then it'll filter blocked user content.
		 */
		add_filter( 'bp_suspend_group_get_where_conditions', array( $this, 'update_where_sql' ), 10, 2 );
		add_filter( 'bp_groups_group_pre_validate', array( $this, 'restrict_single_item' ), 10, 3 );

		// Code after below condition should not execute if moderation setting for this content disabled.
		if ( ! bp_is_moderation_content_reporting_enable( 0, self::$moderation_type ) ) {
			return;
		}
	}

	/**
	 * Get permalink
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param int $group_id group id.
	 *
	 * @return string
	 */
	public static function get_permalink( $group_id ) {
		$group = new BP_Groups_Group( $group_id );

		$url = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $group->slug . '/' );

		return add_query_arg( array( 'modbypass' => 1 ), $url );
	}

	/**
	 * Get Content owner id.
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param integer $group_id Group id.
	 *
	 * @return int
	 */
	public static function get_content_owner_id( $group_id ) {
		$group = new BP_Groups_Group( $group_id );

		return ( ! empty( $group->creator_id ) ) ? $group->creator_id : 0;
	}

	/**
	 * Add Moderation content type.
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param array $content_types Supported Contents types.
	 *
	 * @return mixed
	 */
	public function add_content_types( $content_types ) {
		$content_types[ self::$moderation_type ] = __( 'Group', 'buddyboss' );

		return $content_types;
	}

	/**
	 * Function to delete group moderation data when actual group is deleted
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param int $group_id group if.
	 */
	public function sync_moderation_data_on_delete( $group_id ) {
		if ( ! empty( $group_id ) ) {
			$moderation_obj = new BP_Moderation( $group_id, self::$moderation_type );
			if ( ! empty( $moderation_obj->id ) ) {
				$moderation_obj->delete( true );
			}
		}
	}

	/**
	 * Update where query remove hidden/blocked user's groups
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param string $where groups Where sql.
	 * @param object $suspend suspend object.
	 *
	 * @return array
	 */
	public function update_where_sql( $where, $suspend ) {
		$this->alias = $suspend->alias;

		$sql = $this->exclude_where_query();
		if ( ! empty( $sql ) ) {
			$where['moderation_where'] = $sql;
		}

		return $where;
	}

	/**
	 * Validate the group is valid or not.
	 *
	 * @param boolean $restrict Check the item is valid or not.
	 * @param object  $group    Current group object.
	 *
	 * @return false
	 */
	public function restrict_single_item( $restrict, $group ) {

		if ( bp_moderation_is_content_hidden( (int) $group->id, self::$moderation_type ) ) {
			return false;
		}

		return $restrict;
	}
}