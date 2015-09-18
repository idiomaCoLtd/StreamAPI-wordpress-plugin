<?php
  /**
   * vybere košíky, do kterých je možné přidávat položky
   * @param $all boolean - pokud true, vybere všechny košíky
   */
  function icom_tc_get_carts($all = false) {
    global $wpdb;
    return $wpdb->get_results("SELECT uri, name, state FROM " . $wpdb->prefix . "icom_tc_carts " . ($all == false ? "WHERE state = 'Setup' OR state = ' EstimationStarted' OR state = 'EstimationFinished'" : ""));
  }

  /**
   * vybere konkrétní košík
   * @param $uri košíku
   */
  function icom_tc_get_cart($uri) {
    global $wpdb;
    return $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "icom_tc_carts WHERE uri = '" . $uri . "'");
  }

  /**
   * vybere konkrétní položku
   * @param $uri položky
   */
  function icom_tc_get_item($uri) {
    global $wpdb;
    return $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "icom_tc_items WHERE uri = '" . $uri . "'");
  }

  /**
   * vybere košík dle stránky
   * @param @page - id stránky
   * @param $source - source jazyk
   * @param $target - target jazyk
   */
  function icom_tc_get_cart_by_page($page, $source, $target) {
    global $wpdb;
    // AND ((i.state = 'TranslationFinished' AND target_lang_import NOT LIKE '%" . $target . "%') OR (i.state = 'ProofreadingFinished' AND proofreading_lang_import NOT LIKE '%" . $target . "%') OR (i.state != 'TranslationFinished' AND i.state != 'ProofreadingFinished'))
    return $wpdb->get_row("SELECT c.uri AS uri, c.name AS name, c.state AS state FROM " . $wpdb->prefix . "icom_tc_items i, " . $wpdb->prefix . "icom_tc_carts c WHERE i.id_cart = c.id AND i.customID = '" . $page . "' AND i.source_lang = '" . $source . "' AND i.target_lang LIKE '%" . $target . "%' ORDER BY i.id DESC LIMIT 1");
  }

  /**
   * vybere položku dle stránky
   * @param $page
   * @param $source
   * @param $target
   */
  function icom_tc_get_item_by_page($page, $source, $target) {
    global $wpdb;
    return $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "icom_tc_items WHERE customID = '" . $page . "' AND source_lang = '" .$source . "' AND target_lang LIKE '%" . $target . "%' ORDER BY id DESC LIMIT 1");
  }

  /**
   * vybere košík konkrétní položky
   * @param $uri položky
   */
  function icom_tc_get_item_cart($uri) {
    global $wpdb;
    return $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "icom_tc_carts WHERE id = (SELECT id_cart FROM " . $wpdb->prefix . "icom_tc_items WHERE uri = '" . $uri . "')");
  }

  /**
   * přidá košík
   * @param $name
   * @param $uri
   * @param $source
   */
  function icom_tc_add_cart($name, $uri, $source) {
    global $wpdb;
    $wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_tc_carts (datetime, name, uri, state, source_lang) VALUES (NOW(), '" . icom_save_db($name) . "', '" . $uri . "', 'Setup', '" . $source . "')");
  }

  /**
   * smaže košík
   * @param $uri
   */
  function icom_tc_delete_cart($uri) {
    global $wpdb;
    $cart = icom_tc_get_cart($uri);

    $items = $wpdb->get_results("SELECT uri FROM " . $wpdb->prefix . "icom_tc_items WHERE id_cart = '" . $cart->id . "'");
    foreach($items as $item) {
      icom_tc_delete_item($item['uri']);
    }

    //$wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_tc_items WHERE id_cart = '" . $cart->id . "'");
    $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_tc_carts WHERE uri = '" . $uri . "'");
  }

  /**
   * přidá položku do košíku
   * @param $cart_uri
   * @param $name
   * @param $customID (post_id)
   * @param $uri
   * @param $wordcount
   * @param $source
   * @param $targets (targetové jazyky)
   * @param $proofreading
   */
  function icom_tc_add_item($cart_uri, $name, $customID, $uri, $wordcount, $source, $targets, $proofreading) {
    global $wpdb;
    sort($targets);

    $cart = icom_tc_get_cart($cart_uri);
    $wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_tc_items (id_cart, datetime, name, customID, uri, wordcount, source_lang, target_lang, proofreading) VALUES ('" . $cart->id . "', NOW(), '" . icom_save_db($name) . "', '" . icom_save_db($customID) . "', '" . $uri . "', '" . $wordcount . "', '" . $source . "', '" . implode(";", $targets) . "', '" . ($proofreading == true ? 1 : 0) . "')");

    icom_db_set_icom_post_status($customID);
  }

  /**
   * smaže položku z košíku
   * @param $uri
   */
  function icom_tc_delete_item($uri) {
    global $wpdb;

    $customID = $wpdb->get_var("SELECT customID FROM " . $wpdb->prefix . "icom_tc_items WHERE uri = '" . $uri . "'");
    icom_db_set_orig_post_status($customID);

    $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_tc_items WHERE uri = '" . $uri . "'");
  }

  /**
   * změní u položky proofreading (nastaví nebo smaže)
   * @param $uri
   * @param $state - int(1)
   */
  function icom_tc_set_item_proofreading($uri, $state) {
    global $wpdb;
    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_tc_items SET proofreading = '" . $state . "' WHERE uri = '" . $uri . "'");
  }

  /**
   * vrátí boolean, zda-li má položka proofreading
   * @param $uri
   */
  function icom_tc_get_item_proofreading($uri) {
    global $wpdb;
    return ($wpdb->get_var("SELECT proofreading FROM " . $wpdb->prefix . "icom_tc_items WHERE uri = '" . $uri . "'") == 1 ? true : false);
  }

  /*

  function icom_tc_get_item_proofreading_by_page($page, $source, $target) {
    global $wpdb;

    $return = $wpdb->get_var("SELECT COUNT(i.id) FROM " . $wpdb->prefix . "icom_tc_items i, " . $wpdb->prefix . "icom_tc_carts c WHERE i.id_cart = c.id AND i.source_lang = '" . $source . "' AND target_lang_import LIKE '%" . $target . "%' AND proofreading_lang_import NOT LIKE '%" . $target . "%' AND customID = '" . $page . "' AND proofreading = '1'");
    return ($return > 0 ? true : false);
  }

  */

  /**
   * update kolonky s jazyky po importu
   * @param $uri
   * @param $lang
   * @param $col (target_lang_import, target_lang_import_real, proofreading_lang_import)
   */
  function icom_tc_change_import_langs($uri, $lang, $col) {
    global $wpdb;

    $langs = $wpdb->get_var("SELECT " . $col . " FROM " . $wpdb->prefix . "icom_tc_items WHERE uri = '" . $uri . "'");
    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_tc_items SET " . $col . " = '" . icom_handle_langs($langs, $lang) . "' WHERE uri = '" . $uri . "'");
  }

  /**
   * nastaví konkrétní stav košíku v DB
   * @param $uri
   * @param $state
   */
  function icom_tc_change_state($uri, $state) {
    global $wpdb, $tc;

    /*

    // pokud ještě není celý košík dokončen, ale některé jazyk jsou již na PR, ponecháme současný stav
    $actual_state = $wpdb->get_var("SELECT state FROM " . $wpdb->prefix . "icom_tc_carts WHERE uri = '" . $uri . "'");
    if ($actual_state == 'TranslationPartiallyFinished' && in_array($state, $tc['carts']['filter']['for-import-proofreading'])) return;

    // pokud se mají kontrolovat již ne/importované jazyky - "...Partially..." stavy
    if (!empty($type)) {
      $cart = icom_api_get_cart($uri);
      switch($type) {
        case "tr":
          $langs = $cart->languagesWithFinishedTranslation; break;
        case "pr":
          $langs = $cart->languagesWithFinishedProofreading; break;
        default:
          break;
      }
      sort($langs);
      $langs = implode(";", $langs);

      $states = explode(",", $state);
      if ($langs != $wpdb->get_var("SELECT target_lang FROM " . $wpdb->prefix . "icom_tc_carts WHERE uri = '" . $uri . "'")) {
        $state = $states[0];
      } else {
        $state = $states[1];
      }
    }

    */

    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_tc_carts SET state = '" . $state . "' WHERE uri = '" . $uri . "'");
  }

  /**
   * nastaví stav košíku dle aktuálního stavu na TC
   * @param $uri
   */
  function icom_tc_sync_state($uri) {
    global $wpdb;

    $cart = icom_api_get_cart($uri);
    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_tc_carts SET state = '" . $cart->state . "' WHERE uri = '" . $uri . "'");
  }

  /**
   * smaže jazyky
   * @param $languages ke smazání
   * @param $uri
   */
  function icom_tc_set_cancel($languages, $uri) {
    global $wpdb, $tc;

    $cart = icom_tc_get_cart($uri);
    $langs = $wpdb->get_var("SELECT target_lang FROM " . $wpdb->prefix . "icom_tc_items WHERE id_cart = '" . $cart->id . "'");

    $langs = explode(";", $langs);
    $langs2 = array();
    foreach($langs as $val) {
      if (in_array($val, $languages)) continue;
      $langs2[] = $val;
    }
    sort($langs2);

    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_tc_items SET target_lang = '" . implode(";", $langs2) . "' WHERE id_cart = '" . $cart->id . "'");
    $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_tc_items WHERE target_lang = '' AND id_cart = '" . $cart->id . "'");

    /*

    // pokud jsme ještě neobjednali, nastavíme 'Setup'
    if (in_array($cart->state, $tc['carts']['filter']['for-delete']))
      icom_tc_change_state($uri, 'Setup');

    // pokud uživatel smazal všechny jazyky, uzavřeme košík
    if (empty($langs2))
      icom_tc_change_state($uri, 'Closed');

    */
  }
?>
