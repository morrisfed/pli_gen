<?php
/**
 * Plugin Name: PLI Certificate Generation
 * Description: Morris Federation public liability insurance certificate generation
 * Version: 0.1
 */

register_activation_hook( __FILE__, 'pli_activate' );
register_uninstall_hook( __FILE__, 'pli_uninstall' );

add_action('init', 'pli_initialise_shortcode');

if ( is_admin() ) {
    add_action( 'admin_init', 'pli_register_settings' );
    add_action( 'admin_menu', 'pli_register_settings_page');
}

global $pli_option;
define( 'PLI_OPTION_NAME', 'pli_gen' );
$pli_option = ABSPATH . get_option( PLI_OPTION_NAME );
if (file_exists($pli_option)) {
    pli_log("pli_option = ".$pli_option." exists");
} else {
    pli_log("pli_option = ".$pli_option." does not exist");
}

function pli_activate() {
    pli_log("pli_activate called");
    mkdir(ABSPATH."/wp-content/pli_gen", 0644);
}

function pli_initialise_shortcode() {
    pli_log("pli_initialise_shortcode called");
    add_shortcode('pligen', 'pli_shortcode_handler');
}

function pli_shortcode_handler() {
    global $pli_option;
    pli_log("pli_shortcode_handler called ");
    $current_user = wp_get_current_user();
    $team_name = $current_user->display_name;
    $words = explode(' ', $team_name);

    $line = "Test Team";

    $pdf_file_name = 'pli_'.$current_user->user_login.'.pdf';
    $thumbnail_file_name = $pdf_file_name.'.jpg';
    $pdf_file_path = ABSPATH.'wp-content/pli_gen/'.$pdf_file_name;
    $thumbnail_file_path = $pdf_file_path . '.jpg';

    if (!file_exists($pdf_file_path)) {
        $cpdf = ABSPATH.'scripts/cpdf';
        $command=$cpdf.' -add-text "'.$line.'" -top 350 -justify-center -midline -font "Helvetica" -font-size 17 '.$pli_option.' -o '.$pdf_file_path_path;
        shell_exec($command);
    }

    if (!file_exists($thumbnail_file_path)) {
        $pdfThumb = new imagick();
        $pdfThumb->setResolution(32, 32);
        $pdfThumb->readImage($pdf_file_path . '[0]');
        $pdfThumb->setImageFormat('jpg');
        $pdfThumb->writeImage($thumbnail_file_path);
    }

    $url = get_site_url();

    return '<a href="'.$url.'/wp-content/pli_gen/'.$pdf_file_name.'"><img class="alignnone size-medium thumb-of-pdf" src="'.$url.'/wp-content/pli_gen/'.$thumbnail_file_name.'" alt="thumbnail of pli" width="212" height="300" /></a>';
}

function pli_register_settings() {
    pli_log("pli_register_settings called");

    add_option(PLI_OPTION_NAME, '');

    register_setting(PLI_OPTION_NAME, PLI_OPTION_NAME);
}

function pli_register_settings_page() {
    add_options_page("PLI Certificate Generation Settings", "PLI Certificate Generation", "manage_options", PLI_OPTION_NAME, 'pli_options_page');
}

function pli_options_page() {
    pli_log('pli_options_page called');
?>
  <div>
  <h2>PLI Certificate Generation</h2>
  <form method="post" action="options.php">
  <?php settings_fields(PLI_OPTION_NAME); ?>
  <table>
  <tr valign="top">
  <th scope="row"><label for="pli_gen">PLI Certificate Template Path</label></th>
  <td><input type="text" id="pli_gen" name="pli_gen" value="<?php echo get_option('pli_gen'); ?>" /></td>
  </tr>
  </table>
  <?php  submit_button(); ?>
  </form>
  </div>
<?php
}

function pli_uninstall() {
    pli_log("pli_uninstall called");
    delete_option( PLI_OPTION_NAME );
}

function pli_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}
