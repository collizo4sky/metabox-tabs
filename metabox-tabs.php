<?php

/**
 * Plugin Name: MetaBox Tabs
 * Plugin URI: https://github.com/AchalJ/metabox-tabs
 * Description: MetaBox Tabs
 * Version: 1.0
 * Author: Achal Jain
 * Author URI: https://github.com/AchalJ/
 * Copyright: (c) 2016 Achal Jain
 * License: GNU General Public License v2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! class_exists( 'MetaBox_Tabs', false ) ) {

    class MetaBox_Tabs {

        const VERSION = '1.0';

        /**
    	 * Current post_id.
    	 *
    	 * @since 1.0
    	 * @var $post_id int
    	 */
        static public $post_id;

        /**
    	 * Metabox arguments.
    	 *
    	 * @since 1.0
    	 * @var $args array
    	 */
        static private $args = array();

        static private $object_types = array();

        static private $fields_prefix = '';

        static public $url = '';

        /**
    	 * Default values of metabox.
    	 *
    	 * @since 1.0
    	 * @var $defaults array
    	 */
        static private $defaults = array(
            'id'            => '',
            'title'         => '',
            'object_types'  => array(),
            'context'       => 'normal',
            'priority'      => 'low',
            'show_header'   => true,
            'fields_prefix' => ''
        );

        /**
    	 * Initialize hooks and filters.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function init()
        {
            define( 'MBT_DIR', self::get_dir() );
            define( 'MBT_URL', self::get_url() );

            require_once MBT_DIR . 'classes/class-mbt-location-rules.php';
            require_once MBT_DIR . 'classes/class-mbt-helper.php';
            require_once MBT_DIR . 'classes/class-mbt-ajax.php';

            add_action( 'admin_init', __CLASS__ .'::init_hooks' );
        }

        static public function init_hooks()
        {
            if ( empty( self::$args ) ) {
                return;
            }

            add_action( 'admin_enqueue_scripts',    __CLASS__ . '::enqueue_scripts', 15 );
            add_action( 'admin_head',               __CLASS__ . '::inline_styles' );
            add_action( 'save_post',                __CLASS__ . '::save_metabox' );
        }

        /**
    	 * Get the directory.
    	 *
    	 * @since 1.0
    	 * @return string
    	 */
        static public function get_dir()
        {
            return trailingslashit( dirname( __FILE__ ) );
        }

        /**
    	 * Get URL of the directory.
    	 *
    	 * @since 1.0
    	 * @return string
    	 */
        static public function get_url()
        {
            if ( self::$url ) {
    			return self::$url;
    		}

            self::$url = self::get_url_from_dir( self::get_dir() );

            return self::$url;
        }

        /**
    	 * Converts a system path to a URL
    	 *
    	 * @since 1.0
    	 * @param  string $dir Directory path to convert.
    	 * @return string      Converted URL.
    	 */
        static protected function get_url_from_dir( $dir )
        {
            $dir = self::normalize_path( $dir );

            // Let's test if We are in the plugins or mu-plugins dir.
    		$test_dir = trailingslashit( $dir ) . 'unneeded.php';
    		if (
    			0 === strpos( $test_dir, self::normalize_path( WPMU_PLUGIN_DIR ) )
    			|| 0 === strpos( $test_dir, self::normalize_path( WP_PLUGIN_DIR ) )
    		) {
    			// Ok, then use plugins_url, as it is more reliable.
    			return trailingslashit( plugins_url( '', $test_dir ) );
    		}

            // Ok, now let's test if we are in the theme dir.
    		$theme_root = self::normalize_path( get_theme_root() );
    		if ( 0 === strpos( $dir, $theme_root ) ) {
    			// Ok, then use get_theme_root_uri.
    			return set_url_scheme(
    				trailingslashit(
    					str_replace(
    						untrailingslashit( $theme_root ),
    						untrailingslashit( get_theme_root_uri() ),
    						$dir
    					)
    				)
    			);
    		}
    		// Check to see if it's anywhere in the root directory
    		$site_dir = self::normalize_path( self::$ABSPATH );
    		$site_url = trailingslashit( is_multisite() ? network_site_url() : site_url() );
    		$url = str_replace(
    			array( $site_dir, WP_PLUGIN_DIR ),
    			array( $site_url, WP_PLUGIN_URL ),
    			$dir
    		);

    		return set_url_scheme( $url );
        }

        /**
    	 * `wp_normalize_path` wrapper for back-compat. Normalize a filesystem path.
    	 *
    	 * On windows systems, replaces backslashes with forward slashes
    	 * and forces upper-case drive letters.
    	 * Allows for two leading slashes for Windows network shares, but
    	 * ensures that all other duplicate slashes are reduced to a single.
    	 *
    	 * @since 1.0
    	 * @param string $path Path to normalize.
    	 * @return string Normalized path.
    	 */
    	static protected function normalize_path( $path ) {
    		if ( function_exists( 'wp_normalize_path' ) ) {
    			return wp_normalize_path( $path );
    		}
    		// Replace newer WP's version of wp_normalize_path.
    		$path = str_replace( '\\', '/', $path );
    		$path = preg_replace( '|(?<=.)/+|', '/', $path );
    		if ( ':' === substr( $path, 1, 1 ) ) {
    			$path = ucfirst( $path );
    		}
    		return $path;
    	}

        /**
    	 * Enqueue styles and scripts for metabox and fields.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function enqueue_scripts( $hook )
        {
            global $post_type;

            $object_types = self::$object_types;

            if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
                if ( in_array( $post_type, $object_types ) ) {
                    wp_enqueue_style( 'wp-color-picker' );
                    wp_enqueue_script( 'wp-color-picker' );
                    //wp_enqueue_style( 'select2-style', 'assets/vendor/select2/select2.css', array() );
            		//wp_enqueue_script( 'select2-script', 'assets/vendor/select2/select2.full.js', array('jquery') );
                    wp_enqueue_style( 'mbt-metabox-style', MBT_URL . 'assets/css/meta.css', array() );
                    wp_enqueue_script( 'mbt-metabox-script', MBT_URL . 'assets/js/meta.js', array('jquery'), true );
                }
            }
        }

        /**
    	 * Metabox inline styles.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function inline_styles()
        {
            global $post_type;

            $metabox_id     = self::$args['id'];
            $show_header    = self::$args['show_header'];
            $object_types   = self::$object_types;
            ?>

            <?php if ( in_array( $post_type, $object_types ) ) { ?>
                <style id="mbt-metabox-style">
                <?php if ( ! $show_header ) { ?>
                    <?php echo '#' . $metabox_id; ?> .hndle,
                    <?php echo '#' . $metabox_id; ?> .handlediv {
                        display: none !important;
                    }
                <?php } ?>
                    <?php echo '#' . $metabox_id; ?> .inside {
                        padding: 0;
                        margin: 0;
                    }
                </style>
            <?php }
        }

        static public function get_layout()
        {
            $layout = 'horizontal';

            if ( isset( self::$args['layout'] ) && 'vertical' == self::$args['layout'] ) {
                $layout = 'vertical';
            }

            return $layout;
        }

        /**
    	 * Triggers a hook to register metabox.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function add_meta_box( $args )
        {
            self::$args             = wp_parse_args( $args, self::$defaults );
            self::$object_types     = (array)self::$args['object_types'];
            self::$fields_prefix    = isset( self::$args['fields_prefix'] ) ? self::$args['fields_prefix'] : '';

            add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );
        }

        /**
    	 * Registers a metabox.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function add_meta_boxes()
        {
            add_meta_box( self::$args['id'], self::$args['title'], __CLASS__ . '::render_metabox', self::$object_types, self::$args['context'], self::$args['priority'] );
        }

        /**
    	 * Render metabox.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function render_metabox( $post )
        {
            self::$post_id = $post->ID;

            $tabs       = self::$args['config'];
            $prefix     = self::$fields_prefix;
            $metabox_id = self::$args['id'];
            $layout     = self::get_layout();

            wp_nonce_field( self::$args['id'], self::$args['id'] . '_nonce' );

            include self::get_dir() . 'includes/metabox.php';
        }

        /**
    	 * Renders a field in the current metabox.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function render_metabox_field( $name, $field, $value = '' )
        {
            if ( ! isset( $field['type'] ) || empty( $field['type'] ) ) {
                return;
            }

            $post_id    = self::$post_id;
            $prefix     = self::$fields_prefix;
            $id         = $prefix . $name;
            $default    = isset( $field['default'] ) ? $field['default'] : '';

            if ( empty( $value ) ) {
                if ( metadata_exists( 'post', $post_id, $id ) ) {
                    $value  = get_post_meta( $post_id, $id, true );
                } else {
                    $value  = $default;
                }
            }

            echo '<tr id="mbt-field-' . $name . '" class="mbt-field" data-type="' . $field['type'] . '">';
            include MBT_DIR . 'includes/field.php';
            echo '</tr>';
        }

        /**
    	 * Returns an array of fields in a metabox.
    	 *
    	 * @since 1.0
    	 * @return array
    	 */
    	static public function get_metabox_fields()
    	{
    		$fields = array();
            $config = self::$args['config'];

    		foreach ( $config as $tab ) {
    			if ( isset( $tab['sections'] ) ) {
    				foreach ( $tab['sections'] as $section ) {
    					if ( isset( $section['fields'] ) ) {
    						foreach ( $section['fields'] as $name => $field ) {
    							$fields[ $name ] = $field;
    						}
    					}
    				}
    			}
    		}

    		return $fields;
    	}

        /**
    	 * Get metabox fields value.
    	 *
    	 * @since 1.0
    	 * @return object $settings
    	 */
        static public function get_metabox_settings( $post_id = '' )
        {
            $fields     = self::get_metabox_fields();
            $prefix     = self::$fields_prefix;
            $settings   = new stdClass();

            if ( empty( $post_id ) ) {
                global $post;
                $post_id = $post->ID;
            }

            foreach ( $fields as $name => $field ) {

                $field_id   = $prefix . $name;
                $default    = isset( $field['default'] ) ? $field['default'] : '';

                if ( metadata_exists( 'post', $post_id, $field_id ) ) {
                    $value  = get_post_meta( $post_id, $field_id, true );
                } else {
                    $value  = $default;
                }

                $settings->{$name} = $value;
            }

            $settings->post_id = $post_id;

            return $settings;
        }

        /**
    	 * Save metabox fields.
    	 *
    	 * @since 1.0
    	 * @return void
    	 */
        static public function save_metabox( $post_id )
        {
            $metabox_id     = self::$args['id'];
            $object_types   = self::$object_types;
            $prefix         = self::$fields_prefix;

            // Verify the nonce.
            if ( ! isset( $_POST[$metabox_id . '_nonce'] ) || ! wp_verify_nonce( $_POST[$metabox_id . '_nonce'], $metabox_id ) ) {
                return $post_id;
            }

            // Verify if this is an auto save routine.
            if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
                return $post_id;
            }

            // Check permissions to edit pages and/or posts
            if ( in_array( $_POST['post_type'], $object_types ) ) {
                if ( ! current_user_can( 'edit_page', $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
                    return $post_id;
                }
            }

            $fields = self::get_metabox_fields();

            foreach ( $fields as $name => $field ) {

                $field_id = $prefix . $name;
                $value = '';

                if ( isset( $_POST[$field_id] ) ) {
                    if ( isset( $field['sanitize_custom'] ) && ! empty( $field['sanitize_custom'] ) ) {
                        if ( function_exists( $field['sanitize_custom'] ) ) {
                            $value = call_user_func( $field['sanitize_custom'], $_POST[$field_id] );
                        }
                    }
                    else {
                        $value = self::sanitize_field( $field, $_POST[$field_id] );
                    }
                }
                else {
                    if ( 'checkbox' == $field['type'] ) {
                        $value = '0';
                    }
                }

                update_post_meta( $post_id, $field_id, $value );
            }
        }

        /**
    	 * Sanitize metabox fields.
    	 *
    	 * @since 1.0
    	 * @return mixed
    	 */
        static public function sanitize_field( $field, $value )
        {
            if ( isset( $field['sanitize'] ) && ! $field['sanitize'] ) {
                return $value;
            }

            switch ( $field['type'] ) {
                case 'text':
                    $value = sanitize_text_field( $value );
                break;
                case 'textarea':
                    $value = sanitize_textarea_field( $value );
                break;
                case 'email':
                    $value = sanitize_email( $value );
                break;
                default:
                break;
            }

            return $value;
        }
    }

    MetaBox_Tabs::init();

}
