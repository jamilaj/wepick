<?php
function hook_phoneNumber() {
  echo "<script>
      jQuery(document).ready(function() {
      var globalPhoneNumber1='".fusion_get_option('telephone_1')."';
      var globalPhoneNumber2='".fusion_get_option('telephone_2')."';
      var globalAdresseMail='".fusion_get_option('Adresse_mail')."';
        
      jQuery(\".phoneNumber1\").html(\"<a href=' tel:\"+globalPhoneNumber1+\"' class='dib'>\"+globalPhoneNumber1+\"</a>\" );
      jQuery(\".phoneNumber2\").html(\"<a href=' tel:\"+globalPhoneNumber2+\"' class='dib'>\"+globalPhoneNumber2+\"</a>\" );
      jQuery(\".AdresseMail\").html( \"<a href=' mailto:\"+globalAdresseMail+\"' class='dib'>\"+globalAdresseMail+\"</a>\" );
         
      });
  </script>";
}
 function shortcode_phoneNumber1() {
  return "<a href='tel:".fusion_get_option('telephone_1')."' class='dib'>".fusion_get_option('telephone_1')."</a>";
}
 function shortcode_phoneNumber2() {
  return "<a href='tel:".fusion_get_option('telephone_2')."' class='dib'>".fusion_get_option('telephone_2')."</a>";
}
 function shortcode_AdresseMail() {
 return "<a href='mailto:".fusion_get_option('Adresse_mail')."'>".fusion_get_option('Adresse_mail')."</a>";
}
add_action('wp_head', 'hook_phoneNumber');
add_shortcode('phoneNumber1', 'shortcode_phoneNumber1');
add_shortcode('phoneNumber2', 'shortcode_phoneNumber2');
add_shortcode('AdresseMail', 'shortcode_AdresseMail');



add_action('init', 'new_widgets_fields');
function new_widgets_fields() {

    if ( class_exists( 'fusionredux' ) ) {
    //global $fusionredux;
    $opt_name = "fusion_options";
        fusionredux::setField($opt_name, array(
            'section_id' => 'header_info_1',
            'id' => 'telephone_1',
            'type'        => 'text',
            'title' => __('Numero de Tél 1', 'AVADA'),
            'subtitle' => __('shortcode [phoneNumber1] OU CSS class "phoneNumber1"', 'AVADA'),
            'validate' => 'string'
        ));
        fusionredux::setField($opt_name, array(
            'section_id' => 'header_info_1',
            'id' => 'telephone_2',
            'type'        => 'text',
            'title' => __('Numero de Tél 2', 'AVADA'),
            'subtitle' => __('shortcode [phoneNumber2] OU CSS class "phoneNumber2"', 'AVADA'),
            'validate' => 'string'
        ));
        fusionredux::setField($opt_name, array(
            'section_id' => 'header_info_1',
            'id' => 'Adresse_mail',
            'type'        => 'text',
            'title' => __('Email', 'AVADA'),
            'subtitle' => __('shortcode [AdresseMail] OU CSS class "AdresseMail"', 'AVADA'),
            'validate' => 'email'
        ));
    }
}


function add_popup_section($sections) {
    //$sections = array(); // Delete this if you want to keep original sections!
    $sections[] = array(
        'title' => __('PopUp', 'Avada'),
        'desc' => __('', 'Avada'),
        'priority' => 13,
        'icon' => 'el-icon-photo',
        'fields' => [
            'popup_active_desactive' => [
                'title' => esc_html__('Activer ou Désactiver le popup'),
                'id' => 'popup_active_desactive',
                'default' => '1',
                'type' => 'switch',
                'update_callback' => [
                    [
                        'condition' => 'is_popup',
                        'operator' => '===',
                        'value' => true,
                    ],
                ],
            ],
            'popup_button_background' => [
                'title' => esc_html__('Button Background coleur', 'Avada'),
                'id' => 'popup_button_background',
                'default' => '#21889d',
                'type' => 'color',
            ],
            'popup_button_color' => [
                'title' => esc_html__('Button Text coleur', 'Avada'),
                'id' => 'popup_button_color',
                'default' => '#fff',
                'type' => 'color',
            ],
            'popup_button_lien' => [
                'title' => esc_html__('Lien de bouton contact', 'Avada'),
                'id' => 'popup_button_lien',
                'default' => get_site_url().'/contact',
                'placeholder' => get_site_url().'/contact',
                'type' => 'text',
                'validate' => 'url',
            ],
            'popup_border_color' => [
                'title' => esc_html__('Couleur bordure top de popup', 'Avada'),
                'id' => 'popup_border_color',
                'default' => '#000000',
                'type' => 'color',
            ],
            'popup_background_color' => [
                'title' => esc_html__('Couleur de fond', 'Avada'),
                'id' => 'popup_background_color',
                'default' => '#ffffff',
                'type' => 'color',
            ],
            'popup_text_color' => [
                'title' => esc_html__('Couleur de texte', 'Avada'),
                'id' => 'popup_text_color',
                'default' => '#000000',
                'type' => 'color',
            ],
            'popup_btnOK_color' => [
                'title' => esc_html__('Couleur Button Envoyer', 'Avada'),
                'id' => 'popup_btnOK_color',
                'default' => '#ffffff',
                'type' => 'color',
            ],
            'popup_fondOK_color' => [
                'title' => esc_html__('Fond Button Envoyer', 'Avada'),
                'id' => 'popup_fondOK_color',
                'default' => '#000000',
                'type' => 'color',
            ],
            'popup_background_plus' => [
                'title' => esc_html__('Couleur float button', 'Avada'),
                'id' => 'popup_background_plus',
                'default' => '#000000',
                'type' => 'color',
            ],
            'popup_icon' => [
                'title' => esc_html__('Icon', 'Avada'),
                'description' => esc_html__('Select an image file for your logo.', 'Avada'),
                'id' => 'popup_icon',
                'default' => get_template_directory() . '/images/icone-call.png',
                'mod' => 'min',
                'type' => 'media',
                'mode' => false,
            ],
        ],
    );

    return $sections;
}

add_filter("fusionredux/options/fusion_options/sections", 'add_popup_section');



add_action( 'init', 'popup_install' );
function get_wp_installation()
{
    $full_path = getcwd();
    $ar = explode("wp-", $full_path);
    return $ar[0];
}

function popup_install() {
    //require_once( $_SERVER['DOCUMENT_ROOT'] . '/convert/wp-load.php' );
    global $wpdb;

    $table_name = $wpdb->prefix . 'options';
    $charset_collate = $wpdb->get_charset_collate();
$vv='a:1:{i:0;a:3:{s:10:"style_name";s:11:"besoindaide";s:8:"style_id";s:11:"cp_id_b33a6";s:14:"style_settings";s:9286:"a:173:{s:5:"style";s:11:"free_widget";s:8:"style_id";s:11:"cp_id_b33a6";s:10:"style_type";s:8:"slide_in";s:6:"option";s:21:"smile_slide_in_styles";s:13:"cp_gmt_offset";s:1:"1";s:19:"cp_counter_timezone";s:9:"wordpress";s:9:"new_style";s:11:"besoindaide";s:17:"modal_middle_desc";s:17:"With%20John%20Doe";s:11:"font_family";s:25:",Open%20Sans,,Montserrat,";s:15:"cp_google_fonts";s:319:"Bitter%2CLato%2CLibre%20Baskerville%2CMontserrat%2CNeuton%2COpen%20Sans%2CPacifico%2CRaleway%2CRoboto%2CSacramento%2CVarela%20Round%2CArial%2CArial%20Black%2CComic%20Sans%20MS%2CCourier%20New%2CGeorgia%2CImpact%2CLucida%20Sans%20Unicode%2CPalatino%20Linotype%2CTahoma%2CTimes%20New%20Roman%2CTrebuchet%20MS%2CVerdana%2C";s:14:"slidein_title1";s:236:"%26lt%3Bspan%20class%3D%22cp_responsive%20cp_font%22%20data-font-size%3D%2225px%22%20style%3D%22display%3A%20block%3B%20line-height%3A%201.15em%3B%20font-family%3Aopen%20sans%3B%22%26gt%3BBESOIN%20D%26%2339%3BAIDE%26lt%3B%2Fspan%26gt%3B";s:19:"slidein_short_desc1";s:152:"%3Cdiv%20class%3D%22txtEtRappeller%22%20style%3D%22font-family%3Aopen%20sans%3B%22%3EVous%20souhaitez%20%C3%AAtre%20rappel%C3%A9%20%3F%3C%2Fdiv%3E%0D%0A";s:20:"slidein_confidential";s:814:"%3Cdiv%20class%3D%22appelezContenaire%22%3E%0D%0A%3Cdiv%20class%3D%22iconTelephone%22%3E%5BimagePhone%5D%3C%2Fdiv%3E%0D%0A%0D%0A%3Cdiv%20style%3D%22text-align%3A%20left%3B%22%3E%3Cspan%20style%3D%22font-family%3Aopen%20sans%3B%22%3E%3Cspan%20class%3D%22cp_responsive%20cp_font%20cp_appelezLe%22%20data-font-size-init%3D%2222px%22%20style%3D%22font-size%3A22px%3B%22%3EOu%20appelez%20le%20%3A%3C%2Fspan%3E%3C%2Fspan%3E%0D%0A%0D%0A%3Cdiv%20style%3D%22text-align%3A%20left%3B%22%3E%3Cspan%20style%3D%22font-family%3Aopen%20sans%3B%22%3E%3Cspan%20class%3D%22slideup-phone-number%22%20style%3D%22font-size%3A30px%3B%22%3E02%2000%2000%2000%2000%3C%2Fspan%3E%3C%2Fspan%3E%3C%2Fdiv%3E%0D%0A%3C%2Fdiv%3E%0D%0A%3Ca%20class%3D%22slideup_btncontact%22%20href%3D%22%23%22%3Eformulaire%20de%20contact%3C%2Fa%3E%3C%2Fdiv%3E%0D%0A";s:15:"slidein_content";s:269:"Lorem%20ipsum%20dolor%20sit%20amet%2C%20consectetur%20adipiscing%20elit.%20Duis%20viverra%2C%20urna%20vitae%20vehicula%20congue%2C%20purus%20nibh%20vestibulum%20lacus%2C%20sit%20amet%20tristique%20ante%20odio%20viverra%20orci.%20Nullam%20consectetur%20mollis%20lacinia.";s:12:"button_title";s:146:"%26lt%3Bspan%20class%3D%22cp_responsive%22%20data-font-size%3D%2210px%22%20style%3D%22font-size%3A12px%3B%22%26gt%3BENVOYER%26lt%3B%2Fspan%26gt%3B";s:11:"form_fields";s:203:"order-%3E0%7Cinput_type-%3Enumber%7Cinput_label-%3EN%C2%B0%20T%C3%A9l%C3%A9phone%7Cinput_name-%3EN%C2%B0%20T%C3%A9l%C3%A9phone%7Cinput_placeholder-%3ELaissez%20votre%20num%C3%A9ro%7Cinput_require-%3Etrue";s:10:"input_type";s:6:"number";s:11:"input_label";s:29:"N%C2%B0%20T%C3%A9l%C3%A9phone";s:10:"input_name";s:29:"N%C2%B0%20T%C3%A9l%C3%A9phone";s:17:"input_placeholder";s:29:"Laissez%20votre%20num%C3%A9ro";s:16:"dropdown_options";s:29:"Enter%20Your%20Options%20Here";s:9:"row_value";s:0:"";s:13:"input_require";s:0:"";s:12:"hidden_value";s:0:"";s:11:"form_layout";s:16:"cp-form-layout-3";s:16:"only_button_link";s:0:"";s:23:"only_button_link_target";s:6:"_blank";s:19:"form_grid_structure";s:24:"cp-form-grid-structure-3";s:18:"btn_attached_email";s:1:"1";s:16:"form_input_align";s:4:"left";s:16:"form_input_color";s:9:"%237a7a7a";s:19:"form_input_bg_color";s:26:"rgb(255%2C%20255%2C%20255)";s:23:"form_input_border_color";s:26:"rgb(191%2C%20190%2C%20190)";s:12:"input_shadow";s:1:"0";s:18:"input_shadow_color";s:33:"rgba(66%2C%2066%2C%2066%2C%200.6)";s:19:"input_border_radius";s:1:"5";s:15:"form_input_font";s:11:"Open%20Sans";s:20:"form_input_font_size";s:2:"14";s:21:"form_input_padding_tb";s:2:"11";s:21:"form_input_padding_lr";s:2:"20";s:18:"form_lable_visible";s:0:"";s:16:"form_lable_color";s:26:"rgb(153%2C%20153%2C%20153)";s:15:"form_label_font";s:0:"";s:20:"form_lable_font_size";s:2:"14";s:9:"btn_style";s:11:"cp-btn-flat";s:15:"button_bg_color";s:9:"%23303131";s:17:"btn_border_radius";s:1:"5";s:10:"btn_shadow";s:0:"";s:13:"btn_no_follow";s:0:"";s:22:"button_txt_hover_color";s:9:"%23ffffff";s:21:"button_bg_hover_color";s:9:"%23232424";s:24:"button_bg_gradient_color";s:9:"%237d7e7e";s:17:"form_submit_align";s:19:"cp-submit-wrap-full";s:24:"submit_button_tb_padding";s:1:"9";s:24:"submit_button_lr_padding";s:2:"13";s:19:"slidein_title_color";s:9:"%2330414f";s:18:"slidein_desc_color";s:9:"%2330414f";s:9:"tip_color";s:9:"%2330414f";s:19:"slidein_bg_gradient";s:1:"0";s:16:"slidein_bg_color";s:9:"%23ffffff";s:23:"module_bg_gradient-type";s:6:"linear";s:22:"module_bg_gradient_one";s:9:"%23ffffff";s:22:"module_bg_gradient_sec";s:9:"%231e73be";s:28:"module_bg_gradient-direction";s:10:"top_center";s:24:"module_bg_gradient_start";s:1:"0";s:22:"module_bg_gradient_end";s:3:"100";s:18:"module_bg_gradient";s:53:"%23ffffff%7C%231e73be%7C0%7C100%7Clinear%7Ctop_center";s:27:"slidein_bg_gradient_lighten";s:0:"";s:21:"slide_in_bg_image_src";s:10:"upload_img";s:28:"slide_in_bg_image_custom_url";s:0:"";s:17:"slide_in_bg_image";s:0:"";s:22:"slide_in_bg_image_size";s:4:"full";s:6:"opt_bg";s:26:"no-repeat%7Ccenter%7Ccover";s:26:"side_button_bg_hover_color";s:9:"%233a7ecb";s:29:"side_button_bg_gradient_color";s:9:"%2394d8ff";s:15:"slidein_img_src";s:10:"upload_img";s:22:"slidein_img_custom_url";s:0:"";s:13:"slidein_image";s:8:"7%7Cfull";s:18:"slidein_image_size";s:4:"full";s:10:"image_size";s:3:"120";s:14:"image_position";s:1:"0";s:25:"image_horizontal_position";s:1:"0";s:23:"image_vertical_position";s:1:"0";s:22:"image_displayon_mobile";s:1:"1";s:13:"close_slidein";s:9:"close_img";s:18:"close_si_image_src";s:9:"pre_icons";s:10:"close_icon";s:12:"circle_final";s:29:"slide_in_close_img_custom_url";s:0:"";s:9:"close_txt";s:6:"Fermer";s:16:"close_text_color";s:9:"%23898989";s:15:"close_text_font";s:10:"Montserrat";s:9:"close_img";s:124:"http%3A%2F%2Flocalhost%2Fconvert%2Fwp-content%2Fplugins%2Fconvertplug%2Fmodules%2Fslide_in%2Fassets%2Fimg%2Fcross.png%7Cfull";s:14:"close_img_size";s:4:"full";s:20:"cp_close_image_width";s:2:"33";s:23:"adjacent_close_position";s:9:"top_right";s:21:"close_slidein_tooltip";s:1:"0";s:13:"tooltip_title";s:54:"Note%3A%20Slide%20Ins%20are%20displayed%20only%20once!";s:19:"tooltip_title_color";s:26:"rgb(255%2C%20255%2C%20255)";s:18:"tooltip_background";s:24:"rgb(209%2C%2037%2C%2037)";s:25:"display_close_on_duration";s:1:"1";s:18:"close_btn_duration";s:1:"5";s:16:"button_animation";s:15:"smile-slideInUp";s:10:"toggle_btn";s:1:"1";s:18:"toggle_btn_visible";s:1:"1";s:18:"slide_button_title";s:3:"%2B";s:18:"toggle_button_font";s:0:"";s:20:"toggle_btn_font_size";s:2:"40";s:23:"slide_button_text_color";s:28:"rgba(255%2C255%2C255%2C0.01)";s:21:"slide_button_bg_color";s:9:"%23478bd8";s:18:"slide_btn_gradient";s:1:"0";s:26:"toggle_button_border_color";s:20:"rgb(0%2C%200%2C%200)";s:22:"toggle_btn_border_size";s:1:"0";s:24:"toggle_btn_border_radius";s:2:"10";s:21:"toggle_btn_padding_tb";s:2:"10";s:22:"toggle_btn_padding_lrv";s:2:"10";s:21:"autoclose_on_duration";s:0:"";s:21:"close_module_duration";s:1:"1";s:14:"overlay_effect";s:15:"smile-slideInUp";s:14:"exit_animation";s:18:"smile-slideOutDown";s:22:"disable_overlay_effect";s:0:"";s:20:"hide_animation_width";s:3:"768";s:16:"slidein_position";s:11:"bottom-left";s:16:"cp_slidein_width";s:3:"450";s:15:"content_padding";s:1:"0";s:6:"border";s:179:"br_type%3A0%7Cbr_all%3A0%7Cbr_tl%3A0%7Cbr_tr%3A0%7Cbr_br%3A0%7Cbr_bl%3A0%7Cstyle%3Asolid%7Ccolor%3A%23303131%7Cbw_type%3A1%7Cbw_all%3A0%7Cbw_t%3A5%7Cbw_l%3A0%7Cbw_r%3A0%7Cbw_b%3A0";s:10:"box_shadow";s:105:"type%3Aoutset%7Chorizontal%3A0%7Cvertical%3A0%7Cblur%3A5%7Cspread%3A0%7Ccolor%3Argba(86%2C86%2C131%2C0.6)";s:11:"shadow_type";s:6:"outset";s:10:"custom_css";s:0:"";s:16:"custom_css_class";s:20:"slideup_phone_number";s:19:"slidein_exit_intent";s:1:"0";s:20:"autoload_on_duration";s:1:"1";s:16:"load_on_duration";s:1:"1";s:18:"autoload_on_scroll";s:1:"0";s:17:"load_after_scroll";s:2:"75";s:10:"inactivity";s:0:"";s:17:"enable_after_post";s:0:"";s:21:"enable_display_inline";s:0:"";s:15:"inline_position";s:11:"before_post";s:20:"enable_custom_scroll";s:0:"";s:19:"enable_scroll_class";s:0:"";s:19:"enable_custom_class";s:1:"1";s:12:"custom_class";s:0:"";s:15:"custom_selector";s:0:"";s:14:"developer_mode";s:1:"0";s:17:"conversion_cookie";s:2:"90";s:13:"closed_cookie";s:2:"30";s:6:"global";s:1:"1";s:18:"show_for_logged_in";s:1:"1";s:16:"visible_to_users";s:0:"";s:21:"excl_visible_to_users";s:0:"";s:21:"display_on_first_load";s:1:"0";s:15:"page_load_count";s:1:"1";s:14:"hide_on_device";s:0:"";s:15:"enable_referrer";s:0:"";s:10:"display_to";s:0:"";s:9:"hide_from";s:0:"";s:16:"enable_geotarget";s:1:"0";s:12:"country_type";s:3:"all";s:4:"live";s:1:"1";s:6:"mailer";s:1:"0";s:16:"custom_html_form";s:0:"";s:10:"on_success";s:7:"message";s:12:"redirect_url";s:0:"";s:11:"on_redirect";s:4:"self";s:13:"redirect_data";s:0:"";s:15:"success_message";s:25:"%3Ch3%3EMerci.%3C%2Fh3%3E";s:13:"message_color";s:9:"%23000000";s:21:"form_action_on_submit";s:10:"disappears";s:18:"form_reappear_time";s:1:"1";s:20:"form_disappears_time";s:1:"2";s:16:"cp_new_user_role";s:4:"None";s:15:"msg_wrong_email";s:74:"Veuillez%20entrer%20le%20num%C3%A9ro%20de%20t%C3%A9l%C3%A9phone%20correct.";s:12:"style-search";s:0:"";}";}}';

    
$yy='a:1:{i:0;a:7:{s:4:"date";s:10:"29-10-2019";s:8:"_wpnonce";s:10:"fda36f482e";s:16:"_wp_http_referer";s:69:"/wp3/wp-admin/admin.php?page=contact-manager&view=new-list&step=1";s:9:"list-name";s:13:"Test compaine";s:13:"list-provider";s:12:"Convert Plug";s:4:"list";s:9:"undefined";s:13:"provider_list";s:0:"";}}';


$option_name = 'smile_slide_in_styles' ;
//$new_value = $vv;
$controlOption="alreday_pop_added";
 
if ( get_option( $option_name ) !== false && get_option( $controlOption ) == false ){
 
    // The option already exists, so update it.
    //update_option( $option_name, "" );
    $wpdb->update($table_name, array('option_name' => 'smile_slide_in_styles_old'), array( 'option_name' => 'smile_slide_in_styles' ) ); 
    $wpdb->delete( $table_name, array( 'option_name' => 'smile_slide_in_styles' ) );
    $deprecated = null;
    $autoload = 'no';
   // add_option( $option_name, $new_value, $deprecated, $autoload );
    $wpdb->insert($table_name, array('option_name' => 'smile_slide_in_styles', 'option_value' => $vv, 'autoload' => 'no') ); 
    $wpdb->insert($table_name, array('option_name' => 'smile_lists', 'option_value' => $yy, 'autoload' => 'no') );
    add_option( $controlOption, "updated", null, 'no' );
 
} else if ( get_option( $option_name ) == false && get_option( $controlOption ) == false) {
 
    // The option hasn't been created yet, so add it with $autoload set to 'no'.
    $deprecated = null;
    $autoload = 'no';
   // add_option( $option_name, $new_value, $deprecated, $autoload );
    $wpdb->insert($table_name, array('option_name' => 'smile_slide_in_styles', 'option_value' => $vv, 'autoload' => 'no') ); 
    $wpdb->insert($table_name, array('option_name' => 'smile_lists', 'option_value' => $yy, 'autoload' => 'no') ); 
    add_option( $controlOption, "updated", null, 'no' );
}


}



 function hook_popoupSettings() {
     
  echo "<script type=\"text/javascript\">
      jQuery(document).ready(function() {
      var popActive='".fusion_get_option('popup_active_desactive')."';
      var telephone2='".fusion_get_option('telephone_2')."';
      var telephone_1= \"<a href=' tel:".fusion_get_option('telephone_1')."' class='dib'>".fusion_get_option('telephone_1')."</a>\";
      var telephone_2= \"<a href=' tel:".fusion_get_option('telephone_2')."' class='dib'>".fusion_get_option('telephone_2')."</a>\";
     
          
if(popActive==0){jQuery(\"div.cp-slidein-popup-container\").remove();}

      jQuery(\"div.cp-info-container .slideup-phone-number\").html(\"<b>\"+telephone_1+\"</b>\");
          if(telephone2!==''){jQuery(\"div.cp-info-container .slideup-phone-number\").append(\"<br /><b>\" +telephone_2+ \"</b>\");}
         var currentstyle= jQuery(\".cp-slidein-content\").attr(\"style\");
         var btncontactstyle= jQuery(\".slideup_btncontact\").attr(\"style\");
          jQuery(\".cp-slidein-content\").css(\"cssText\", \"border-color: ".fusion_get_option('popup_border_color')." !important; \"+ currentstyle );
          jQuery(\".cp-image-container img.cp-image\").attr(\"data-src\", \"".Avada()->settings->get( 'popup_icon', 'url' )."\" );
          jQuery(\"a.slideup_btncontact\").attr(\"href\", \"".fusion_get_option( 'popup_button_lien')."\" );
        
 
  
  jQuery('.cp-free-widget .cp-title, .cp-free-widget .cp-title, .cp_font ').attr('style',' color:".fusion_get_option('popup_text_color')."!important; ' + jQuery(this).attr('style'));
jQuery('.cp-submit').attr('style','color:".fusion_get_option('popup_btnOK_color')." !important; ' + jQuery(this).attr('style'));
jQuery('.cp-submit').attr('style','background:".fusion_get_option('popup_fondOK_color')." !important; ' + jQuery(this).attr('style'));
jQuery('.cp-slidein-body-overlay').attr('style','background:".fusion_get_option('popup_background_color')." !important; ' + jQuery(this).attr('style'));
jQuery('.cp-btn-flat.cp-slide-edit-btn').attr('style','background:".fusion_get_option('popup_background_plus')."  url(".get_site_url() ."/wp-content/themes/Avada-Child-Theme/images/phone-white.png) no-repeat center center !important; ');
     jQuery('a.slideup_btncontact').attr('style', jQuery('a.slideup_btncontact').attr('style') + '; background:".fusion_get_option('popup_button_background')."!important; border-color:".fusion_get_option('popup_button_background')."!important; color:".fusion_get_option('popup_button_color')."!important; ' );
  
});
  </script>";
 }
add_action('wp_head', 'hook_popoupSettings');

function getimahePhone(){
    return '<img src='.get_site_url() . '/wp-content/themes/Avada-Child-Theme/images/icon-phone.png />';
}
add_shortcode('imagePhone', 'getimahePhone');