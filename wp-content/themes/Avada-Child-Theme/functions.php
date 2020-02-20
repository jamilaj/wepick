<?php

function theme_enqueue_styles() {
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', array('avada-stylesheet'));
    wp_enqueue_style('aos-style', 'https://unpkg.com/aos@2.3.1/dist/aos.css', array('avada-stylesheet'));
}
add_action('wp_enqueue_scripts', 'theme_enqueue_styles');

function theme_enqueue_scripts() {
    wp_enqueue_script( 'weshore', get_stylesheet_directory_uri() . '/js/scripty.js', array( 'jquery' ), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'theme_enqueue_scripts' );

function aos_script() {
    echo '<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script><script>AOS.init();</script>';

}
add_action( 'wp_footer', 'aos_script' );



function avada_lang_setup() {
    $lang = get_stylesheet_directory() . '/languages';
    load_child_theme_textdomain('Avada', $lang);
}
add_action('after_setup_theme', 'avada_lang_setup');






/* ----------- appel du css --------- */

function My_login() {
    echo '<link rel="stylesheet" type="text/css" href="' . get_bloginfo('stylesheet_directory') . '/custom-login-wp/style-login.css" />';
}

add_action('login_head', 'My_login');


/* -------Changement du lien du logo---- */

function my_login_logo_url() {
    return get_bloginfo('url');
}

add_filter('login_headerurl', 'my_login_logo_url');

/* -------- Changement du titre--------- */

function my_login_logo_url_titre() {
    return '';
}

add_filter('login_headertitle', 'my_login_logo_url_titre');

/* ---------Changement du shake animation en cas d'erreur------- */

function my_login_head() {
    remove_action('login_head', 'wp_shake_js', 12);
}

add_action('login_head', 'my_login_head');

/* ----------Footer ---------- */


 


/*----------- changement du favicon ---------*/
function add_favicon() {
    $favicon_url = "";
    echo '<link rel="shortcut icon" href="' . $favicon_url . '" />';
}
add_action('wp_head','add_favicon'); //front end
add_action('login_head', 'add_favicon'); //login page
add_action('admin_head', 'add_favicon'); //back end

// Defer Javascripts
// Defer jQuery Parsing using the HTML5 defer property
if (!(is_admin() )) {
function defer_parsing_of_js ( $url ) {
if ( FALSE === strpos( $url, '.js' ) ) return $url;
if ( strpos( $url, 'jquery.js' ) ) return $url;
// return "$url' defer ";
return "$url' defer onload='";
}
add_filter( 'clean_url', 'defer_parsing_of_js', 11, 1 );
}


function pr_disable_admin_notices() {
		global $wp_filter;
			if ( is_user_admin() ) {
				if ( isset( $wp_filter['user_admin_notices'] ) ) {
								unset( $wp_filter['user_admin_notices'] );
				}
			} elseif ( isset( $wp_filter['admin_notices'] ) ) {
						unset( $wp_filter['admin_notices'] );
			}
			if ( isset( $wp_filter['all_admin_notices'] ) ) {
						unset( $wp_filter['all_admin_notices'] );
			}
	}

        //fusion_options-primary_color

  
 //require_once 'popup.php';
require_once 'coherence_options.php';




//$no_exists_value = get_option( 'smile_slide_in_styles' );
//var_dump( $no_exists_value ); /* outputs false */