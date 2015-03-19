<?php
/**
 * WordPress Revisional Metabox
 * 
 * @package Revisional_Metabox
 * @version 1.0.0
 * @author Alexander O'Mara
 * @copyright Copyright (c) 2015 Alexander O'Mara
 * @license MPL 2.0 http://mozilla.org/MPL/2.0/
 */

if ( ! class_exists( 'Revisional_Metabox' ) ) :

/**
 * Extend this class for each metabox.
 * The parent constructor must be called from the child constructor.
 * Properties meta_key, metabox_title, and metabox_title must be set in the child class constructor.
 * Properties metabox_context, metabox_priority, metabox_id_prefix, revision_id_prefix, metabox_nonce_suffix, and revisional can also optionally be configured.
 * Methods render_content and get_submitted_data must be overridden.
 * Method diff_content can be overridden to show revisions on the revision screen.
 * 
 * @package Revisional_Metabox
 * @subpackage API
 * @license MPL 2.0 http://mozilla.org/MPL/2.0/
 */
class Revisional_Metabox {
	
	/**
	 * A unique meta key used for storing the meta on a post.
	 * Must be set by child class.
	 * 
	 * @var string Meta key.
	 */
	protected $meta_key = null;
	
	/**
	 * Title for the metabox.
	 * Must be set by child class.
	 * 
	 * @var string Metabox title.
	 */
	protected $metabox_title = null;
	
	/**
	 * The post types the metabox is used for.
	 * Must be set by child class.
	 * 
	 * @var string|array List of post type.
	 */
	protected $metabox_post_types = null;
	
	/**
	 * The context of the metabox.
	 * 
	 * @var string Metabox context.
	 */
	protected $metabox_context = 'advanced';
	
	/**
	 * The priority of the metabox.
	 * 
	 * @var string Metabox priority.
	 */
	protected $metabox_priority = 'default';
	
	/**
	 * A prefix added to the metabox id slug.
	 * 
	 * @var string A prefix for the id slug.
	 */
	protected $metabox_id_prefix = 'metabox-';
	
	/**
	 * A prefix added to the revision id key.
	 * 
	 * @var string A prefix for the revision id.
	 */
	protected $revision_id_prefix = 'meta-';
	
	/**
	 * A suffix added to the nonce.
	 * 
	 * @var string Nonce suffix.
	 */
	protected $metabox_nonce_suffix = '-nonce';
	
	/**
	 * An option to disabled revisions for this instance.
	 * 
	 * @var bool Set to false to disable revisions, default true.
	 */
	protected $revisional = true;
	
	/**
	 * Set when a new revision is created for later use.
	 * 
	 * @var int|null Post ID or null.
	 */
	protected $new_revision = null;
	
	/**
	 * The order in which the the diff screen contexts will be printed.
	 * 
	 * @var array
	 */
	protected static $diff_order_context = array( 'normal', 'side', 'advanced' );
	
	/**
	 * The order in which the the diff screen priorities will be printed.
	 * 
	 * @var array
	 */
	protected static $diff_order_priority = array( 'high', 'core', 'default', 'low' );
	
	/**
	 * The internally managed list of revision diff entries.
	 * 
	 * @var array|null
	 */
	protected static $diff_managed = null;
	
	/**
	 * Version number.
	 * 
	 * @var string
	 */
	const VERSION = '1.0.0';
	
	/**
	 * Main constructor, initializes the action and filter hooks.
	 * parent::__construct() must be called from child class constructors.
	 * Child classes should also set the necessary properties in their constructor function.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes',                         array( $this, 'action_add_meta_boxes'                         ), 10, 2 );
		add_action( 'save_post',                              array( $this, 'action_save_post'                              ), 10, 3 );
		add_action( 'wp_restore_post_revision',               array( $this, 'action_wp_restore_post_revision'               ), 10, 2 );
		add_filter( 'wp_save_post_revision_post_has_changed', array( $this, 'filter_wp_save_post_revision_post_has_changed' ), 10, 3 );
		add_filter( 'wp_get_revision_ui_diff',                array( $this, 'filter_wp_get_revision_ui_diff_pre'            ),  9, 3 );
		add_filter( 'wp_get_revision_ui_diff',                array( $this, 'filter_wp_get_revision_ui_diff'                ), 10, 3 );
	}
	
	/**
	 * add_meta_boxes action callback.
	 * 
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post object.
	 */
	public function action_add_meta_boxes( $post_type, $post ) {
		$post_types = (array) $this->metabox_post_types;
		foreach ( $post_types as &$post_type ) {
			add_meta_box(
				$this->metabox_id_prefix . $this->meta_key,
				$this->metabox_title,
				array( $this, 'callback_metabox' ),
				$post_type,
				$this->metabox_context,
				$this->metabox_priority
			);
		}
		unset( $post_type );
	}
	
