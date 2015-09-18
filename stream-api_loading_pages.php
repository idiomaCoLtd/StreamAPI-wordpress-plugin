<?php
/**
 * Přidá parametr lang=LANG_CODE za každý odkaz na stránce
 *
 * @param String $link upravovaný odkaz
 * @return String upravený $link
 */
function icom_edit_permalink($link) {
    if (icom_is_permalink_struct()) {
        return ICOM_ABSPATH . icom_current_lang() . "/" . str_replace(ICOM_ABSPATH, "", $link);
    } else {
        return ICOM_ABSPATH . str_replace(ICOM_ABSPATH, "", $link) . "&lang=" . icom_current_lang();
    }
}

/**
 * Zjistí aktuálně nastavený jazyk pro zobrazování stránek
 * @return String lang_code
 */
function icom_current_lang() {
  global $icom_lang_selected_front, $icom_lang_default_front;

  $lang = get_query_var('lang');
  if (empty($lang) && !empty($_GET['lang'])) {
    $lang = $_GET['lang'];
  }

  if (empty($lang)) return $icom_lang_default_front;

  $lang_exists = preg_grep("/" . $lang . "/i", $icom_lang_selected_front); // musí být regulárem namísto in_array(), kvůli case-insensitive
  if (!empty($lang_exists)) {
    return $lang;
  } else {
    return $icom_lang_default_front;
  }
}

/**
 * Pokud je jazyk ulozen v URL vrati TRUE, jinak FALSE
 * #not used
 * @return boolean
 */
function icom_lang_in_url() {
    $l1 = get_query_var('lang');
    $l2 = $_GET['lang'];
    if ((isset($l1) && !empty($l1)) || (isset($l2) && !empty($l2))) {
        return TRUE;
    } else {
        return FALSE;
    }
}

/**
 * Vybere z DB stranky ktere nejsou v aktualne zvolenem jazyce
 * nebo maji priznak PREVIEW
 *
 * @global wpdb $wpdb
 * @return int[] ID vyloucenych stranek
 */
function icom_get_exclude_pages() {
    global $wpdb;

    $current_lang = icom_current_lang();
    $pages_for_exclude = $wpdb->get_results(" SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE lang != '" . $current_lang . "' OR preview = '1'");

    foreach ($pages_for_exclude as $page) {
        $exclude_pages[] = $page->id_post;
    }
    return $exclude_pages;
}

/**
 * Vyloučí z výpisu stránky podle aktuálně zvoleného jazyka
 *
 * Slouzi nejspise k zobrazeni menu na frontendu
 */
function icom_exclude_pages($query) {
    $exclude_pages = icom_get_exclude_pages();
    $query->set('post__not_in', $exclude_pages);
    return $query;
}

/**
 * Zjisti jestli jsou nastavene cool URL (mod_rewrite)
 *
 * @return boolean
 */
function icom_is_permalink_struct() {
    $structure = get_option('permalink_structure');
    if (empty($structure)) {
        return false;
    } else {
        return true;
    }
}

/**
 * Vrátí strukturu url dle toho jestli jsou nebo nejsou nastaveny permalinky
 * @param  $lang
 * @param  $page
 * @return boolean
 */
function icom_get_permalink_struct($lang, $page) {
    $permalink = icom_is_permalink_struct();
    if ($permalink == false)
        return array('permalink' => false, 'value' => '?&' . $page . '&lang=' . $lang);
    else
        return array('permalink' => true, 'value' => $lang . "/" . $page);
}

/**
 * Question: Co dela tahle funkce a k cemu se pouziva??
 *
 * Zjisti jestli je dana stranka (z tabulky posts) typu 'page'
 * @return boolean
 */
/*
function icom_get_type($type) {
    switch ($type) {
        case "post":
            break;

        default:
            if (get_query_var('page_id') || get_query_var('pagename'))
                return true;
            else if (!get_query_var('p') && !get_query_var('name'))
                return true;
            else
                return false;
            break;
    }
}
*/

/**
 * Zjisti typ(post, page, ...) stranky [post_type] podle ID
 *
 * @global wpdb $wpdb
 * @param int $id
 * @return string
 */
function icom_get_post_type($id) {
    global $wpdb;
    return $wpdb->get_var("SELECT post_type FROM " . $wpdb->prefix . "posts WHERE ID = " . $id . "");
}

