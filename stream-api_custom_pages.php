<?php
    /**
   *  ověří, zda-li se má upravit seznam položek pro potřeby icomu
   *  - icom funguje pouze pro POSTS a PAGES
   */
  function icom_allowed_post_type() {
    $type = get_query_var('post_type');

    if ($type == "post") return true;
    if ($type == "page") return true;

    return false;
  }

  function icom_allowed_post_status() {
    $status = get_query_var('post_status');

    if ($status == "trash") return false;
    if ($status == "draft") return false;

    return true;
  }

  /**
   * Přidání vlastních sloupců do výpisu "Pages" a "Posts"
   */
  function icom_custom_pages_columns($columns) {
    global $icom_lang_all, $icom_lang_default, $icom_lang_selected;

    if (empty($_POST) && icom_allowed_post_type() == false) return $columns;

    //přidání vlaječky a zkratky jazyka
    $icom_lang_columns = array();
  	foreach($icom_lang_selected as $lang) {
      if ($icom_lang_default == $lang) continue; //defaultní jazyk se vypisovat nebude

      $icom_lang_columns[$lang] = __('<img src="' . ICOM_PLUGIN_URL . 'img/flags/' . $lang . '.png" />
                                      <span class="icom-lang">
                                        <abbr class="icom-abbr" title="' . $icom_lang_all[$lang]->name . '">' . $lang . '</abbr>
                                      </span>');
  	}

    // Změna pořadí výpisu sloupců - přidání ID a vlaječky DEFAULT jazyka
    foreach($columns as $column => $col_val) {
      // před TITLE
      if ($column == "title" && icom_allowed_post_status() == true) {
        $col_val = '<img src="' . ICOM_PLUGIN_URL . 'img/flags/' . $icom_lang_default . '.png">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $col_val . '
                    <br>
                    <span class="icom-lang">
                      <abbr class="icom-abbr" title="' . $icom_lang_all[$lang]->name . '">' . $icom_lang_default . '</abbr>
                    </span>';
        $col_new['icom_id_post'] = __('ID');
      }

      $col_new[$column] = $col_val; //výpis všech původních sloupců

      //po TITLE
      // Sloučení všech sloupců dohromady
      if (count($icom_lang_selected) > 1 && icom_allowed_post_status() == true && $column == "title") {
        $col_new = array_merge($col_new, $icom_lang_columns);
    } }

    return $col_new;
  }

  /**
   *  Obsah vlastních definovaných sloupců
   */
  function icom_custom_pages_columns_content($column_name, $post_id) {
  	global $wpdb, $tc, $icom_lang_pricelist, $icom_lang_default, $icom_lang_selected;

    if (empty($_POST) && icom_allowed_post_status() == false) return;

  	// pokud jde o sloupeček ID, tak vypiš ID postu
  	if ($column_name == "icom_id_post") {
  		echo $post_id;
      return;
  	}

    // pokud sloupeček není jazyk, tak se nic přidávat nebude
    if (!in_array($column_name, $icom_lang_selected)) return;

    // získání source stránky k této target stránce
    $post_id_source = $wpdb->get_var("SELECT id_post_orig FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . icom_save_db($post_id) . "'");

    $post_type = get_query_var('post_type');
  	// IKONA ADD - přímý odkaz na vytvoření nové stránky
    $link = '<a href="' . ICOM_HOST . 'wp-admin/post-new.php?post_type=' . $post_type . '&icom-targetCreate-of=' . $post_id . '&icom-targetLang=' . $column_name . '">
              <img src="' . ICOM_PLUGIN_URL . 'img/pages/add.png" />
            </a>';

    // IKONA ADD - kontextové menu na vytvoření nové stránky (ručně nebo překladem)
  	$link_add_full = '<span class="icom-table-action" onclick="showContextMenu(this);">
                        <img src="' . ICOM_PLUGIN_URL . 'img/pages/add.png" />
                        <span class="icom-table-menu">
                          <a href="'.ICOM_HOST.'wp-admin/post-new.php?post_type=' . $post_type . '&icom-targetCreate-of=' . $post_id . '&icom-targetLang=' . $column_name . '">' . __('ADD manually', 'icom') . '</a>
                          <a href="javascript:void(0);" onclick="addPageByTranslation(\'' . $post_id . '\', \'' . $column_name . '\');">' . __('ADD by translation', 'icom') . '</a>
                        </span>
                      </span>';

    // IKONA KOŠÍKU - konextové mneu pokud je položka v košíku
    $link_cart_preview = '<a class="icom-table-action" href="' . ICOM_HOST . 'wp-admin/post.php?post={POST}&action=edit">' . __('EDIT preview', 'icom') . '</a>';
    $link_cart_full = '<span class="icom-table-action" onclick="showContextMenu(this);">
                        <img src="' . ICOM_PLUGIN_URL . 'img/pages/cart.png" />
                        <span class="icom-table-menu">
                          <a class="icom-table-action" href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart={CART-URI}">' . __('OPEN cart', 'icom') . ' <i>"{CART-NAME}"</i></a>
                          {PREVIEW}
                        </span>
                      </span>';

    // IKONA EDIT - kontextové menu k editaci položky
    $link_edit = '<a class="icom-table-action" href="' . ICOM_HOST . 'wp-admin/post.php?post={POST}&action=edit">' . __('EDIT', 'icom') . '</a>
                  <a class="icom-table-action" href="{PERMALINK}">' . __('VIEW', 'icom') . '</a>
                  <a class="icom-table-action" href="{TRASH-POST-LINK}">' . __('MOVE to trash', 'icom') . '</a>';

    $link_edit_full = '<span class="icom-table-action" onclick="showContextMenu(this);">
                        <img src="' . ICOM_PLUGIN_URL . 'img/pages/edit.png" />
                        <span class="icom-table-menu">
                          ' . $link_edit . '
                        </span>
                      </span>';

    // IKONA NOT-SYNCED - kontetové menu k synchronizaci položky
    $link_sync_full = '<span class="icom-table-action" onclick="showContextMenu(this);">
                        <img src="' . ICOM_PLUGIN_URL . 'img/pages/not-synch.png" />
                        <span class="icom-table-menu">
                          <a class="icom-table-action" href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&post={POST}&icom-sourceTargetSynch=' . $post_id_source . '">' . __('SET as synced', 'icom') . '</a>
                          ' . $link_edit . '
                        </span>
                      </span>';

    // IKONA TRASH - kontextové menu k položce v koši
    $link_trash_full = '<span class="icom-table-action" onclick="showContextMenu(this);">
                        <img src="' . ICOM_PLUGIN_URL . 'img/pages/trash.png" />
                        <span class="icom-table-menu">
                          <a class="icom-table-action" href="' . ICOM_HOST . 'wp-admin/edit.php?post_type=' . $post_type . '&post_status=trash">' . __('OPEN trash', 'icom') . '</a>
                        </span>
                      </span>';

    // IKONA ADD - ověření, zda-li pro tento jazyk existuje ceník
    if (isset($icom_lang_pricelist)) {
      foreach($icom_lang_pricelist as $val) {
        if ($val->languageCode == $column_name) { $link = $link_add_full; break; }
    } }

    // pokud již stránka existuje změníme kontextové menu
    $target = icom_db_get_page_by_parent($post_id_source, $column_name);
  	if ($target) {
  		$status = $wpdb->get_var("SELECT post_status FROM " . $wpdb->prefix . "posts WHERE ID = '" . $target->id_post . "'");

      // IKONA TRASH
      if ($status == "trash") {
        $link = $link_trash_full;

      // IKONA NOT-SYNCED
      } else if (!icom_is_synced($target->id_post, $post_id)) {
        $link = $link_sync_full;

      // IKONA EDIT
      } else {
        $link = $link_edit_full;
      }

      $permalink = icom_get_permalink_struct($column_name, str_replace(ICOM_HOST, "", get_permalink($target->id_post)));
      $link = str_replace(array("{POST}", "{PERMALINK}", "{TRASH-POST-LINK}"),
                          array($target->id_post, ICOM_HOST . $permalink['value'], get_delete_post_link($target->id_post)),
                          $link);
  	}

      // IKONA KOŠÍKU
    	$cart = icom_tc_get_cart_by_page($post_id, $icom_lang_default, $column_name);
      $item = icom_tc_get_item_by_page($post_id, $icom_lang_default, $column_name);
    	if (strpos($item->target_lang_import_real, $column_name) === false && in_array($cart->state, $tc['carts']['filter']['for-pages'])) {
        if ($target) {
          $link = str_replace(array("{PREVIEW}", "{POST}", "{CART-URI}", "{CART-NAME}"),
                              array($link_cart_preview, $target->id_post, $cart->uri, $cart->name),
                              $link_cart_full);
        } else {
          $link = str_replace(array("{PREVIEW}", "{CART-URI}", "{CART-NAME}"),
                              array("", $cart->uri, $cart->name),
                              $link_cart_full);
    } }

  	echo $link;
  }

  /**
   * Funkce vrátí TRUE pokud jsou stránky synchonizované, jinak FASLE
   *
   * @global object $wpdb - objekt připojení k databázi
   * @param integer $id1 - ID stránky
   * @param integer $id2 - ID stránky
   * @return boolean
   */
  function icom_is_synced($id1, $id2){
    global $wpdb;

    return $wpdb->get_var("SELECT synch FROM " . $wpdb->prefix . "icom_translations_synch WHERE id_post1 = '" . icom_save_db($id1) . "' AND id_post2 = '" . icom_save_db($id2) . "'");
  }

  /**
   * Úprava CSS u vybraných sloupců
   */
  function icom_custom_pages_column_width() {
  	global $icom_lang_selected;

  	echo '<style type="text/css">';
  	echo '.column-icom_id_post { width: 31px !important; }';
  	foreach($icom_lang_selected as $lang) {
  		echo 'td.column-' . $lang . ', th.column-' . $lang . ' { width: 25px !important; text-align: center !important; }';
  	}
  	echo '</style>';
  }

  /**
   *  vyloučí z výpisu target stránky a preview
   */
  function icom_exclude_target_pages( $query ) {
    global $wpdb, $icom_lang_default;

  	$target_pages = $wpdb->get_results("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE lang != '" . $icom_lang_default . "' OR preview = 1");
  	foreach($target_pages as $page) {
  		$exclude_pages[] = $page->id_post;
  	}

  	if (!is_admin()) {
      return $query;
    }

    if (icom_allowed_post_type() == true && icom_allowed_post_status() == true) {
      $query->set('post__not_in', $exclude_pages); // page_id
    }

    return $query;
  }

  /**
   * vloží do patičky modal-dialog se selectem s košíkama
   * - použito ve variantě "add new page by translation"
   */
  function icom_modal_carts() {
    global $icom_lang_all, $icom_lang_default, $icom_lang_selected, $icom_lang_pricelist;

    $token = get_option('icom_token');
    if (empty($token)) return;

    if (icom_allowed_post_type() == false) return;

    $carts = icom_tc_get_carts();
    $select = "<option value='' selected='selected'>--- ". __('select one of your carts', 'icom')." ---</option>";
    foreach($carts as $val) {
      $select .= "<option value='" . $val->uri . "'>" . $val->name. "</option>";
    }

  	echo "<div id='icom-dialog-carts' class='icom-dialog'>
            <div class='icom-dialog-in' style='width: 500px;'>
              <button class='button close' onclick='return showDialog(\"icom-dialog-carts\", true);'>X</button>

              <h3>". __('Select one of your carts', 'icom')."</h3>
              <p>". __('In this cart will be inserted texts to translate selected page:', 'icom')."</p>
              <br>
              <select onchange='jQuery(\"#icom-dialog-carts-submit\").attr(\"href\", \"?page=icom&icom-targetCreate-by[]=\" + jQuery(\"#icom-create-target-by\").val() + \"&icom-sourceLang=" . $icom_lang_default . "&icom-targetLangs[]=\" + jQuery(\"#icom-target\").val() + \"&icom-cart=\" + this.value);'>
                " . $select . "
              </select>
              <a class='button' href='#' id='icom-dialog-carts-submit'>". __('OK', 'icom')."</a>

              <input type='hidden' id='icom-create-target-by' value='' />
              <input type='hidden' id='icom-target' value='' />
            </div>
          </div>";

    $targets = "";
    foreach($icom_lang_selected as $val) {
      $price = false;

      foreach($icom_lang_pricelist as $val_price) {
        if ($val_price->languageCode == $val) { $price = $val_price->unitPrice->value; break; }
      }

      $is = false;
      foreach($icom_lang_all as $v) {
        if ($val == $v->code) { $is = true; break; }
      }

      if ($val == $icom_lang_default) $is = false;
      if ($is == false) continue;

      $targets .= "<span class='icom-checks icom-target'>
                    <input type='checkbox' id='lang_" . $val . "' " . ($price === false ? "disabled='disabled'" : "value='" . $val . "' onchange='jQuery(this).parent().toggleClass(\"icom-active\");'") . " />
                    <label for='lang_" . $val . "'>
                      <img src='" . ICOM_PLUGIN_URL . "img/flags/" . $val . ".png' />
                      <b>" . $icom_lang_all[$val]->name . "</b>
                    </label>
                  </span>";

    }

    echo "<div id='icom-dialog-bulkAction' class='icom-dialog'>
            <div class='icom-dialog-in' style='width: 50%;'>
              <button class='button close' onclick='return showDialog(\"icom-dialog-bulkAction\", true);'>X</button>

              <h3>". __('Select at least one target language', 'icom')."</h3>
                <div id='icom-bulk-target'>
                  " . $targets . "
                </div>
                <div class='icom-cleaner'></div>

              <h3>". __('Select one of your carts', 'icom')."</h3>
              <select id='icom-bulk-cart'>
                " . $select . "
              </select>

              <h3>". __('Proofreading', 'icom')."</h3>
              <input type='checkbox' id='icom-bulk-proofreading' value='1' checked='checked' />
              <label for='icom-bulk-proofreading'>" . __('Request proofreading for all selected pages and languages', 'icom') . "</label>

              <input type='hidden' id='icom-bulk-source' value='" . $icom_lang_default . "' />
              <br><br>
              <button class='button' onclick='addPagesByTranslation(\"" . ICOM_HOST . "wp-admin/admin.php?page=icom\");'>". __('Add to cart', 'icom')."</button>

            </div>
          </div>";

    if (icom_allowed_post_status() == true) {

      echo "<script type='text/javascript'>
              jQuery(document).ready(function() {
                jQuery('<option>').val('icom').text('Translate').appendTo('select[name=\"action\"]');
                jQuery('<option>').val('icom').text('Translate').appendTo('select[name=\"action2\"]');
              });

              jQuery('#doaction').click(function() {
                if (jQuery('select[name=\"action\"]').val() == 'icom') { return showDialog('icom-dialog-bulkAction'); }
              });
              jQuery('#doaction2').click(function() {
                if (jQuery('select[name=\"action2\"]').val() == 'icom') { return showDialog('icom-dialog-bulkAction'); }
              });

              jQuery('#posts-filter').find('span.displaying-num').parent().prepend('<span class=\"icom-pages-legend\"><img src=\"" . ICOM_PLUGIN_URL . "img/pages/cart.png\" /><i>". __('Page in cart', 'icom')."</i><img src=\"" . ICOM_PLUGIN_URL . "img/pages/add.png\" /><i>". __('Add new page', 'icom')."</i><img src=\"" . ICOM_PLUGIN_URL . "img/pages/edit.png\" /><i>". __('Edit page', 'icom')."</i><img src=\"" . ICOM_PLUGIN_URL . "img/pages/not-synch.png\" /><i>". __('Unsynced page', 'icom')."</i><img src=\"" . ICOM_PLUGIN_URL . "img/pages/trash.png\" /><i>". __('Page in trash', 'icom')."</i></span>');
            </script>";

    }

    echo get_msg();

  }
?>