	/**
	 * add_meta_box callback function.
	 * 
	 * @param mixed $object Object passed to do_meta_boxes.
	 * @param array $box    Associative array of data from add_meta_box.
	 */
	public function callback_metabox( $object, $box ) {
		$this->nonce_field();
		$this->render_content($object, $box);
	}
	
	/**
	 * save_post action callback.
	 * 
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function action_save_post( $post_id, $post, $update ) {
		//Check if autosaving and validate permissions.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		//Check if a revision, and if it is, save the revision id.
		$parent_id = wp_is_post_revision( $post_id );
		if ( $parent_id ) {
			$this->new_revision = $post_id;
		}
		//Check if the data was submitted, skip saving to revision if revisions are disabled.
		if ( $this->nonce_verify() && ( ! $parent_id || $this->revisional ) ) {
			//User submitted the data, set the data on any newly created revision and the main post.
			$this->set_metadata( $post_id, $this->get_submitted_data() );
		}
	}
	
	/**
	 * wp_restore_post_revision action callback.
	 * 
	 * @param int $post_id     Post ID.
	 * @param int $revision_id Post revision ID.
	 */
	public function action_wp_restore_post_revision( $post_id, $revision_id ) {
		//If revisions are enabled, restore metadata.
		if ( $this->revisional ) {
			$post = get_post( $post_id );
			//Check if this post type has this meta.
			if ( $post && in_array( $post->post_type, (array) $this->metabox_post_types ) ) {
				//Get the metadata to restore to.
				$metadata = $this->get_metadata( $revision_id );
				//Set the metadata on the new revision if one was created.
				if ( $this->new_revision ) {
					$this->set_metadata( $this->new_revision, $metadata );
				}
				//Set the metadata on the main post.
				$this->set_metadata( $post_id, $metadata );
			}
		}
	}
	
	/**
	 * wp_save_post_revision_post_has_changed filter callback.
	 * 
	 * @param bool    $post_has_changed Whether the post has changed.
	 * @param WP_Post $last_revision    The last revision post object.
	 * @param WP_Post $post             The post object.
	 * 
	 * @return bool Returns true if the post has changed, false if it has not.
	 */
	public function filter_wp_save_post_revision_post_has_changed( $post_has_changed, $last_revision, $post ) {
		//If revisions are enabled, check if post type may need to trigger a revision.
		if ( $this->revisional && ! $post_has_changed && in_array( $post->post_type, (array) $this->metabox_post_types ) ) {
			//Check if the data was submitted or a revision is being reverted.
			if ( $this->nonce_verify() ) {
				//User submitted, get the current metadata.
				$current_metadata = $this->get_submitted_data();
				//Get the previous metadata.
				$previous_metadata = $this->get_metadata( $last_revision->ID );
				//Compare the metadata values.
				if ( $this->compare_metadata( $current_metadata, $previous_metadata ) ) {
					$post_has_changed = true;
				}
			}
			else {
				//A revision is being reverted, always create a new revision.
				$post_has_changed = true;
			}
		}
		return $post_has_changed;
	}
	
	/**
	 * wp_get_revision_ui_diff filter callback.
	 * This callback depends on the other filter callback being run after this one is.
	 * 
	 * @param array         $return       Revision UI fields. Each item is an array of id, name and diff.
	 * @param WP_Post|false $compare_from The revision post to compare from, or false for the initial revision.
	 * @param WP_Post       $compare_to   The revision post to compare to.
	 * 
	 * @return array Revision fields.
	 */
	public function filter_wp_get_revision_ui_diff_pre( $return, $compare_from, $compare_to ) {
		//If revisions are enabled, diff metadata.
		if ( $this->revisional ) {
			//Load the meta for each post.
			$meta_from = $compare_from ? $this->get_metadata( $compare_from->ID ) : false;
			$meta_to = $this->get_metadata( $compare_to->ID );
			//Run the diff function.
			$diff = $this->diff_content( $meta_from, $meta_to );
			//If diff returned a string with content, show the diff.
			if ( $diff && is_string( $diff ) ) {
				//Initialize the managed list if necessary.
				if ( ! is_array( self::$diff_managed ) ) {
					self::$diff_managed = array();
				}
				//Insert into the internally sorted and managed list.
				self::$diff_managed[$this->metabox_context][$this->metabox_priority][] = array(
					'id'   => $this->revision_id_prefix . $this->meta_key,
					'name' => $this->metabox_title,
					'diff' => $diff
				);
			}
		}
		//Continue like nothing happened.
		return $return;
	}
	
