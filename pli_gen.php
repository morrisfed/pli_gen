<?php
/**
 * Plugin Name: PLI Certificate Generation
 * Description: Morris Federation public liability insurance certificate generation
 * Version: 0.2
 */

register_activation_hook(__FILE__, "pli_activate");
register_uninstall_hook(__FILE__, "pli_uninstall");

add_action("init", "pli_initialise_shortcode");

if (is_admin()) {
    add_action("admin_menu", "pli_register_settings_page");
}

define("PLI_OPTION_NAME", "pligen");

// function show_loggedin_function( $atts ) {
//
//     // Rumburg
//     //$gotUser = get_user_by("login", "5b053bd3afd691796be93226");
//     // Downes
//     $gotUser = get_user_by("login", "5b053bd5afd691796be933d0");
//     pli_log($gotUser);
//
// 	add_filter('widget_text', 'do_shortcode');
// 	if ($gotUser)
// 		return 'Welcome ' . $gotUser->display_name . '!';
// 	else
// 		return '<a href="' . wp_login_url() . ' ">Login</a>';
//
// }
// add_shortcode( 'show_loggedin_as', 'show_loggedin_function' );

function pli_activate()
{
    pli_log("pli_activate called");
    $options = get_option(PLI_OPTION_NAME);
    if (!$options) {
        $options = [];
    }
    $update_options = [
        "template" => array_key_exists("template", $options)
            ? $options["template"]
            : "",
        "offset" => array_key_exists("offset", $options)
            ? $options["offset"]
            : "350",
        "fontsize" => array_key_exists("fontsize", $options)
            ? $options["fontsize"]
            : "17",
        "executable" => array_key_exists("executable", $options)
            ? $options["executable"]
            : "",
        "cache" => array_key_exists("cache", $options) ? $options["cache"] : "",
    ];
    update_option(PLI_OPTION_NAME, $update_options);
}

function pli_initialise_shortcode()
{
    pli_log("pli_initialise_shortcode called");
    add_shortcode("pligen", "pli_shortcode_handler");
}

function pli_shortcode_handler()
{
    pli_log("pli_shortcode_handler called");

    $options = get_option(PLI_OPTION_NAME);
    $template = $options["template"];
    $offset = $options["offset"];
    $fontsize = $options["fontsize"];
    $executable = $options["executable"];
    $cache = $options["cache"];

    if (
        empty(ABSPATH . $template) ||
        empty(ABSPATH . $executable) ||
        empty(ABSPATH . $cache)
    ) {
        return "<p>PLI certificates are not currently available</p>";
    }

    if (!file_exists(ABSPATH . $cache)) {
        mkdir(ABSPATH . $cache, 0777);
    }

    $current_user = wp_get_current_user();
    pli_log($current_user);
    pli_log("is_user_logged_in: " . is_user_logged_in());

    $team_name = wp_specialchars_decode($current_user->display_name);

    pli_log("Team name = " . $team_name);

    $words = explode(" ", $team_name);
    $line = null;
    $returns = 1;
    foreach ($words as $word) {
        $temp = null;
        if (empty($line)) {
            $temp = $word;
        } else {
            $temp = $line . " " . $word;
        }
        if (strlen($temp) > 40 * $returns) {
            $temp = $line . "\n" . $word;
            $returns++;
        }
        $line = $temp;
    }
    $line = str_replace('"', '\"', $line);
    pli_log("Folded team name = " . $line);

    $pdf_file_name = "pli_" . $current_user->user_login . ".pdf";
    $thumbnail_file_name = $pdf_file_name . ".jpg";
    $pdf_file_path = ABSPATH . $cache . "/" . $pdf_file_name;
    pli_log($pdf_file_path);
    $thumbnail_file_path = $pdf_file_path . ".jpg";
    $name_file_path = $pdf_file_path . "name.txt";

    $name_matches = false;

    if (file_exists($name_file_path)) {
        $current_team_name = file_get_contents($name_file_path);
        if (strcmp($team_name, $current_team_name) == 0) {
            $name_matches = true;
        }
    }
    if (!$name_matches) {
        file_put_contents($name_file_path, $team_name);
    }

    if (!$name_matches || !file_exists($pdf_file_path)) {
        $command =
            ABSPATH .
            $executable .
            ' -add-text "' .
            $line .
            '" -top ' .
            $offset .
            ' -justify-center -midline -font "Helvetica" -font-size ' .
            $fontsize .
            " " .
            ABSPATH .
            $template .
            " -o " .
            $pdf_file_path;
        pli_log($command);
        shell_exec($command);
    }

    if (!$name_matches || !file_exists($thumbnail_file_path)) {
        $pdfThumb = new imagick();
        $pdfThumb->setResolution(32, 32);
        $pdfThumb->readImage($pdf_file_path . "[0]");
        $pdfThumb->setImageFormat("jpg");
        $pdfThumb->writeImage($thumbnail_file_path);
    }

    $url = get_site_url();

    return '<a href="' .
        $url .
        $cache .
        $pdf_file_name .
        '"><img class="alignnone size-medium thumb-of-pdf" src="' .
        $url .
        $cache .
        $thumbnail_file_name .
        '" alt="thumbnail of pli" width="212" height="300" /></a>';
}

