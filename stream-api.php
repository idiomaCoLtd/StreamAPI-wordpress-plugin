<?php
/*
  Plugin Name: Stream-API
  Plugin URI: http://www.idioma.com
  Description: Plugin for multilingual WordPress with the possibility of professional translations via idioma Co.Ltd.
  Version: 1.0.1
  Author: BECORP.cz
  Author URI: http://www.becorp.cz
  License: GNU GPLv3
*/

/*
  This program is free software; you can redistribute it and/or modify it under the terms
  of the GNU General Public License as published by the Free Software Foundation; either
  version 2 of the License, or (at your option) any later version. This program is distributed
  in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public
  License for more details. You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
  Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

  // ========================================================
  // your authorization key and secret you've got from idioma
  // ========================================================
  $my_consumer_key = 'YOUR_CONSUMER_KEY';
  $my_consumer_secret = 'YOUR_CONSUMER_SECRET';

  session_start();

  // ========
  //
  // SETTINGS
  //
  // ========

  // KONSTANTY
  define("ICOM_PLUGIN_URL", plugin_dir_url( __FILE__ ));
  define("ICOM_HOST_PROTOCOL", (isset($_SERVER['HTTPS']) ? "https" : "http") . "://");
  define("ICOM_HOST", ICOM_HOST_PROTOCOL . $_SERVER["SERVER_NAME"] . str_replace("/wp-admin", "", dirname($_SERVER["SCRIPT_NAME"])) . "/");
  define("ICOM_ABSPATH", ICOM_HOST_PROTOCOL . $_SERVER['SERVER_NAME'] . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], "/")) . "/");
  define("ICOM_ACTUAL", ICOM_HOST_PROTOCOL . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"]);

  define("ICOM_SEP_SEG", "<hr data-icom-order='{ORDER}' data-icom-type='{TYPE}'>"); // {ORDER} = číslo segmentu, {TYPE} = (title || content || bbCodeAttr)
  define("ICOM_POST_STATUS", "icompage");
  define("ICOM_PERMALINK_STRUCT", "/%lang%/%icompage%/");

  // DATA PRO AUTORIZACI
  $api['consumer'] = array('key' => $my_consumer_key, 'secret' => $my_consumer_secret);
  $api['url']['base'] = (get_option('icom_sandbox') == "false" ? "https://tc6.idioma.com/" : "http://icom.tc6.idioma.com/");
  $api['url']['callback'] = ICOM_HOST . "wp-admin/admin.php?page=icom-settings";
  $api['url']['service'] = "oauth/tokenservice";
  $api['url']['grant'] = "oauth/tokengrant";
  $api['url']['access'] = "oauth/tokenservice";

  // segmentace textů - tagy, dle kterých se má segmentovat
  /*
  $api['segmentation']['tags'] = array('p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table');
  */

  // prefix hesla, které se používá pro automatické přihlášení při náhledech PR
  $api['proofreading']['preview']['pass'] = "idiomaPR"; // prefix je doplněn o náhodné číslo v rozmezí 100-999

  // filter na různé akce s košíky (mazání, import ...)
  $tc['carts']['filter']['for-delete'] = array("Setup", "EstimationStarted", "EstimationFinished");
  $tc['carts']['filter']['for-pages'] = array("Setup", "EstimationStarted", "EstimationFinished", "TranslationStarted", "TranslationPartiallyFinished", "TranslationFinished", "ProofreadingStarted", "ProofreadingPartiallyFinished", "ProofreadingFinished"); // při jakých stavech se má na přehledu stránek zobrazit ikonka "in cart"
  $tc['carts']['filter']['for-refresh'] = array("EstimationStarted", "TranslationStarted", "TranslationPartiallyFinished", "ProofreadingStarted", "ProofreadingPartiallyFinished");
  $tc['carts']['filter']['for-estimation'] = array("Setup");
  $tc['carts']['filter']['for-order'] = array("EstimationFinished");
  /*
  $tc['carts']['filter']['for-lang-cancel'] = array("Setup", "EstimationStarted", "EstimationFinished", "TranslationStarted", "TranslationPartiallyFinished");
  $tc['carts']['filter']['for-import'] = array("TranslationPartiallyFinished", "TranslationFinished", "ProofreadingPartiallyFinished", "ProofreadingFinished");
  $tc['carts']['filter']['for-lang-proofreading'] = array("TranslationPartiallyFinished", "TranslationFinished");
  $tc['carts']['filter']['for-import-proofreading'] = array("ProofreadingPartiallyFinished", "ProofreadingFinished");
  */
  $tc['carts']['filter']['for-close'] = array("ProofreadingFinished");

  // ====================
  //
  // INICIALIZAČNÍ FUNKCE
  //
  // ====================

  // AKTIVACE PLUGINU
  function icom_activate() {
    global $wpdb;

    //zavislost na tomto pluginu
    add_option('icom_installed', 'true', 'yes');
    // sandbox zapnut (testovací účet)
    add_option('icom_sandbox', 'true', 'yes');

    // pokud je pole prázdné, plugin není autorizován
    add_option('icom_token', '', 'yes');

    // defaultní jazyk stránky (nastaven na en-GB)
    add_option('icom_language_default', 'en-GB', 'yes');
    add_option('icom_language_default_front', 'en-GB', 'yes');
    // všechny jazyky, ve kterých je web dostupný (nastaven na en-GB)
    add_option('icom_language_selected', array('en-GB'), 'yes');
    add_option('icom_language_selected_front', array('en-GB'), 'yes');
    // jazyk, který byl v URL na front-endu (používá se při přesměrovávání v loading_pages.php)
    add_option('icom_language_last', '', 'yes');

    // kontrola neznámých BBtagů
    add_option('icom_bbcode_check', 'true', 'yes');

    // targetové jazyky vybranné v toolboxu (používá se v případě "refreshe" max. WC a PRICE)
    add_option('icom_toolbox_targets', '', 'yes');
    // vybranný košík v případě pre-estimace
    add_option('icom_toolbox_cart', '', 'yes');
    // informační hlášky používané v toolboxu
    add_option('icom_toolbox_msg', '', 'yes');

    // kontroluje, zda-li je struktura permalinků nastavena, tak jak ji potřebuje iCom
    add_option('icom_rewrite_permastruct', 'false', 'yes');

    $sql = "CREATE TABLE `" . $wpdb->prefix . "icom_segments` (
            `id` bigint(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `id_post` bigint(10) UNSIGNED NOT NULL DEFAULT '0',
            `src_lang` varchar(16) NOT NULL DEFAULT '',
            `src_text` text collate utf8_czech_ci not null default '',
            `trg_lang` varchar(16) NOT NULL DEFAULT '',
            `trg_text` text collate utf8_czech_ci not null default '',
            `poradi` int(10) UNSIGNED NOT NULL DEFAULT '0',
            `active` tinyint(1) UNSIGNED NOT NULL DEFAULT '1'
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=0;

          CREATE TABLE `" . $wpdb->prefix . "icom_translations` (
            `id` bigint(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `id_post` bigint(10) UNSIGNED NOT NULL DEFAULT '0',
            `id_post_orig` bigint(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Urcuje propojeni stranek pri zmene def. jazyka',
            `id_post_parent` bigint(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID sourcove stranky z wp_posts',
            `post_parent_valid` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT 'POST_PARENT z wp_posts',
            `lang` varchar(16) NOT NULL DEFAULT '',
            `preview` tinyint(1) NOT NULL DEFAULT '0',
            `post_status` varchar(255) NOT NULL DEFAULT '',
            UNIQUE KEY `id_post` (`id_post`,`id_post_orig`,`id_post_parent`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=0;

          CREATE TABLE `" . $wpdb->prefix . "icom_translations_synch` (
            `id_post1` bigint(10) UNSIGNED NOT NULL DEFAULT '0',
            `id_post2` bigint(10) UNSIGNED NOT NULL DEFAULT '0',
            `synch` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
            PRIMARY KEY (`id_post1`,`id_post2`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=0;

          CREATE TABLE `" . $wpdb->prefix . "icom_tc_carts` (
            `id` bigint(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `datetime` datetime not null default '0000-00-00 00:00:00',
            `name` varchar(255) collate utf8_czech_ci not null default '',
            `uri` varchar(255) not null default '',
            `state` varchar(32) not null default '',
            `source_lang` varchar(16) not null default ''
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=0;

          CREATE TABLE `" . $wpdb->prefix . "icom_tc_items` (
            `id` bigint(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `id_cart` bigint(10) UNSIGNED NOT NULL DEFAULT '0',
            `datetime` datetime not null default '0000-00-00 00:00:00',
            `name` varchar(255) collate utf8_czech_ci not null default '',
            `customID` varchar(255) collate utf8_czech_ci not null default '',
            `uri` varchar(255) not null default '',
            `wordcount` bigint(10) unsigned not null default '0',
            `source_lang` varchar(16) not null default '',
            `target_lang` varchar(255) not null default '',
            `target_lang_import` varchar(255) not null default '',
            `target_lang_import_real` varchar(255) not null default '',
            `proofreading` tinyint(1) unsigned not null default '0',
            `proofreading_lang_import` varchar(255) not null default ''
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=0;

          CREATE TABLE `" . $wpdb->prefix . "icom_bbcode` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `name` VARCHAR(64) NOT NULL DEFAULT '',
            `attrs` VARCHAR(255) NOT NULL DEFAULT '',
            `regex` VARCHAR(255) NOT NULL DEFAULT ''
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci AUTO_INCREMENT=0;";

    dbDelta($sql);

    // již vytvořené posts přidáme do `icom_translations`
    $pages = $wpdb->get_results("SELECT ID FROM " . $wpdb->prefix . "posts WHERE (post_type = 'page' OR post_type = 'post') AND post_status != 'auto-draft'");
    $lang = get_option('icom_language_default');
    foreach($pages as $val) {
      dbDelta("INSERT INTO " . $wpdb->prefix . "icom_translations (id_post, id_post_orig, lang) VALUES ('" . $val->ID . "', '" . $val->ID . "', '" . $lang . "')");
    }

    update_option('icom_installed', 'true');
  }

  // DEAKTIVACE PLUGINU
  function icom_deactivate() {
    update_option('icom_token', '');
    update_option('icom_sandbox', '');
    update_option('icom_installed', 'false');
  }

  // ODINSTALACE PLUGINU
  function icom_uninstall() {
    global $wpdb;

    delete_option('icom_installed');
    delete_option('icom_sandbox');
    delete_option('icom_token');
    delete_option('icom_language_default');
    delete_option('icom_language_default_front');
    delete_option('icom_language_selected');
    delete_option('icom_language_selected_front');
    delete_option('icom_language_last');
    delete_option('icom_toolbox_targets');
    delete_option('icom_toolbox_cart');
    delete_option('icom_toolbox_msg');
    delete_option('icom_rewrite_permastruct');

    $sql = "DROP TABLE IF EXISTS `" . $wpdb->prefix . "icom_segments`;
            DROP TABLE IF EXISTS `" . $wpdb->prefix . "icom_translations`;
            DROP TABLE IF EXISTS `" . $wpdb->prefix . "icom_translations_synch`;
            DROP TABLE IF EXISTS `" . $wpdb->prefix . "icom_tc_carts`;
            DROP TABLE IF EXISTS `" . $wpdb->prefix . "icom_tc_items`;
            DROP TABLE IF EXISTS `" . $wpdb->prefix . "icom_bbcode`;";

    dbDelta($sql);
  }

  // VLOŽENÍ ICOM POLOŽKY DO MENU
  function icom_menu() {
    add_menu_page('Stream-API menu', 'Stream-API', 'manage_options', 'icom', 'icom_cart', plugin_dir_url(__FILE__) . 'img/stream-api.png', 30);
		add_submenu_page('icom', 'Stream-API carts', 'Carts', 'manage_options', 'icom', 'icom_cart');
		add_submenu_page('icom', 'Stream-API settings', 'Settings', 'manage_options', 'icom-settings', 'icom_settings');
  }
  add_action('admin_menu', 'icom_menu');

  // AKTIVACE VŠECH HOOKŮ PRO ADMINISTRACI
  function icom_init_hooks_admin() {
    wp_enqueue_script('icom-js-functions', plugin_dir_url(__FILE__) . 'js/stream-api.functions.js', array(), '', true); // main JS functions
    wp_enqueue_style('icom-style', plugin_dir_url(__FILE__) . 'css/stream-api.css'); // main CSS

    // icom_ajax.php
    add_action('delete_post', 'icom_delete_post');
    add_action('save_post', 'icom_save_post', 11);

    // icom_toolbox.php
    add_action('add_meta_boxes', 'icom_toolbox');

    //záložka Pages
    add_filter('manage_pages_columns', 'icom_custom_pages_columns');
    add_filter('manage_pages_custom_column', 'icom_custom_pages_columns_content', 10, 2);

    //záložka Posts
    add_filter('manage_posts_columns', 'icom_custom_pages_columns');
    add_filter('manage_posts_custom_column', 'icom_custom_pages_columns_content', 10, 2);

    //přidání CSS stylu do hlavičky adminstrace
    add_action('admin_head', 'icom_custom_pages_column_width');

    //úprava dotazu vybírání stránek v Pages i Posts
    add_action('pre_get_posts', 'icom_exclude_target_pages');

    //data pro modální okno košíku
    add_action('in_admin_footer', 'icom_modal_carts');

    // uložní slug stránky, bez přidávní číselného postfixu v případě duplicity slugů
    add_filter('wp_unique_post_slug', 'icom_unique_slug', 10, 6);

    // úprava selectu s rodičovskými stránkami při editoaci stránky
    add_filter('page_attributes_dropdown_pages_args', 'icom_toolbox_exlude_parents', 10, 2);
  }

  // AKTIVACE VŠECH HOOKŮ PRO FRONT-END
  function icom_init_hooks() {
     // filter pro všechny odkazy na "pages"
    add_filter('page_link', 'icom_edit_permalink');
    // filter pro všechny odkazy na "posts"
    add_filter('post_link', 'icom_edit_permalink');
    // vyhození stránek neodpovídajících aktuálnímu jazyku
    add_action('pre_get_posts', 'icom_exclude_pages', 10);
    // načítání stránky na front-endu
    add_action('pre_get_posts', 'icom_load_page_by_lang', 2);

    // icom_proofreading.php
    wp_enqueue_script('jquery-ui', 'https://code.jquery.com/ui/1.8.1/jquery-ui.min.js', array(), '', true);
    wp_enqueue_script('icom-js-snotes', plugin_dir_url(__FILE__) . 'js/plugin.sNotes.js', array(), '', true);
    wp_enqueue_style('icom-style-snotes', plugin_dir_url(__FILE__) . 'css/sNotes.css');

    add_action('wp_footer', 'icom_proofreading', 99);
  }

  // REGISTRACE NOVÉHO POST_TYPE 'icompage' - PRO POUŽÍVÁNÍ REF. STRÁNEK V iQUBE
  function icom_register_post_status() {
    register_post_status(ICOM_POST_STATUS, array(
      'label'                     => 'Stream-API',
      'public'                    => true,
      'exclude_from_search'       => true,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => false,
    ));
  }
  add_action('init', 'icom_register_post_status');

  // ===========================================
  //
  // PŘILINKOVÁNÍ EXTERNÍCH SOUBORŮ WP a PLUGINU
  //
  // ===========================================

  // PŘILINKOVÁNÍ EXTERNÍCH SCRIPTŮ WORDPRESSU
  require_once ABSPATH . "wp-admin/includes/upgrade.php"; // kvůli dbDelta()
  require_once ABSPATH . "wp-includes/pluggable.php"; // kvůli _wpnonce

  // AKTIVACE HOOKŮ PRO OD/INSTALACI/AKTIVACI PLUGINU
  register_activation_hook(__FILE__, 'icom_activate');
  register_deactivation_hook(__FILE__, 'icom_deactivate');
  register_uninstall_hook(__FILE__, 'icom_uninstall');

  // PŘILINKOVÁNÍ VŠECH PODSOUBORŮ PLUGINU
  require_once dirname(__FILE__) . '/oauth.class.php';
  require_once dirname(__FILE__) . '/oauth-tc.class.php';

  require_once dirname(__FILE__) . '/libs/stream-api_functions.debug.php';
  require_once dirname(__FILE__) . '/libs/stream-api_functions.main.php';
  require_once dirname(__FILE__) . '/libs/stream-api_functions.tc.php';
  require_once dirname(__FILE__) . '/libs/stream-api_functions.db.php';
  require_once dirname(__FILE__) . '/libs/stream-api_functions.api.php';
  require_once dirname(__FILE__) . '/stream-api_loading_pages.php';

  // ==========================
  //
  // INICIALIZACE SAMOTNÝCH DAT
  //
  // ==========================

  $icom_lang_default = get_option('icom_language_default');
  $icom_lang_default_front = get_option('icom_language_default_front');
  $icom_lang_selected = get_option('icom_language_selected');
  sort($icom_lang_selected);
  $icom_lang_selected_front = get_option('icom_language_selected_front');
  sort($icom_lang_selected_front);

  // INICIALIZACE DAT PRO FRONT-END
  if (is_admin() == false) {

    require_once dirname(__FILE__) . '/stream-api_proofreading.php';
    //temporary not used
    //require_once dirname(__FILE__) . '/stream-api_lang_switcher_widget.php';
    add_action('init', 'icom_init_hooks'); // inicializace hooků pro front-end

  // INICIALIZACE DAT PRO BACK-END
  } else {

    require_once dirname(__FILE__) . '/stream-api_ajax.php';
    require_once dirname(__FILE__) . '/stream-api_cart.php';
    require_once dirname(__FILE__) . '/stream-api_settings.php';
    require_once dirname(__FILE__) . '/stream-api_toolbox.php';
    require_once dirname(__FILE__) . '/stream-api_custom_pages.php';
    add_action('admin_init', 'icom_init_hooks_admin'); // inicializace hooků pro back-end

    $icom_lang_all = icom_api_get_languages();
    $icom_lang_pricelist = icom_api_get_pricelist($icom_lang_default);
  }

  add_action('init', 'icom_lang_rewrite_tag', 10);
  add_action('init', 'icom_lang_rewrite_rule', 10);
?>