/**
 * Zjistí post_status (publish, draft, ...) stránky
 *
 * @global wpdb $wpdb
 * @param int $id
 * @return string
 */
function icom_get_post_status($id){
    global $wpdb;
    return $wpdb->get_var("SELECT post_status FROM " . $wpdb->prefix . "posts WHERE ID = " . $id . "");
}

/**
 * Zjisti jazyk stranky podle jejiho ID
 *
 * @global wpdb $wpdb
 * @param int $id
 * @return string
 */
function icom_get_post_lang($id) {
    global $wpdb;
    return $wpdb->get_var("SELECT lang FROM " . $wpdb->prefix . "icom_translations WHERE id_post = " . $id . "");
}

/**
 * Sestavi permalink pro stranky typu post. Pokud je zjisteny jiny typ (napr. pages),
 * tak fce vrati get_permalink()
 *
 * @global wpdb $wpdb
 * @global string $icom_lang_default_front
 * @param int $id
 * @return string $permalink
 */
function icom_get_post_permalink($id) {
    global $wpdb, $icom_lang_default_front;

    $lang = icom_get_post_lang($id);
    if (!isset($lang) || empty($lang)) {
      $lang = $icom_lang_default_front;
    }

    /* TO-DO: zde je ješte potřeba dodělat aby to vracelo jazyk v případě, že nejsou povolené COOL URL
     */
    if (icom_get_post_type($id) !== "post") {
      $permalink = get_permalink($id);
      if ((stripos($permalink, $lang)) !== FALSE) {
        return $permalink;
      } else if ((stripos($permalink, ICOM_HOST)) !== FALSE) {
        return substr_replace($permalink, $lang . "/", strlen(ICOM_HOST), 0);
      } else {
        return NULL;
      }
    }

    $slug = $wpdb->get_var("SELECT post_name FROM " . $wpdb->prefix . "posts WHERE ID = " . $id . "");
    $permalink = rtrim(str_replace("/wp-admin/", "", ICOM_ABSPATH), "/");
    $permalink = rtrim($permalink, "/") . "/" . $lang . "/" . $slug . "/";

    return $permalink;
}

/**
 * Ziska ID aktualni stranky stranky
 * @return type
 */
function icom_get_post_id() {
    global $wpdb, $icom_lang_default;

    if (get_query_var('p')) {
        return get_query_var('p');
    } else if (get_query_var('page_id')) {
        return get_query_var('page_id');
    } else if (get_query_var('icompage') || get_query_var('pagename') || get_query_var('name')) {
        $query_string = get_query_var('icompage');
        if (empty($query_string)) $query_string = get_query_var('pagename');
        if (empty($query_string)) $query_string = get_query_var('name');

        $slug_parts = explode("/", $query_string);
        $slug_count = count($slug_parts);
        $post_name = $slug_parts[$slug_count - 1]; // ziska ID stranky ze slugu (post_name)
        //zkusi ziskat ID stranky z tabulky [icom_transtations]
        //podle aktualniho jazyka a podle [post_name] z tabulky [posts]
        if (stripos($post_name, "preview-page") !== false)
            $where = "t.preview = '1'";
        else
            $where = "t.lang = %s AND t.preview = '0'";
        $qry = "SELECT id_post FROM " . $wpdb->prefix . "icom_translations t, " . $wpdb->prefix . "posts p WHERE p.id = t.id_post AND post_name = '" . $post_name . "' AND (" . $where . ") ORDER BY t.id DESC";
        $id = $wpdb->get_var($wpdb->prepare($qry, icom_current_lang()));

        //pokud neexistuje stranka v tab. [icom_transtations] tzn. neni dostupna v hledanem jazyce
        //zkusi ziskat ID nejake stranky se stejnym slugem z tabulky [posts]
        if (is_null($id)) {
            $id = $wpdb->get_var($wpdb->prepare($qry, $icom_lang_default));
        }
        if (is_null($id))
            return -1;
        else
            return $id;
    } else {
        return null;
    }
}

/**
 * Pokud ID stranky a jazyk nejsou shodne (podle tabulky icom_translations),
 * tak se pokusi ziskat ID stranky v danem jazyce a presmerovat na ni.
 * Kdyz neni zadne ID nalezene nepresmeruje.
 *
 * @global wpdb $wpdb
 * @param WP_Query $query
 * @return WP_Query
 */