	/**
	 * wp_get_revision_ui_diff filter callback.
	 * This callback depends on the other filter callback being run before this one is.
	 * 
	 * @param array         $return       Revision UI fields. Each item is an array of id, name and diff.
	 * @param WP_Post|false $compare_from The revision post to compare from, or false for the initial revision.
	 * @param WP_Post       $compare_to   The revision post to compare to.
	 * 
	 * @return array Revision fields.
	 */
	public function filter_wp_get_revision_ui_diff( $return, $compare_from, $compare_to ) {
		//On the first call, move the managed list into the main list.
		if ( is_array( self::$diff_managed ) ) {
			//Loop over the list orders, inserting the managed items all at once in order.
			foreach ( self::$diff_order_context as &$context ) {
				if ( isset( self::$diff_managed[$context] ) ) {
					foreach ( self::$diff_order_priority as &$priority ) {
						if ( isset( self::$diff_managed[$context][$priority] ) ) {
							foreach ( self::$diff_managed[$context][$priority] as &$diff ) {
								$return[] = $diff;
							}
							unset( $diff );
						}
					}
					unset( $priority );
				}
			}
			unset( $context );
			//Clear the managed list, so as not to insert again.
			self::$diff_managed = null;
		}
		//Send the list along.
		return $return;
	}
	
	/**
	 * Get the meta value from a post by id, even a revision.
	 * 
	 * @param unknown $post_id Post ID.
	 * 
	 * @return mixed Returns the meta value.
	 */
	public function get_metadata( $post_id ) {
		return get_metadata( 'post', $post_id, $this->meta_key, true );
	}
	
	/**
	 * Set the meta value for a post by id, even a revision.
	 * 
	 * @param unknown $post_id Post ID.
	 * @param mixed   $value   Meta value to set.
	 */
	public function set_metadata( $post_id, $value ) {
		update_metadata( 'post', $post_id, $this->meta_key, $value );
	}
	
	/**
	 * Create the nonce field used for the metabox.
	 */
	public function nonce_field() {
		wp_nonce_field( $this->nonce_action(), $this->nonce_name(), false );
	}
	
	/**
	 * Verify the nonce from the metabox
	 * 
	 * @return boolean Returns true if the nonce is valid, false if not valid or not present.
	 */
	public function nonce_verify() {
		$nonce_name = $this->nonce_name();
		$nonce = isset( $_REQUEST[$nonce_name] ) ? $_REQUEST[$nonce_name] : null;
		return $nonce ? (bool) wp_verify_nonce( $nonce, $this->nonce_action() ) : false;
	}
	
	/**
	 * Get the nonce name.
	 * 
	 * @return string The nonce name key.
	 */
	public function nonce_name() {
		return '_' . $this->metabox_id_prefix . $this->meta_key . $this->metabox_nonce_suffix;
	}
	
	/**
	 * Get the nonce action.
	 * 
	 * @return string The nonce action key.
	 */
	public function nonce_action() {
		return $this->meta_key . $this->metabox_nonce_suffix;
	}
	
	/**
	 * Compare the current and previous metadata to check if different.
	 * 
	 * @param mixed $current_metadata  The current metadata value.
	 * @param mixed $previous_metadata The previous metadata value.
	 * 
	 * @return bool Returns true if different, false if the same.
	 */
	public function compare_metadata( $current_metadata, $previous_metadata ) {
		return serialize( $current_metadata ) !== serialize( $previous_metadata );
	}
	
	/**
	 * Return the diff string or null.
	 * Must be overridden to return a non-blank string to display the diff on the revision screen.
	 * 
	 * @param mixed $meta_from The saved meta to compare from, false for initial revision.
	 * @param mixed $meta_to   The saved meta to compare to.
	 * 
	 * @return string|null Returns null by default.
	 */
	public function diff_content( $meta_from, $meta_to ) {
		return null;
	}
	
	/**
	 * Renders the metabox content.
	 * Must be overridden by the child class.
	 * 
	 * @param mixed $object Object passed to do_meta_boxes.
	 * @param array $box    Associative array of data from add_meta_box.
	 * 
	 * @throws Exception
	 */
	public function render_content( $object, $box ) {
		throw new Exception( __METHOD__ . ' must be overridden by the child class.' );
	}
	
	/**
	 * Retrieves and validates the metabox submitted content.
	 * Must be overridden by the child class.
	 * 
	 * @throws Exception
	 */
	public function get_submitted_data() {
		throw new Exception( __METHOD__ . ' must be overridden by the child class.' );
	}
}

endif;
