<?php
/*
Plugin Name: Mabzy Check-in Button
Plugin URI: http://mabzy.com
Description: Adds a check-in button relative to each post.
Version: 1.2
Author: Sean Fisher
Author URI: http://sean-fisher.com/

Mabzy would like to thank TweetMeMe for the core code! :D
Release under GPLv2
*/

function ma_options() {
	add_menu_page('Mabzy', 'Mabzy', 8, basename(__FILE__), 'ma_options_page');
	add_submenu_page(basename(__FILE__), 'Settings', 'Settings', 8, basename(__FILE__), 'ma_options_page');
}

/**
* Build up all the params for the button
*/
function ma_build_options() {
	// get the post varibale (should be in the loop)
	global $post;
	// get the permalink
    if (get_post_status($post->ID) == 'publish') {
        $url = get_permalink();
    }
    $button = '?url=' . urlencode($url);

	// now build up the params, start with the source
    if (get_option('ma_source')) {
        $button .= '&amp;source=' . urlencode(get_option('ma_source'));
    }

	// which style
    if (get_option('ma_version') == 'compact') {
		$version = 'compact';
    } else {
		$version = 'regular';
	}

	// what shortner to use
	if (get_option('ma_url_shortner') && get_option('ma_url_shortner') != 'default') {
    	$button .= '&amp;service=' . urlencode(get_option('ma_url_shortner'));
	}

	// does the shortner have an API key
	if (get_option('ma_api_key')) {
		$button .= '&amp;service_api=' . urlencode(get_option('ma_api_key'));
	}

	// how many spaces do we want to leave at the end
	if (get_option('ma_space')) {
		$button .= '&amp;space=' . get_option('ma_space');
	}

	// append the hashtags
	if (get_option('ma_hashtags') == 'yes') {
		// first lets see if the post has the custom field
		if (($hashtags = get_post_meta($post->ID, 'ma_hashtags')) != false) {
			// first split them out
			$hashtags = explode(',', $hashtags[0]);
			// go through and urlencode
			foreach($hashtags as $row => $tag) {
				$hashtags[$row] = urlencode(trim($tag));
			}
			// nope so lets use them
			$button .= '&amp;hashtags=' . implode(',', $hashtags);
		} else if (($tags = get_the_tags()) != false) {
			// ok, grab them off the post tags
			$hashtags = array();
			foreach ($tags as $tag) {
				$hashtags[] = urlencode($tag->name);
			}
			$button .= '&amp;hashtags=' . implode(',', $hashtags);
		} else if (($hashtags = get_option('ma_hashtags_tags')) != false) {
			// first split them out
			$hashtags = explode(',', $hashtags);
			// go through and urlencode
			foreach($hashtags as $row => $tag) {
				$hashtags[$row] = urlencode(trim($tag));
			}
			// add them all back together
			$button .= '&amp;hashtags=' . implode(',', $hashtags);
		}
	}
	// return all the params
	return $button;
}

/**
* Generate the iFrame render of the button
*/
function ma_generate_button() {
	// build up the outer style
    $button = '<div class="mabzy_button" style="' . get_option('ma_style') . '">';
    $button .= '';
	
	if (get_post_status($post->ID) == 'publish')
        $url = get_permalink();
	$button .= '<script type="text/javascript">var mab_url = "'.$url.'";</script>';
	// give it a height, dependant on style
    if (get_option('ma_version') == 'compact') {
        $button .= '<script type="text/javascript" src="http://mabzy.com/compact_button.js">
				</script>';
    } else {
		  $button .= '<script type="text/javascript" src="http://mabzy.com/large_button.js"></script>';
	}
	// close off the iframe
	$button .= '</div>';
	// return the iframe code
    return $button;
}

/**
* Generates the image button
*/
function ma_generate_static_button() {
	return;
	/**
	if (get_post_status($post->ID) == 'publish') {
        $url = get_permalink();
		return
		'<div class="mabzy_button" style="' . get_option('ma_style') . '">
			<a href="http://api.mabzy.com/share?url=' . urlencode($url) . '">
				<img src="http://api.mabzy.com/imagebutton.gif' . ma_build_options() . '" height="61" width="50" />
			</a>
		</div>';
	} else {
		return;
	}
	**/
}

