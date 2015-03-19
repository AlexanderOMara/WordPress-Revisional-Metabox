# WordPress Revisional Metabox

Revisional metaboxes in WordPress made easy.


## Overview

WordPress 2.6 introduced post revisions for the editor so that authors could save revisions of their content. While this feature has been great for storing revisions of the built-in post title, content, and excerpt fields, it was not very flexible for use by developers to store other data.

In WordPress 4.1, the necessary actions and filters were added to facilitate custom metabox revisions, so that developers can hook into this new functionality, but unfortunately the feature remains prohibitively difficult to use.

That is, unless you use this class to abstract away all the difficulties.


## Usage

1. Copy `revisional-metabox.php` to your project and `require_once` it.
2. Extend the class `Revisional_Metabox` for each metabox.
3. The parent constructor must be called from the child constructor.
4. Set the property `meta_key` to the unique meta key that the metadata will be stored under.
5. Set the property `metabox_title` to the title for the metabox. 
6. Set the property `metabox_post_types` to the list of post types this metabox will be used for.
7. Additionally, the following properties can optionally be configured.
  - `metabox_context` Set the metabox context.
  - `metabox_priority` Set the metabox priority.
  - `metabox_id_prefix` The prefix used in the metabox id attribute.
  - `revision_id_prefix` The id used for the revision on the revision screen.
  - `metabox_nonce_suffix` A suffix added to the input validating nonce.
  - `revisional` Can be used to disable revisions for the metabox.
8. Method `render_content` must be overridden output the metabox content.
  - Use the `get_metadata` method to get the current meta value.
9. Method `get_submitted_data` must be overridden to return the submitted form data parsed into the variable that will be stored.
10. Additionally, method `diff_content` can optionally be overridden to show revisions on the revision screen.
  - Must return a non-blank string to on the revision screen for a specific revision.
  - `wp_text_diff` with the `show_split_view` option is your friend.

See the PHP docstrings for full API documentation and the example code below for simple usage.


## Example WordPress Plugin

The following is an example WordPress plugin that utilizes this library for a revisional metabox. Be sure to copy the `revisional-metabox.php` file as per the `require_once` statement.

```php
<?php
/*
Plugin Name: Example Metabox Revisions
Description: Metabox Revisions example plugin.
Version:     1.0
Author:      Example
Plugin URI:  http://example.com
*/

require_once( __DIR__ . '/inc/revisional-metabox.php' );

class Example_Revisional_Metabox extends Revisional_Metabox {
	public function __construct() {
		parent::__construct();
		$this->meta_key = 'examplemeta';
		$this->metabox_title = __( 'Example Meta', 'embr' );
		$this->metabox_post_types = array( 'post' );
	}
	
	public function render_content( $object, $box ) {
		$meta = $this->get_metadata( $object->ID );
		?><input type="text" class="regular-text" name="<?php echo esc_attr( $this->meta_key ); ?>" value="<?php echo esc_attr( $meta ); ?>" /><?php
	}
	
	public function get_submitted_data() {
		return isset( $_REQUEST[$this->meta_key] ) ? stripslashes( $_REQUEST[$this->meta_key] ) : null;
	}
	
	public function diff_content( $meta_from, $meta_to ) {
		return wp_text_diff( $meta_from, $meta_to, array( 'show_split_view' => true ) );
	}
}
new Example_Revisional_Metabox();
```


## Considerations

The library is wrapped in an `if` `class_exists` block, but if you are still concerned with name and/or version collisions, feel free to add a prefix/suffix to the class name.

For displaying complex metadata on the revision screen, one option is to flatten it to a string, and pass it to `wp_text_diff`. Alternatively, you can create custom HTML with a similar structure.


## Compatibility

This library requires WordPress 4.1 or greater as it depends on API hooks that were added in this version.


## Bugs

If you find a bug or have compatibility issues, please open a ticket under issues section for this repository.


## License

See [LICENSE.txt](LICENSE.txt)

If this license does not work for you, feel free to contact me.


## Donations

If you find my software useful, please consider making a modest donation on my website at [alexomara.com](http://alexomara.com).
