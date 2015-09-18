<?php
  /**
   * přesměrování ze všech stránek pluginu na stránku s autorizaci
   * - pokud nemáme token, musíme donutit uživatele, aby se autorizoval
   */
  if ($_GET['page'] == "icom") {
    $token = get_option('icom_token');
    if (empty($token)) {
      header("location:" . $api['url']['callback']);
      exit();
    }
  }

  // === icom_settings.php ===

  /**
   * změna typu účtu
   * sandbox X ostrý účet
   */
  if (isset($_GET['icom-authSandbox'])) {

    $nonce = $_REQUEST['_wpnonce'];
    if (!wp_verify_nonce($nonce, 'icom-settings-authSandbox')) {
      die('Not authorized process');
    }

    update_option('icom_token', '');
    update_option('icom_sandbox', $_GET['icom-authSandbox']);

    unset($_SESSION['request_token']);
    unset($_SESSION['request_token_secret']);

    if ($_GET['icom-authSandbox'] == "false") {
      $wpdb->get_results("TRUNCATE TABLE " . $wpdb->prefix . "icom_segments");
    }

    header("location:" . $api['url']['callback']);
    exit();
  }

  // === / icom_settings.php ===

  // === icom_cart.php ===

  /**
   * stáhnutí estimačního PDF nebo faktury
   */
  if (isset($_GET['icom-cartPdf'])) {
    icom_api_get_file($_GET['icom-cartPdf']);
    exit();
  }

  // === / icom_cart.php ===

  // === icom_proofreading.php ===

  /**
   * uložení poznámek z knihovny sNotes z proofreadingu na TC
   * - uloží vytvořené poznámky na TC v serializovaném stringu
   */
  if (isset($_POST['icom-snotesData'])) {
    icom_api_custom_data($_POST['icom-cartItem'], $_POST['icom-targetLang'], $_POST['icom-snotesData']);
    exit();
  }

  // === / icom_proofreading.php ===

  /**
   * duplikace sourcové stránky u targetové stránky
   * - používáme v případě, chceme-li target stránku oddělit od současné source stránky (typicky např. u stránek s obch. podmínkami)
   */
  if (isset($_GET['icom-sourceDuplicate'])) {
    $post = icom_save_db($_GET['post']);
    $sourceDuplicate = icom_save_db($_GET['icom-sourceDuplicate']);

    $columns = $wpdb->get_results("SHOW COLUMNS FROM " . $wpdb->prefix . "posts");

    $cols = array();
    foreach($columns as $val) {
      switch($val->Field) {
        case "ID":
        case "guid":
        case "comment_count":
          $cols[] = "NULL";
          break;

        case "post_date":
        case "post_date_gmt":
        case "post_modified":
        case "post_modified_gmt":
          $cols[] = "NOW()";
          break;

        default:
          $cols[] = $val->Field;
          break;
      }
    }

    $wpdb->query("INSERT INTO " . $wpdb->prefix . "posts (SELECT " . implode(", ", $cols) . " FROM " . $wpdb->prefix . "posts WHERE id = '" . $sourceDuplicate . "')");
    $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_title = CONCAT(post_title, ' 2'), guid = '" . ICOM_HOST . "?p=" . $wpdb->insert_id . "' WHERE id = '" . $wpdb->insert_id . "'");

    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations SET id_post_parent = '" . $wpdb->insert_id . "' WHERE id_post = '" . $post . "'");

    header("location: " . ICOM_HOST . "wp-admin/post.php?action=edit&post=" . $post);
    exit();
  }

  /**
   * označení dvojice stránek jako synchronizované
   * - volá se při kliknutí na odkaz v toolboxu (či jinde), že má být stránka označena jako synchronizovaná
   */
  if (isset($_GET['icom-sourceTargetSynch'])) {
    icom_set_synch($_GET['post'], $_GET['icom-sourceTargetSynch']);

    header("location: " . ICOM_HOST . "wp-admin/post.php?action=edit&post=" . $_GET['post']);
    exit();
  }

  /**
   * vytvoření nové/ých stránky/ek překladem sourcové stránky
   * - vloží do košíku nasegmentovanou sourcovou stránku, aby z ní mohla být vytvořena targetová/é stránka/y
   */
  if (isset($_GET['icom-targetCreate-by'])) {
    foreach($_GET['icom-targetCreate-by'] as $val) {

      $targets = array();
      foreach($_GET['icom-targetLangs'] as $v) {
        $item = $wpdb->get_var("SELECT c.state FROM " . $wpdb->prefix . "icom_tc_items i, " . $wpdb->prefix . "icom_tc_carts c WHERE c.id = i.id_cart AND customID = '" . $val . "' AND target_lang LIKE '%" . $v . "%'");
        if ($item == null || $item == "Closed") {
          $targets[] = $v;
        } else {
          set_msg('error', 'Page #' . $val . ' is already in not-closed cart in translation ' . $v);
      } }
      if (empty($targets)) continue;

      $item = $wpdb->get_row("SELECT post_title, post_content FROM " . $wpdb->prefix . "posts WHERE ID = '" . $val . "'");
      icom_api_add_item($val, $_GET['icom-cart'], $_GET['icom-sourceLang'], $targets, $_GET['icom-proofreading'], $item->post_title, $item->post_content);
    }

    if (isset($_GET['post'])) {
      header("location: " . ICOM_HOST . "wp-admin/post.php?post=" . $_GET['post'] . "&action=edit");
    } else {
      header("location: " . ICOM_HOST . "wp-admin/edit.php?post_type=page");
    }
    exit();
  }

  /**
   * detekce notifikační URL o ukončení estimace nebo překladu
   */
  if (isset($_GET['icom-cartNotif'])) {
    switch($_GET['icom-cartNotif']) {
      case "estimation":
        icom_tc_change_state($_GET['icom-cart'], 'EstimationFinished'); break;
      case "order":
        icom_tc_sync_state($_GET['icom-cart']); break;
      case "proofreading":
        icom_tc_sync_state($_GET['icom-cart']); break;
      default:
        break;
    }
    exit();
  }

  /**
   * import dat z preview do ostré stránky
   * - zkopíruje data z preview, se kterým se pracovalo po dobu procesu překladu a proofu a vytvoří/updatne reálnou stránku
   */
  if (isset($_GET['icom-importItem-real'])) {
    if (isset($_GET['icom-importItem-to'])) {
      $post_id = (int)$_GET['icom-importItem-to'];
      //$change_permalink = false;
    } else {
      $post_id = icom_save_post(false);
      //$change_permalink = true;
    }

    // update obsahu stránky
    $item = $wpdb->get_row("SELECT post_title, post_content FROM " . $wpdb->prefix . "posts WHERE ID = '" . $_GET['icom-importItem-real'] . "'");
    icom_import_update_content($post_id, $item->post_title, $item->post_content, "draft", $_GET['icom-targetCreate-of']);

    // update preview v translations
    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations SET id_post_orig = '" . $post_id . "' WHERE id_post = '" . $_GET['icom-importItem-real'] . "'");
    // update post_status na originální
    icom_db_set_orig_post_status($post_id);

    // update importovaných jazyků
    icom_tc_change_import_langs($_GET['icom-importItem'], $_GET['icom-targetLang'], "target_lang_import_real");

    // kontrola rodičů stránek
    icom_check_post_parent();

    header("location: " . ICOM_HOST . "wp-admin/post.php?post=" . $post_id . "&action=edit&icom-importItem-firstly");
    exit();
  }

  // import dat (z košíků do preview stránek)
  if (isset($_GET['icom-importItem'])) {
    $importItem = icom_save_db($_GET['icom-importItem']);
    $sourceLang = icom_save_db($_GET['icom-sourceLang']);
    $targetLang = icom_save_db($_GET['icom-targetLang']);
    $importItemOption = icom_save_db($_GET['icom-importItem-option']);

    // import segmentů
    $content = icom_segmentation_import($importItem, $sourceLang, $targetLang, $importItemOption);

    // pokud již stránka (preview) existuje
    if (isset($_GET['icom-importItem-to'])) {

      /*
      $post_id_explode = explode("|", $_GET['icom-importItem-to']);
      if (isset($post_id_explode[1])) {
        $post_id = $post_id_explode[0]; // originál stránky
        $post_id_preview = $post_id_explode[1]; // preview stránky
      } else {
        $post_id = $post_id_preview = $post_id_explode[0];
      }
      */
     $post_id_preview = $post_id = $_GET['icom-importItem-to'];

      // nalezení source stránky
      $post_id_parent = $wpdb->get_var("SELECT id_post_parent FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $post_id . "' AND lang = '" . $targetLang . "'");

      // vytáhnutí aktuálního obsahu stránky/preview
      $page = $wpdb->get_row("SELECT post_title, post_content FROM " . $wpdb->prefix . "posts WHERE ID = '" . $post_id . "'");
      switch($importItemOption) {
        case "proofreading":
        case "over":
          $page->post_title = $content['title'];
          $page->post_content = $content['content'];
          break;
        case "after":
          $page->post_title .= " " . $content['title'];
          $page->post_content .= "<br><br><!-- ICOM_START -->" . $content['content'] . "<!-- /ICOM_END -->";
          break;
        case "before":
          $page->post_title = $content['title'] . " " . $page->post_title;
          $page->post_content = "<!-- ICOM_START -->" . $content['content'] . "<!-- /ICOM_END --><br><br>" . $page->post_content;
          break;
        default:
          break;
      }
      // uložení změněných textů do DB (ale pouze do preview stránky)
      icom_import_update_content($post_id_preview, $page->post_title, $page->post_content, "publish", $post_id_parent, false);

      // update imporovaných jazyků v DB
      if ($importItemOption == "proofreading") {
        icom_tc_change_import_langs($importItem, $targetLang, "proofreading_lang_import");
      } else {
        icom_tc_change_import_langs($importItem, $targetLang, "target_lang_import");
      }

    // vytvoření nového preview
    } else {
      $post_id_preview = icom_save_post(false);
      $post_id_parent = $_GET['icom-targetCreate-of'];

      // nahrání obsahu do preview
      icom_import_update_content($post_id_preview, $content['title'], $content['content'], "publish", $post_id_parent, false);

      // zaheslování preview
      $password = $api['proofreading']['preview']['pass'] . "-" . rand(10000, 99999);
      $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_name = 'preview-page-" . $post_id_preview . "', post_password = '" . $password . "' WHERE ID = '" . $post_id_preview . "'");

      // označení stránky, že se jedná pouze o "preview" stránku
      $wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations SET preview = '1', id_post_orig = '0' WHERE id_post = '" . $post_id_preview . "'");

      // smazání záznamů z `icom_translations_synch` k "preview" stránce
      $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_translations_synch WHERE id_post1 = '" . $post_id_preview . "' OR id_post2 = '" . $post_id_preview . "'");

      icom_tc_change_import_langs($importItem, $targetLang, "target_lang_import");
    }

    if (!isset($_GET['icom-importItem-option'])) {
      // pokud je u stránky proof, nastavíme vše potřebné na TC
      $proofreading = icom_tc_get_item_proofreading($_GET['icom-importItem']);
      if ($proofreading == true) {
        icom_api_set_item_proofreading_ready($_GET['icom-importItem'], $_GET['icom-targetLang'], ICOM_HOST . "?p=" . $post_id_preview . "&lang=" . $_GET['icom-targetLang'] . "&icom-targetLang=" . $_GET['icom-targetLang'] . "&icom-cartItem=" . $_GET['icom-importItem'] . "&icom-previewHash=" . base64_encode($password));
    } }

    /*

    // zjištění, zda-li nebyla změněna sourcová stránka během překladu
    $source = $wpdb->get_row("SELECT post_title, post_content, lang FROM " . $wpdb->prefix . "posts p, " . $wpdb->prefix . "icom_translations t WHERE t.id_post = p.ID AND p.ID = '" . $post_id_parent . "'");
    icom_segmentation($post_id_parent, $source->lang, array($_GET['icom-lang']), "<h1>" . $source->post_title . "</h1>" . $source->post_content);
    $segments = icom_segmentation_export($post_id_parent, $source->lang, $_GET['icom-targetLang']);
    if (!empty($segments)) {
      icom_set_synch($post_id, $post_id_parent, 0);
      set_msg('error', 'Imported page is not synced with its source page, which was probably edited. <a href="' . ICOM_HOST . 'wp-admin/post.php?post=' . $post_id_parent . '&action=edit">Visit source page</a> to order translation to make both pages synced.');
    } else {
      icom_set_synch($post_id, $post_id_parent, 1);
    }

    // aktualizace stavů v naší DB u položky
    icom_tc_import_item($_GET['icom-importItem'], $_GET['icom-targetLang'], $_GET['icom-importItem-option']);

    */

    // přesměrování na importovanou stránku
    header("location: " . ICOM_HOST . "wp-admin/post.php?post=" . $post_id_preview . "&action=edit");
    exit();
  }

	function icom_unique_slug($slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug) {
   	return $original_slug;
	}

	/**
	 * Upravy postname atribut u vsech targetovych stranek
	 * podle zadane hodnoty nebo podle stranky jejiz ID je predavano
	 *
	 * @param integer $id ID stranky
	 * @param string $postname URL segment stranky (post_name, slug)
	 *
	 * @author Tonda
	 */
	function icom_update_postname($id, $postname = NULL) {
    global $wpdb;

    /* Blok pro stranky, ktere jsou homapage */
    $id_homepage = get_option('page_on_front');
    $homepages_id = $wpdb->get_results("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE id_post_parent != '0' AND (id_post_parent = '" . $id_homepage . "' OR id_post = '" . $id_homepage . "')");
    $is_homepage = FALSE;
    foreach($homepages_id as $hp_id){
        if(strcmp($id, $hp_id->id_post) == 0){
            $is_homepage = TRUE;
            break;
        }
    }
    if($is_homepage){
        foreach($homepages_id as $hp_id){
            $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_name = '' WHERE ID = '" . $hp_id->id_post . "'");
        }
    }

    /* Ostatni targetove stranky */
    if(!isset($postname)){
        $postname = $wpdb->get_var("SELECT post_name FROM " . $wpdb->prefix . "posts WHERE ID = '" . icom_get_source_id($id) . "'");
    }

    $targets_id = $wpdb->get_results("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE preview = '0' AND id_post != '" . $id . "' AND id_post_parent = '" . $id . "'");
    foreach($targets_id as $target_id){
        $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_name = '" . $postname . "' WHERE ID = '" . $target_id->id_post . "'");
    }
	}

  /**
   * uložení title, obsahu, post_name a post_status u stránky
   * @param $post_id
   * @param $title
   * @param $content
   * @param $status
   * @param $source_id
   * @param $change_permalink
   */
  function icom_import_update_content($post_id, $title, $content, $status, $source_id, $change_permalink = true) {
    global $wpdb;

    $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_title = '" . icom_save_db($title) . "',
                                                        post_content = '" . icom_save_db($content) . "', " .
                                                        //($change_permalink == true ? "post_name = '" . $link . "', " : "") . "
                                                        "post_status = '" . $status . "'
                                                        WHERE ID = '" . $post_id . "'");

    // pokud je zvoleno změna permalinku u stránky, upravím jej
    if ($change_permalink == true) {
      // pokud je vyplněno $source_id, zkopíruje se link z této stránky (aby měly source a targety v defaultním nastavení stejná URL)
      //if ($source_id != 0)
        //$link = $wpdb->get_var("SELECT post_name FROM " . $wpdb->prefix . "posts WHERE ID = '" . (int)$source_id . "'");
        icom_update_postname($source_id);
      //else
        //$link = icom_links($title);
    }

  }

  /**
   * - kontroluje post_parent sloupečky z wp_posts
   * - zajišťuje, že se target stránky zařazují pod správné target parenty
   */
  function icom_check_post_parent() {
    global $wpdb;

    $pages = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "icom_translations WHERE post_parent_valid = '0'");
    foreach($pages as $val) {
      $post_id = ($val->id_post_parent == 0 ? $val->id_post : $val->id_post_parent);

      $parent1 = $wpdb->get_var("SELECT post_parent FROM " . $wpdb->prefix . "posts WHERE ID = '" . $post_id . "'");
      if ($parent1 == 0) {
        $wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations SET post_parent_valid = '1' WHERE id = '" . $val->id . "'");
        continue;
      }

      if ($val->id_post_parent == 0) {
        $parent2 = $wpdb->get_var("SELECT id_post_parent FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $parent1 . "' AND lang = '" . $val->lang . "' AND preview = '0'");
      } else {
        $parent2 = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE id_post_parent = '" . $parent1 . "' AND lang = '" . $val->lang . "' AND preview = '0'");
      }

      if (!is_null($parent2)) {
        if ($parent2 != 0) {
          $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_parent = '" . $parent2 . "' WHERE id = '" . $val->id_post . "'");
        }
        $wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations SET post_parent_valid = '1' WHERE id = '" . $val->id . "'");
      }
    }

  }

  /**
   * nastaví dvojici stránek jako synchronní
   * @param $post_id
   * @param $parent_post_id
   * @param $value
   */
  function icom_set_synch($post_id, $parent_post_id, $value = 1) {
    global $wpdb;

    $post_id = (int)$post_id;
    $parent_post_id = (int)$parent_post_id;

    $wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_translations_synch VALUES ('" . $post_id . "', '" . $parent_post_id . "', '" . $value . "') ON DUPLICATE KEY UPDATE synch = '" . $value . "'");
  	$wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_translations_synch VALUES ('" . $parent_post_id . "', '" . $post_id . "', '" . $value . "') ON DUPLICATE KEY UPDATE synch = '" . $value . "'");
  }

  /**
   * prijde obsah a vytáhne z něj BB tagy, ty následně porovná s naší tabulkou známých BB tagů a udělá replacement
   * @param $content
   */
  function icom_segmentation_bbcode($content) {
    global $wpdb;

    $return = array('content' => $content, 'attributes' => array(), 'regexes' => array());

    $regexes = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "icom_bbcode");
    $attributes = array();
    foreach($regexes as $regex) {
      $attributes[$regex->name]['attrs'] = explode(",", $regex->attrs);
      $attributes[$regex->name]['regexes'] = explode("\n", $regex->regex);
    }

    preg_match_all("~\[[^\[\]]+\]([^\[\]]+\[/[^[]+\])?~i", $content, $matches);
    $i = 0;
    foreach($matches[0] as $match) {
      preg_match("~\[([^ \]\/]+)\]?~i", $match, $tags);

      if (!empty($attributes[$tags[1]]['attrs'])) {
        foreach($attributes[$tags[1]]['attrs'] as $attr) {
          preg_match("~" . $attr . "=\"([^\"]+)\"~", $match, $attr_to_translate);
          if (empty($attr_to_translate)) continue;

          $return['content'] = str_replace($attr . "=\"" . $attr_to_translate[1] . "\"", $attr . "=\"{ICOM-BBCODE-ATTR-" . $i++ . "}\"", $return['content']);
          $return['attributes'][] = $attr_to_translate[1];
      } }

      if (isset($attributes[$tags[1]])) {
      	$return['regexes'][$tags[1]] = array("DisplayName" => $tags[1], "OpeningRegex" => $attributes[$tags[1]]['regexes'][0], "ClosingRegex" => $attributes[$tags[1]]['regexes'][1]);
      }
    }

    $regexes = array();
    foreach($return['regexes'] as $val) {
    	$regexes[] = $val;
    }
    $return['regexes'] = $regexes;

    return $return;
  }

  /**
   * segmentace textů do tabulky icom_segments
   *
   * @param $post_id
   * @param $source - sourcový jazyk
   * @param $targets - targetové jazyky
   * @param $content
   *  - obsah k segmentacie
   *  - u stránek je to typicky TITLE.CONTENT
   */
  /*
  function icom_segmentation($post_id, $source, $targets, $content) {
    global $wpdb, $api;

    $segments = icom_explode_html($api['segmentation']['tags'], $content);
    foreach($targets as $value) {
      foreach($segments as $key => $val) {
        if (empty($val)) continue;

        if ($key == 0) $val = str_replace(array("<h1>", "</h1>"), array("", ""), $val); // odstranění <h1></h1> (vloženo pluginem kvůli segmentaci) z title

        $segment = $wpdb->get_row("SELECT id, src_lang, src_text, trg_lang, trg_text FROM " . $wpdb->prefix . "icom_segments WHERE active = '0' AND id_post = '" . $post_id . "' AND ((src_lang = '" . $source . "' AND src_text = '" . icom_save_db($val) . "' AND trg_lang = '" . $value . "') OR (src_lang = '" . $value . "' AND trg_lang = '" . $source . "' AND trg_text = '" . icom_save_db($val) . "'))");
        if ($segment === null) {
          $wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_segments VALUES (NULL, '" . $post_id . "', '" . $source . "', '" . icom_save_db($val) . "', '" . $value . "', '', '" . $key . "', '1')");
        } else {
          $wpdb->query("UPDATE " . $wpdb->prefix . "icom_segments SET active = '1', poradi = '" . $key . "' " . ($segment->src_lang == $value && $segment->trg_text == $val ? ", src_text = trg_text, src_lang = trg_lang, trg_lang = '" . $segment->src_lang . "', trg_text = '" . icom_save_db($segment->src_text) . "'" : "") . " WHERE id = '" . $segment->id . "'");
        }

      }
      $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_segments WHERE active = '0' AND id_post = '" . $post_id . "' AND trg_lang = '" . $value . "'");
      $wpdb->query("UPDATE " . $wpdb->prefix . "icom_segments SET active = '0' WHERE id_post = '" . $post_id . "' AND trg_lang = '" . $value . "'");
    }
  }
  */

  /**
   * vybere všechny prázdné segmenty, určené k překladu, z konkrétního jazyka
   *
   * @param post_id
   * @param src_lang
   * @param trg_lang
   */
  /*
  function icom_segmentation_export($post_id, $src_lang, $trg_lang) {
    global $wpdb;

    $segments = $wpdb->get_results("SELECT poradi, src_text FROM " . $wpdb->prefix . "icom_segments WHERE trg_text = '' AND id_post = '" . $post_id . "' AND src_lang = '" . $src_lang . "' AND trg_lang = '" . $trg_lang . "' ORDER BY poradi ASC");
    if ($segments == null) return array('segments' => 0);

    $return = "";
    foreach($segments as $val) {
      $return .= $val->src_text . str_replace("{ORDER}", $val->poradi, ICOM_SEP_SEG);
    }

    return array('segments' => count($segments), 'string' => $return);
  }
  */

  /**
   * import přeložených segmentů z TC do segmentovací tabulky
   * @param $uri položky
   * @param $source
   * @param $target
   * @param $import option
   */
  function icom_segmentation_import($uri, $source, $target, $option) {
    global $wpdb;

    $item = icom_api_get_item($uri);
    foreach($item->targetContents as $val) {
      if ($val->targetLanguage == $target) {
        $trg_content = ($option == "proofreading" ? $val->proofreadContent : $val->translatedContent); break;
    } }

    $contents = explode(ICOM_SEP_SEG, $trg_content);

    $attrs = explode(str_replace("{TYPE}", "bbCodeAttr", ICOM_SEP_SEG), $contents[2]);
    foreach($attrs as $key => $attr) {
    	$contents[1] = str_replace("{ICOM-BBCODE-ATTR-" . $key . "}", $attr, $contents[1]);
    }

    return array('title' => $contents[0], 'content' => $contents[1]);

    /*
    // rozparsování příchozích segmentů
    $segments = explode("' data-icom='ICOM-SEG'>", $trg_content);
    foreach($segments as $val) {
      $segment = explode("<hr data-icom-order='", $val);
      if (!isset($segment[1])) continue;

      $wpdb->query("UPDATE " . $wpdb->prefix . "icom_segments SET trg_text = '" . icom_save_db($segment[0]) . "' WHERE id_post = '" . $item->customId . "' AND src_lang = '" . $source . "' AND trg_lang = '" . $target . "' AND poradi = '" . $segment[1] . "'");
    }

    return icom_segmentation_build($item->customId, $source, $target);
    */
  }

  /**
   * vytáhne všechny segmenty dané stránky a hodí je do pole array('title' => '', 'content' => '')
   * @param id_post
   * @param source
   * @param target
   * @param rebuild - pokud se pouze skladá stránka, bez překladu (v případě, že se pouze něco smazalo)
   */
  /*
  function icom_segmentation_build($id_post, $source, $target, $rebuild = false) {
    global $wpdb;

    $title = $wpdb->get_var("SELECT trg_text FROM " . $wpdb->prefix . "icom_segments WHERE id_post = '" . $id_post . "' AND src_lang = '" . $source . "' AND trg_lang = '" . $target . "' AND poradi = '0'");
    $content = $wpdb->get_results("SELECT trg_text FROM " . $wpdb->prefix . "icom_segments WHERE id_post = '" . $id_post . "' AND src_lang = '" . $source . "' AND trg_lang = '" . $target . "' AND poradi > '0' ORDER BY poradi ASC");

    $page = array('title' => $title, 'content' => '');
    $array = array();
    foreach($content as $val) { $array[] = $val->trg_text; }
    $page['content'] = implode("", $array);

    if ($rebuild == true) {
      $target_id = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE id_post_parent = '" . $id_post . "' AND lang = '" . $target . "'");
      $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_title = '" . $page['title'] . "', post_content = '" . $page['content'] . "' WHERE ID = '" . $target_id . "'");

      // synchronizace stránek
      icom_set_synch($id_post, $target_id);
    }

    return $page;
  }
  */

  /**
   * přidání nové stránky - source i target
   * - odchycení cesty při vytváření nové stránky source i target a volání fce icom_save_post(), která vyřeší zbytek
   */
  if (strpos($_SERVER['SCRIPT_NAME'], "post-new.php") !== false) {
    icom_save_post();
	}

  /**
   * uložení stránky
   * - volá se pro srouce stránky
   * - volá se pro target stránky
   * - volá se při vytváření nových stránek
   * - volá se při updatech stránek
   *
   * @param $redirect - přesměrování
   */
  function icom_save_post($redirect = true) {
    global $wpdb;

    if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) return;
    if ($_GET['action'] == "trash" || $_GET['action'] == "untrash") return;
    if (strpos($_SERVER['SCRIPT_NAME'], "admin-ajax.php") === false && strpos($_SERVER['SCRIPT_NAME'], "post.php") === false && strpos($_SERVER['SCRIPT_NAME'], "post-new.php") === false && $_GET['page'] != "icom") return;

    $targetCreateOf = icom_save_db($_GET['icom-targetCreate-of']);
    $targetLang = icom_save_db($_GET['icom-targetLang']);

    // vytvoření nové stránky
    if (empty($_POST)) {
      $post_type = icom_save_db(isset($_GET['post_type']) ? $_GET['post_type'] : "post");

      $wpdb->query("INSERT INTO " . $wpdb->prefix . "posts (post_status, post_type, post_date, post_modified) VALUES ('auto-draft', '" . $post_type . "', NOW(), NOW())");
		  $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET guid = '" . ICOM_HOST . "?p=" . $wpdb->insert_id . "' WHERE id = '" . $wpdb->insert_id . "'");

		  $post_id = $wpdb->insert_id;

		// update již vytvořené stránky
		} else {
		  $post_id = icom_save_db($_POST['post_ID']);
		}

    icom_update_postname($post_id);

    // import TARGET stránky
    if (isset($_GET['icom-targetCreate-of'])) {
      $post_id = $wpdb->insert_id; // ID naposledy vytvořené stránky
      $post_id_orig = $wpdb->get_var("SELECT id_post_orig FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $targetCreateOf . "'");

      // doplnění potřebných dat od source stránky
      $post_orig_data = $wpdb->get_row("SELECT post_author, menu_order, post_mime_type FROM " . $wpdb->prefix . "posts WHERE ID = '" . $post_id_orig . "'");
      $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_author = '" . $post_orig_data->post_author . "', menu_order = '" . $post_orig_data->menu_order . "', post_mime_type = '" . $post_orig_data->post_mime_type . "' WHERE ID = '" . $post_id . "'");
      $post_orig_template = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "postmeta WHERE post_id = '" . $post_id_orig . "' AND meta_key = '_wp_page_template' LIMIT 1");
      $wpdb->query("INSERT INTO " . $wpdb->prefix . "postmeta (post_id, meta_key, meta_value) VALUES ('" . $post_id . "', '_wp_page_template', '" . $post_orig_template . "')");

      icom_update_postname($post_id_orig);

			//uložení záznamu o nově vytvořené stránce do tabulky "translations"
	    $wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_translations (id_post, id_post_orig, id_post_parent, lang) VALUES ('" . $post_id . "', '" . $post_id_orig . "', '" . $targetCreateOf . "', '" . $targetLang . "')");

			//uložení záznamů do synchronizační tabulky
			icom_set_synch($post_id, $targetCreateOf, 0);

	    // v případě source stránky označíme také synchronizaci s target stránkama
      $targets = $wpdb->get_results("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE id_post != '" . $post_id . "' AND id_post_parent = '" . $targetCreateOf . "' AND preview = '0'");
	    foreach($targets as $val) {
        icom_set_synch($post_id, $val->id_post, '0');
	    }
		} else {
      // vytvoření / update stránky v icom_translations a icom_translations_synch
      if (empty($_POST)) {
        $default = get_option('icom_language_default');
        $wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_translations (id_post, id_post_orig, post_parent_valid, lang) VALUES ('" . $post_id . "', '" . $post_id . "', '1', '" . $default . "')");
      } else {
        if (strpos($_POST['_wp_http_referer'], "icom-importItem-firstly") === false) {
          $wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations_synch SET synch = '0' WHERE id_post1 = '" . $post_id . "' OR id_post2 = '" . $post_id . "'");
    } } }

    if (empty($_POST) && in_array(get_post_status($post_id), array("inherit", "auto-draft", "draft"))) {
      $parent_id = $wpdb->get_var("SELECT post_parent FROM " . $wpdb->prefix . "posts WHERE ID = '" . $post_id . "'");
      if ($parent_id != 0) $post_id = $parent_id;
		}

		// ukládání přes "quick edit" || box na dashboardu
		if (strpos($_SERVER['SCRIPT_NAME'], "admin-ajax.php") !== false || strpos($_SERVER['SCRIPT_NAME'], "index.php") !== false) {
      $redirect = false;
    }

    /*
		if ($redirect == true) {
		  header("location: " . ICOM_HOST . "wp-admin/post.php?action=edit&post=" . $post_id);
		  exit();
		}
    */

		return $post_id;
  }

  /*
  add_filter('wp_unique_post_slug', 'wp_unique_test_slug', 10, 6);
  function wp_unique_test_slug($slug, $post_id, $post_status, $post_type, $post_parent, $original_slug) {
    echo $slug . " - ";
    $slug .= "abc";
    echo $slug;

    return "slugabc"; //$slug;
  }
  */

    /**
     * smazání stránky - mažou se záznamy ve všech tabulkách icom_ (a posts, v případě source stránky)
     * - volá se při mazání source stránky - smažou se také všechny target stránky
     * - volá se při mazání target stránky - smažou se také všechny preview
     */
    function icom_delete_post($post_id) {
      global $wpdb;

			$posts = $wpdb->get_results("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE id_post_parent = '" . $post_id . "' OR id_post_orig = '" . $post_id . "'");
			foreach($posts as $val) {
        if ($val->id_post == $_GET['post']) continue;

        $wpdb->query("DELETE FROM " . $wpdb->prefix . "posts WHERE ID = '" . $val->id_post . "' OR (post_parent = '" . $val->id_post . "' AND (post_type = 'revision' OR post_type = 'attachment'))");
        icom_delete_post_sql($val->id_post);
		  }

			icom_delete_post_sql($post_id);
    }

	/**
	 * mazání záznamů ze všech icom_tabulek právě mazané stránky - source i target
	 * - smaže všechny záznamy právě mazané stránky ze všech tabulek pluginu
	 *
	 * @param post_id
	 */
	function icom_delete_post_sql($post_id) {
		global $wpdb;

		$wpdb->query("DELETE FROM " . $wpdb->prefix . "postmeta WHERE post_id = '" . $post_id . "'");
		$wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $post_id . "'");
    $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_translations_synch WHERE id_post1 = '" . $post_id . "' OR id_post2 = '" . $post_id . "'");
    $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_segments WHERE id_post = '" . $post_id . "'");
	}
?>