/**
* Gets run when the content is loaded in the loop
*/
function ma_update($content) {

    global $post;

    // add the manual option, code added by kovshenin
    if (get_option('ma_where') == 'manual') {
        return $content;
	}
    // is it a page
    if (get_option('ma_display_page') == null && is_page()) {
        return $content;
    }
	// are we on the front page
    if (get_option('ma_display_front') == null && is_home()) {
        return $content;
    }
	// are we in a feed
    if (is_feed()) {
		return;	//	Not available yet!
		$button = ma_generate_static_button();
		$where = 'ma_rss_where';
    } else {
		$button = ma_generate_button();
		$where = 'ma_where';
	}
	// are we displaying in a feed
	if (is_feed() && get_option('ma_display_rss') == null) {
		return $content;
	}

	// are we just using the shortcode
	if (get_option($where) == 'shortcode') {
		return str_replace('[mabzy]', $button, $content);
	} else {
		// if we have switched the button off
		if (get_post_meta($post->ID, 'mabzy') == null) {
			if (get_option($where) == 'beforeandafter') {
				// adding it before and after
				return $button . $content . $button;
			} else if (get_option($where) == 'before') {
				// just before
				return $button . $content;
			} else {
				// just after
				return $content . $button;
			}
		} else {
			// not at all
			return $content;
		}
	}
}

// Manual output
function mabzy() {
    if (get_option('ma_where') == 'manual') {
        return ma_generate_button();
    } else {
        return false;
    }
}

// Remove the filter excerpts
// Code added by Soccer Dad
function ma_remove_filter($content) {
	if (!is_feed()) {
    	remove_action('the_content', 'ma_update');
	}
    return $content;
}



/**
* Adds a mabzy-title meta title, provides a much more accurate title for the button
*/
function ma_head() {
	// if its a post page
	if (is_single()) {
		global $post;
		$title = get_the_title($post->ID);
		echo '<meta name="mabzy-title" content="' . strip_tags($title) . '" />';
	}
}

