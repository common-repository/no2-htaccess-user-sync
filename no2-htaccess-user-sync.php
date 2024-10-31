<?php
/**
 * @package no2-htaccess-user-sync
 * @author Fabrice Bodmer
 * @version 2.3
 */
/*
Plugin Name: no2-htaccess-user-sync
Plugin URI: http://www.netoxygen.ch/fr/societe/open-source/wordpress.html
Description: This plugin syncs wordpress users with a .htusers file. Of course, this plugin is only compatible with Apache on Unix/Linux. Optionaly, if the user is authenticated via htaccess, he will also get logged into the wordpress php session.
Author: Fabrice Bodmer (Net Oxygen sàrl)
Version: 2.3
Author URI: http://netoxygen.ch/
*/

function do_login() {
    if (is_user_logged_in()) return; // if user already logged in -> do nothing

    $user = get_userdatabylogin($_SERVER['REMOTE_USER']);

    if ($user) {
        $user = new WP_User($user->ID);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login);
    }
}
// register the login function only if the option is enabled in the plugin's configuration
if (get_option('no2-htus-dowplogin')) add_action('init', 'do_login');

function delete_htuser($userid) {
    if (!$userid) return;

    $user_info = get_userdata($userid);
    $user = $user_info->user_login;

    $htusers_file = get_option('no2-htus-filepath');
    $htpasswd_bin = get_option('no2-htus-htpasswd');

    if (file_exists($htusers_file)) {
        exec($htpasswd_bin.' -D '.$htusers_file.' '.$user);
    }
}
add_action('delete_user', 'delete_htuser');

function update_htuser($userid) {
    if (!$userid) return;

    $user_info = get_userdata($userid);
    $user = $user_info->user_login;
    $pass1 = $_REQUEST['pass1']; //$user_info->user_pass;
    $pass2 = $_REQUEST['pass2'];

    $htusers_file = get_option('no2-htus-filepath');
    $htpasswd_bin = get_option('no2-htus-htpasswd');

    if ($pass1 == $pass2 && file_exists($htusers_file)) {
        exec($htpasswd_bin.' -b '.$htusers_file.' '.$user.' '.$pass1);
    }
}
add_action('profile_update', 'update_htuser');
add_action('user_register', 'update_htuser');

// action: check_passwords --> better ???


////// ADMIN //////

// Hook for adding admin menus
add_action('admin_menu', 'add_pages');

// action function for above hook
function add_pages() {
    // Add a new submenu under Options:
    add_options_page('htuser sync', 'htuser sync', 10, 'no2-htus', 'options_page');
}

// mt_options_page() displays the page content for the Test Options submenu
function options_page() {

    // variables for the field and option names 
    $opt_name = 'no2-htus-filepath';
    $opt2_name = 'no2-htus-htpasswd';
    $opt3_name = 'no2-htus-dowplogin';
    $hidden_field_name = 'no2-htus_hidden';
    $data_field_name = 'no2-htus-filepath';
    $data2_field_name = 'no2-htus-htpasswd';
    $data3_field_name = 'no2-htus-dowplogin';

    // Init option (default values)
    add_option($opt3_name,1); // do wp login

    // Read in existing option value from database
    $opt_val = get_option( $opt_name );
    $opt2_val = get_option($opt2_name);
    $opt3_val = get_option($opt3_name);

    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( $_POST[ $hidden_field_name ] == 'Y' ) {
        // Read their posted value
        $opt_val = $_POST[ $data_field_name ];
        $opt2_val = $_POST[ $data2_field_name ];
        if ($_POST[ $data3_field_name ] == 'set') $opt3_val = 1;
        else $opt3_val = 0;

        // Save the posted value in the database
        update_option( $opt_name, $opt_val );
        update_option($opt2_name, $opt2_val);
        update_option($opt3_name, $opt3_val);

        // Put an options updated message on the screen

?>
<div class="updated"><p><strong><?php _e('Options saved.', 'no2-htus' ); ?></strong></p></div>
<?php
        if (!file_exists($opt_val)) echo "<div class=\"updated\"><p style=\"color: red;\"><strong>".__('Error: the htuser file doesn\'t exist.', 'no2-htus' )."</strong></p></div>";
        else if (!is_writable($opt_val)) echo "<div class=\"updated\"><p style=\"color: red;\"><strong>".__('Error: the htuser file is not writable.', 'no2-htus' )."</strong></p></div>";
        if (!file_exists($opt2_val)) echo "<div class=\"updated\"><p style=\"color: red;\"><strong>".__('Error: htpasswd binary not found.', 'no2-htus' )."</strong></p></div>";
        else if (!is_executable($opt2_val)) echo "<div class=\"updated\"><p style=\"color: red;\"><strong>".__('Error: cannot execute htpasswd binary.', 'no2-htus' )."</strong></p></div>";
    }

    // Now display the options editing screen

    echo '<div class="wrap">';

    // header

    echo "<h2>" . __( 'NO2 htuser syncing plugin', 'no2-htus' ) . "</h2>";

    // options form

    if ($opt3_val) $opt3_checked = ' checked="checked"';
    ?>

<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

<p><?php _e("Path to the htpasswd binary (absolute):", 'no2-htus' ); ?>
<input type="text" name="<?php echo $data2_field_name; ?>" value="<?php echo $opt2_val; ?>" size="50">
</p>
<p><?php _e("Path to your htuser file (absolute):", 'no2-htus' ); ?> 
<input type="text" name="<?php echo $data_field_name; ?>" value="<?php echo $opt_val; ?>" size="50">
</p>
<p>
<input type="checkbox" name="<?php echo $data3_field_name; ?>" value="set"<?php echo $opt3_checked; ?>>Authenticated htusers should be logged into wordpress</input>
</p>
<hr />

<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Update Options', 'no2-htus' ) ?>" />
</p>

</form>
</div>

<?php
 
}



?>