function pli_register_settings_page()
{
    add_options_page(
        "PLI Certificate Generation Settings",
        "PLI Certificate Generation",
        "manage_options",
        __FILE__,
        "pli_options_page"
    );
}

function pli_options_page()
{
    pli_log("pli_options_page called");
    if (
        isset($_POST["pli_gen_options_nonce"]) &&
        wp_verify_nonce($_POST["pli_gen_options_nonce"], basename(__FILE__))
    ) {
        pli_log("updating options");
        $update_options = [
            "template" => isset($_POST["pli_gen_template"])
                ? $_POST["pli_gen_template"]
                : "",
            "offset" => isset($_POST["pli_gen_offset"])
                ? $_POST["pli_gen_offset"]
                : "350",
            "fontsize" => isset($_POST["pli_gen_fontsize"])
                ? $_POST["pli_gen_fontsize"]
                : "17",
            "executable" => isset($_POST["pli_gen_executable"])
                ? $_POST["pli_gen_executable"]
                : "",
            "cache" => isset($_POST["pli_gen_cache"])
                ? $_POST["pli_gen_cache"]
                : "",
        ];
        update_option(PLI_OPTION_NAME, $update_options);
        echo '<div class="updated fade"><p><strong>Options saved</strong></p></div>';
    }
    if (isset($_GET["clear_cache"])) {
        pli_log("clearing cache");
        $options = get_option(PLI_OPTION_NAME);
        if (empty(ABSPATH . $options["cache"])) {
            echo "<h2>PLI Certicate Cache</h2>" .
                "\n" .
                "<p>Cache could not be cleared because the cache option is not configured</p>";
        } else {
            $files = glob(ABSPATH . $options["cache"] . "/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    pli_log("Deleting " . $file);
                    unlink($file);
                }
            }
            echo "<h2>PLI Certicate Cache Cleared</h2>" . "\n";
        }
        echo '<p><a href="' .
            remove_query_arg("clear_cache", $_SERVER["REQUEST_URI"]) .
            '" class="button button-primary">Back to PLI Certicate Generator options</a></p><br/>' .
            "\n";
    } else {
        $options = get_option(PLI_OPTION_NAME);
        echo "<div>" .
            "\n" .
            "<h2>PLI Certificate Generation Options</h2>" .
            "\n" .
            '<form method="post" action="">' .
            "\n" .
            "<table>" .
            "\n" .
            '<tr valign="top">' .
            "\n" .
            '<th scope="row"><label for="pli_gen_template">Template Path</label></th>' .
            "\n" .
            '<td><input type="text" id="pli_gen_template" name="pli_gen_template" value="' .
            $options["template"] .
            '"/></td>' .
            "\n" .
            "</tr>" .
            "\n" .
            '<tr valign="top">' .
            "\n" .
            '<th scope="row"><label for="pli_gen_offset">Offset into PDF for team name</label></th>' .
            "\n" .
            '<td><input type="text" id="pli_gen_offset" name="pli_gen_offset" value="' .
            $options["offset"] .
            '"/></td>' .
            "\n" .
            "</tr>" .
            "\n" .
            '<tr valign="top">' .
            "\n" .
            '<th scope="row"><label for="pli_gen_fontsize">Font size for team name</label></th>' .
            "\n" .
            '<td><input type="text" id="pli_gen_fontsize" name="pli_gen_fontsize" value="' .
            $options["fontsize"] .
            '"/></td>' .
            "\n" .
            "</tr>" .
            "\n" .
            '<tr valign="top">' .
            "\n" .
            '<th scope="row"><label for="pli_gen_executable">Executable path</label></th>' .
            "\n" .
            '<td><input type="text" id="pli_gen_executable" name="pli_gen_executable" value="' .
            $options["executable"] .
            '"/></td>' .
            "\n" .
            "</tr>" .
            "\n" .
            '<tr valign="top">' .
            "\n" .
            '<th scope="row"><label for="pli_gen_cache">Cache path</label></th>' .
            "\n" .
            '<td><input type="text" id="pli_gen_cache" name="pli_gen_cache" value="' .
            $options["cache"] .
            '"/></td>' .
            "\n" .
            "</tr>" .
            "\n" .
            "</table>" .
            "\n" .
            '<p class="submit"><input type="submit" name="Submit" class="button-primary" value="Save changes"/></p>' .
            "\n" .
            '<input type="hidden" name="pli_gen_options_nonce" value="' .
            wp_create_nonce(basename(__FILE__)) .
            '" />' .
            "\n" .
            "</form>" .
            "\n" .
            "</div>" .
            "\n" .
            "<h2>PLI Certificate Cache</h2>" .
            "\n" .
            '<p><a href="' .
            add_query_arg("clear_cache", "all", $_SERVER["REQUEST_URI"]) .
            '" class="button button-primary">' .
            __("Clear Cache", "pli-gen") .
            "</a></p>" .
            "\n";
    }
}

function pli_uninstall()
{
    pli_log("pli_uninstall called");
    delete_option(PLI_OPTION_NAME);
}

function pli_log($message)
{
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}