function icom_load_page_by_lang($query) {
    global $wpdb, $icom_lang_default;

    if (!$query->is_main_query()) {
      return $query;
    }

    $is_permalink = icom_is_permalink_struct(); // TRUE pokud se pouzivaji COOL URL
    $current_lang = icom_current_lang(); // Jazyk aktualne uvedeny v URL
    $last_lang = get_option("icom_language_last"); // Jazyk, ktery byl v URL naposledy (nevim kvuli cemu jsme ho museli zavest)

    $is_homepage = false;
    $is_page = false;
    $is_preview = false;

    $id = icom_get_post_id(); // ID aktualni stranky
    $id_homepage = get_option('page_on_front'); // ID homepage

    // pokud se jedná o preview stránku (která se používá při PR), jen přesměruje na správný jazyk
    $preview = $wpdb->get_var("SELECT lang FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $id . "' AND preview = '1'");
    if ($preview && empty(get_query_var('lang'))) {
        $query->set('lang', $preview);
        wp_redirect(icom_get_post_permalink($id) . "?icom-previewHash=" . $_GET['icom-previewHash'] . "&icom-targetLang" . $_GET['icom-targetLang']);
        exit();
    }

    $link_homepage = get_permalink($id);
    // pokud se jedná o úvodní stránku defaultního jazyka a nemá v sobě jazyk
    if ($is_permalink == true && $id == $id_homepage && ICOM_ABSPATH != $link_homepage) {
      wp_redirect($link_homepage);
      exit();
    }

    // 404ka
    if ($id == -1) {

        if ($id_homepage > 0) {

          $query->is_404 = TRUE;
          $query->is_post_page = FALSE;

        } else {

          $query->is_404 = FALSE;
          $query->is_post_page = TRUE;

          $id = null;

        }

    // $id je NULL pokud v URL není ID ani slug
    } else if (!isset($id)) {

      $is_homepage = true;
      //zjistim ID postu na zaklade jazyka a ID homepage stranky
      $id = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations  WHERE lang = '" . $current_lang . "' AND id_post_orig = '" . $id_homepage . "' AND preview = '0'");

      //jelikož homepage je na specifický adrese /[LANG]/,
      //tak WP řeknu: to není homepage, ale obyčejná page, tak přestan dělat problémy
      if (isset($id)) {
        $query->is_home = FALSE;
        $query->is_page = TRUE;

        $query->set("p", $id);
        $query->set("page_id", $id);

      //pokud homepage ve zvolenem jazyce neexistuje, je potreba vratit 404 Not Found
      } else {
        $query->is_home = FALSE;
        $query->is_404 = FALSE;
        $query->is_post_page = TRUE;
      }

    } else {

        $is_page = (icom_get_post_type($id) == "post" ? false : true);

        $query->is_home = FALSE;
        $query->is_404 = FALSE;
        $query->is_admin = FALSE;

        if ($is_page == false) {
            $query->is_page = FALSE;
            $query->is_single = TRUE;

            $query->set("p", $id);
            $query->set("page_id", 0);
        } else {
            $query->is_page = TRUE;
            $query->is_single = FALSE;

            $query->set("p", 0);
            $query->set("page_id", $id);
        }

        $query->is_archive = FALSE;
        $query->is_preview = get_query_var('preview');
        $query->is_date = FALSE;
        $query->is_year = FALSE;
        $query->is_month = FALSE;
        $query->is_time = FALSE;
        $query->is_author = FALSE;
        $query->is_category = FALSE;
        $query->is_tag = FALSE;
        $query->is_tax = FALSE;
        $query->is_search = FALSE;
        $query->is_feed = FALSE;
        $query->is_comment_feed = FALSE;
        $query->is_trackback = FALSE;
        $query->is_comments_popup = FALSE;
        $query->is_attachment = FALSE;
        $query->is_singular = TRUE;
        $query->is_robots = FALSE;
        $query->is_paged = FALSE;

    }

    $url_array = explode("?", ICOM_ACTUAL);
    $link = icom_get_post_permalink($id);
    $post_status = icom_get_post_status($id); // draft, publish, future, ...

    // pokud jsou nastaveny permalinky a je v url ?lang=jazyk, přesměrujeme, aby tam nebyl
    // anebo pokud tam není a měl by být (může se stát u default_jazyka)
    if ($id != null && $link != null && $is_permalink == true && (isset($_GET['p']) || isset($_GET['page_id']) || isset($_GET['lang']) || $url_array[0] != $link) && $post_status != "draft") {
        $url = "?" . $url_array[1];
        $url = preg_replace("/[?|&]p(=[^&]*)?/", "", $url);
        $url = preg_replace("/[?|&]lang(=[^&]*)?/", "", $url);
        $url = preg_replace("/[?|&]page_id(=[^&]*)?/", "", $url);
        //$url = trim($url, "&");
        //$url = preg_replace("/[&]+/", "&", $url);
        $url = trim($link . "?" . $url, "?");

        wp_redirect($url);
        exit();
    }

    // ziskani zaznamu stranky z ICOM_TRANSLATION
    $post = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . icom_save_db($id) . "'");
    // Pokud se jazyk stranky, kterou chceme nacist (zobrazit) neshodujes jazykem uvedenym v URL
    if ($id != null && $is_permalink == true && strcasecmp($post->lang, $current_lang) != 0) {

        // ziskani noveho ID pro presmerovani
        if ($post->id_post_parent == 0) {
          $new_post_id = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE (id_post_orig = '" . icom_save_db($id) . "' OR id_post_parent = '" . icom_save_db($id) . "') AND lang = '" . $current_lang . "' AND preview = '0'");
        } else {
          $new_post_id = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE (id_post_orig = '" . $post->id_post_parent . "' OR id_post_parent = '" . $post->id_post_parent . "') AND lang = '" . $current_lang . "' AND preview = '0'");
        }

        // presmerovani na novou stranku, se spravnym jazykem
        if (isset($new_post_id)) {
          update_last_lang_option($current_lang);
          wp_redirect(icom_get_post_permalink($new_post_id));
          exit();
        }

    } else if ($id != null && strcasecmp($last_lang, $current_lang) != 0) {

        update_last_lang_option($current_lang);
        $is_page == true ? $query->set('page_id', $id) : $query->set('p', $id);

        /*
        if ($is_page == true) {
          $query->set('page_id', $id);
        } else if ($query->is_main_query()) {
          $query->set('p', $id);
        }
        */

    } else if ($id != null) {

      echo "aaa";
        update_last_lang_option($current_lang);
        $is_page == true ? $query->set('page_id', $id) : $query->set('p', $id);

    }

    // pokud jsou vypnuty permalinky
    if ($is_permalink == false) {

      $id2 = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE id_post_parent = '" . $id . "' AND lang = '" . $current_lang . "' AND preview = '0'");
      if (!isset($id2)) {
        $parent = $wpdb->get_var("SELECT id_post_parent FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $id . "' AND lang = '" . $current_lang . "' AND preview = '0'");
        if (!isset($parent)) $id = null;
      } else {
        $id = $id2;
      }

      if (isset($id)) {
        $is_page == true ? $query->set('page_id', $id) : $query->set('p', $id);
      } else {
        $query->is_404 = TRUE;
      }

    }

    if ($id == null) $query->is_404 = TRUE;

    return $query;
}

/**
 * Aktualizuje polozku 'icom_last_lang' v databazi
 * @param String $lang
 */
function update_last_lang_option($lang) {
    update_option("icom_language_last", $lang);
}

// přidání nového tagu %lang%, který se pak používá ve struktuře permalinků v nastavení
function icom_lang_rewrite_tag() {
    add_rewrite_tag('%lang%', '^([a-zA-Z]{2,3}(-[a-zA-Z]{2,4})?(-[a-zA-Z]{2})?)');
    add_rewrite_tag('%icompage%', '([-a-zA-Z_]+)$');
}

// přidání htaccess pravidla, pro odchycení jazyka (ten musí být ve tvaru (aaz-aazz?-az?)
function icom_lang_rewrite_rule() {
    add_rewrite_rule('^([a-zA-Z]{2,3}(-[a-zA-Z]{2,4})?(-[a-zA-Z]{2})?)/(.+)', 'index.php?lang=$matches[1]&icompage=$matches[4]', 'top');
}