function ma_options_page() {
?>
    <div class="wrap">
    <div class="icon32" id="icon-options-general"><br/></div>
    <h2>Settings for Mabzy Check-in Button</h2>
    <p>This plugin will add a <a href="http://mabzy.com">Mabzy</a> Check-in Button to your blog posts as well as RSS feeds.
    </p>
    <form method="post" action="options.php">
    <?php
        // New way of setting the fields, for WP 2.7 and newer
        if(function_exists('settings_fields')){
            settings_fields('ma-options');
        } else {
            wp_nonce_field('update-options');
            ?>
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="ma_where,ma_style,ma_version,ma_display_page,ma_display_front,ma_display_rss,ma_display_feed,ma_source,ma_url_shortner,ma_space,ma_hashtags,ma_hashtags_tags,ma_api_key" />
            <?php
        }
    ?>
        <table class="form-table">
        	<tr>
                <th scope="row" valign="top">
                    Display
                </th>
                <td>
                    <input type="checkbox" value="1" <?php if (get_option('ma_display_page') == '1') echo 'checked="checked"'; ?> name="ma_display_page" id="ma_display_page" group="ma_display"/>
                    <label for="ma_display_page">Display the button on pages</label>
                    <br/>
                    <input type="checkbox" value="1" <?php if (get_option('ma_display_front') == '1') echo 'checked="checked"'; ?> name="ma_display_front" id="ma_display_front" group="ma_display"/>
                    <label for="ma_display_front">Display the button on the front page. </label>
                    <br/>
                    <input type="checkbox" value="1" <?php if (get_option('ma_display_rss') == '1') echo 'checked="checked"'; ?> name="ma_display_rss" id="ma_display_rss" group="ma_display"/>
                    <label for="ma_display_rss">Display the image button in your feed, only available as <strong>the normal size</strong> widget.</label>
                </td>
	            </tr>
                <th scope="row" valign="top">
                    Position
                </th>
                <td>
                	<select name="ma_where">
                		<option <?php if (get_option('ma_where') == 'before') echo 'selected="selected"'; ?> value="before">Before</option>
                		<option <?php if (get_option('ma_where') == 'after') echo 'selected="selected"'; ?> value="after">After</option>
                		<option <?php if (get_option('ma_where') == 'beforeandafter') echo 'selected="selected"'; ?> value="beforeandafter">Before and After</option>
                		<option <?php if (get_option('ma_where') == 'shortcode') echo 'selected="selected"'; ?> value="shortcode">Shortcode [mabzy]</option>
                		<option <?php if (get_option('ma_where') == 'manual') echo 'selected="selected"'; ?> value="manual">Manual</option>
                	</select>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top">
                    RSS Position
                </th>
                <td>
                	<select name="ma_rss_where">
                		<option <?php if (get_option('ma_rss_where') == 'before') echo 'selected="selected"'; ?> value="before">Before</option>
                		<option <?php if (get_option('ma_rss_where') == 'after') echo 'selected="selected"'; ?> value="after">After</option>
                		<option <?php if (get_option('ma_rss_where') == 'beforeandafter') echo 'selected="selected"'; ?> value="beforeandafter">Before and After</option>
                		<option <?php if (get_option('ma_where') == 'shortcode') echo 'selected="selected"'; ?> value="shortcode">Shortcode [mabzy]</option>
                	</select> 
                	<span class="description">Note, this isn't available just just yet!</span>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top"><label for="ma_style">Styling</label></th>
                <td>
                    <input type="text" value="<?php echo htmlspecialchars(get_option('ma_style')); ?>" name="ma_style" id="ma_style" />
                    <span class="description">Add style to the div that surrounds the button E.g. <code>float: left; margin-right: 10px;</code></span>
                </td>
            </tr>
            <tr>
                <th scope="row" valign="top">
                    Type
                </th>
                <td>
                    <input type="radio" value="large" <?php if (get_option('ma_version') == 'large') echo 'checked="checked"'; ?> name="ma_version" id="ma_version_large" group="ma_version"/>
                    <label for="ma_version_large">The normal size widget</label>
                    <br/>
                    <input type="radio" value="compact" <?php if (get_option('ma_version') == 'compact') echo 'checked="checked"'; ?> name="ma_version" id="ma_version_compact" group="ma_version" />
                    <label for="ma_version_compact">The compact widget</label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
        </p>
    </form>
    </div>
<?php
}



// On access of the admin page, register these variables (required for WP 2.7 & newer)
function ma_init(){
    if(function_exists('register_setting')){
        register_setting('ma-options', 'ma_display_page');
        register_setting('ma-options', 'ma_display_front');
        register_setting('ma-options', 'ma_display_rss');
        register_setting('ma-options', 'ma_source', 'ma_sanitize_username');
        register_setting('ma-options', 'ma_style');
        register_setting('ma-options', 'ma_version');
        register_setting('ma-options', 'ma_where');
        register_setting('ma-options', 'ma_rss_where');
        register_setting('ma-options', 'ma_ping');
        register_setting('ma-options', 'ma_url_shortner');
        register_setting('ma-options', 'ma_space');
        register_setting('ma-options', 'ma_hashtags');
        register_setting('ma-options', 'ma_hashtags_tags');
		register_setting('ma-options', 'ma_api_key');
    }
}

function ma_sanitize_username($username){
    return preg_replace('/[^A-Za-z0-9_]/','',$username);
}

// Only all the admin options if the user is an admin
if(is_admin()){
    add_action('admin_menu', 'ma_options');
    add_action('admin_init', 'ma_init');
}

// Set the default options when the plugin is activated
function ma_activate(){
    add_option('ma_where', 'before');
    add_option('ma_rss_where', 'before');
    add_option('ma_source');
    add_option('ma_style', 'float: right; margin-left: 10px;');
    add_option('ma_version', 'large');
    add_option('ma_display_page', '1');
    add_option('ma_display_front', '1');
    add_option('ma_display_rss', '1');
    add_option('ma_hashtags', 'on');
}

add_filter('the_content', 'ma_update', 8);
add_filter('get_the_excerpt', 'ma_remove_filter', 9);


add_action('wp_head', 'ma_head');

register_activation_hook( __FILE__, 'ma_activate');
