<?php
  /**
   * vložení položek do košíku přes toolbox na editaci stránky
   * - zavolá segmentační fci
   * - naplní košík nepřeloženými segmenty
   * - přesměruje zpět na editaci postu
   */
  if (isset($_POST['icom-toolbox'])) {
    $post_id = icom_save_post(false);

    if (empty($_POST['post_title'])) set_msg('error', __('Page has no title.', 'icom'));
    if (empty($_POST['content'])) set_msg('error', __('Page has no content.', 'icom'));
    if (empty($_POST['icom-targetLangs'])) set_msg('error', __('You have to select one target language at least.', 'icom'));
    if (empty($_POST['icom-cart'])) set_msg('error', __('You have to select one of your carts.', 'icom'));

    if (is_msg() == false) {
      $proofreading = (isset($_POST['icom-proofreading']) ? true : false);

      $segments = icom_api_add_item($_POST['post_ID'], $_POST['icom-cart'], $_POST['icom-sourceLang'], $_POST['icom-targetLangs'], $proofreading, $_POST['post_title'], $_POST['content']);
      // pokud není žádný segment k překladu (tzn. uživatel pouze něco smazal)
      if ($segments == false) {
        foreach($_POST['icom-targetLangs'] as $val) {
          icom_segmentation_build($_POST['post_ID'], $_POST['icom-sourceLang'], $val, true);
        }

        set_msg('info', __('There is nothing to translate, so selected languages were updated only.', 'icom'));
    } }

    header("location: " . ICOM_HOST . "wp-admin/post.php?action=edit&post=" . $post_id);
    exit();
  }

  /**
   * aktualizuje orientační cenu a wordcount v toolboxu
   * - zavolá segmentační fci
   * - zjistí počet segmentů dané stránky, které budou potřeba přeložit
   * - spočte wordcount, cenu a tyto zobrazí v toolboxu
   */
  if (isset($_POST['icom-toolbox-refresh'])) {
    if (empty($_POST['icom-targetLangs'])) set_msg('error', __('You have to select one target language at least.', 'icom'));

    if (is_msg() == true) return;

    icom_segmentation($_POST['post_ID'], $_POST['icom-sourceLang'], $_POST['icom-targetLangs'], $_POST['post_title'] . "." . $_POST['content']);

    update_option('icom_toolbox_targets', $_POST['icom-targetLangs']);
    update_option('icom_toolbox_cart', $_POST['icom-toolbox-cart']);
  }

  /**
   * přidání toolboxu do stránky s editací postu
   * - přidá nový metabox do postranního sidebaru, ve kterém se spravují jazykové verze (příp. vkládání do košíku)
   */
  function icom_toolbox() {
    if (!current_user_can('edit_posts')) {
      echo "<span class='icom-error'>" . __("You do not have rights to manage this page", "icom") . "</span>";
      return;
    }

    $type = icom_db_get_post_type($_GET['post']);
    if ($type == "post" || $type == "page")
      add_meta_box('icom-toolbox', __('Stream API - languages', 'icom'), 'icom_toolbox_html', '', 'side', 'high');
    else
      add_meta_box('icom-toolbox-alt', __('Stream API - languages', 'icom'), 'icom_toolbox_alt_html', '', 'side', 'high');
  }

  /**
   * alternativní meta-box pro typy stránek, které nelze přes icom automaticky překládat
   *
   * @param $post_id
   */
  function icom_toolbox_alt_html($post_id) {
    echo "Content of this items can not be translated the same way like e.g. <i>Posts</i> or <i>Pages</i>. But there is a workaround.
          <h4>How to do this?</h4>
          <ol>
            <li>Create <i>TED</i> - Temporary Empty Dummy page</li>
            <li>Copy (CTRL + C) content of items you would like to translate</li>
            <li>Paste (CTRL + V) it into <i>TED</i></li>
            <li>Send <i>TED</i> to translation</li>
            <li>Once you have translated texts back, you can do anything you want with them</li>
          </ol>";
  }

  /**
   * vykreslení toolboxu
   * - vykreslí toolbox při editaci postu a umožní správu jazykových verzí
   *
   * @param post_id
   */
  function icom_toolbox_html($post_id) {
    global $wpdb, $tc, $icom_lang_default, $icom_lang_selected, $icom_lang_all;

    $token = get_option('icom_token');

    // úpravy šablony týkající se preview stránek
    $preview = icom_toolbox_handle_preview($post_id);
    if ($preview == 1) {
      echo "<script>
              jQuery(document).ready(function() { jQuery('#icom-toolbox').remove(); });
            </script>";
      return;
    }

    // editovaná stránka
    $page = $wpdb->get_row("SELECT lang, IF (id_post_parent = '0', id_post, id_post_parent) AS id_post_translation, id_post_parent, preview FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $post_id->ID . "'");
    // košíky uživatele
    $carts = icom_tc_get_carts();

    $select_carts = "<option value=''>--- " . __("select one of your carts", "icom") . " ---</option>";
    if (!empty($carts)) {
      $cart = get_option('icom_toolbox_cart');
      update_option('icom_toolbox_cart', '');

      foreach($carts as $val) {
        $select_carts .= "<option value='" . $val->uri . "' " . ($cart == $val->uri ? "selected='selected'" : "") . ">" . $val->name . "</option>";
    } }

    // pages a posts
    $pages_array = $wpdb->get_results("SELECT p.*, t.* FROM " . $wpdb->prefix . "posts p LEFT JOIN " . $wpdb->prefix . "icom_translations t ON t.id_post = p.ID WHERE (p.post_type = 'post' OR p.post_type = 'page') AND t.lang = '" . $icom_lang_default . "' ORDER BY post_type ASC");
    $pages = "<optgroup label='" . __("Pages", "icom") . "'>";
    $is_group_posts = false;
    foreach($pages_array as $val) {
      if ($is_group_posts == false && $val->post_type == "post") {
        $is_group_posts = true;
        $pages .= "</optgroup><optgroup label='" . __("Posts", "icom") . "'>";
      }

      $pages .= "<option value='" . $val->ID . "' " . ($val->ID == $page->id_post_parent ? "selected='selected'": "") . ">" . $val->post_title . "</option>";
    }
    $pages .= "</optgroup>";

    // výpočet MAX. WC a PRICE
    $targets = get_option('icom_toolbox_targets');
    update_option('icom_toolbox_targets', '');

    $wordcount = 0;
    if (!empty($targets)) {
      $wordcount = $wpdb->get_var("SELECT (SUM(LENGTH(src_text) - LENGTH(REPLACE(src_text, ' ', ''))) + 1) FROM " . $wpdb->prefix . "icom_segments WHERE trg_text = '' AND id_post = '" . $post_id->ID . "' AND trg_lang = '" . $targets[0] . "'");
    }

    // eventuální targetové jazyky
    $parent = $wpdb->get_var("SELECT IF(id_post_parent = '0', id_post, id_post_parent) AS parent FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $post_id->ID . "'");

    $pricelist = icom_api_get_pricelist($page->lang);

    $languages = "";
    $langauge_original = "";
    $langage_this_page = "";

    foreach($icom_lang_selected as $val) {
      $status = "";
      $synch = 0;
      $item = "";
      $price = false;

      $is = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE (id_post = '" . $page->id_post_translation . "' OR id_post_parent = '" . $page->id_post_translation . "') AND lang = '" . $val . "'");
      if ($is === null) {
        $link = "<a href='" . ICOM_HOST . "wp-admin/post-new.php?post_type=" . $post_id->post_type . "&icom-targetCreate-of=" . $page->id_post_translation . "&icom-targetLang=" . $val . "'><img class='icom-action icom-add' src='" . ICOM_PLUGIN_URL . "/img/pages/add.png' title='" . __("Add manually", "icom") . "' /></a>";
      } else {
        $synch = $wpdb->get_var("SELECT synch FROM " . $wpdb->prefix . "icom_translations_synch WHERE (id_post1 = '" . $is . "' AND id_post2 = '" . $_GET['post'] . "') OR (id_post2 = '" . $is . "' AND id_post1 = '" . $_GET['post'] . "')");
        $status = $wpdb->get_var("SELECT post_status FROM " . $wpdb->prefix . "posts WHERE ID = '" . $is . "'");

        if ($status == "trash")
          $link = "<a href='" . ICOM_HOST . "wp-admin/edit.php?post_status=trash&post_type=" . $post_id->post_type . "'><img class='icom-action icom-trash' src='" . ICOM_PLUGIN_URL . "/img/pages/trash.png' title='" . __("In trash", "icom") . "' /></a>";
        else
          $link = "<a href='" . ICOM_HOST . "wp-admin/post.php?post=" . $is . "&action=edit'><img class='icom-action icom-edit' src='" . ICOM_PLUGIN_URL . "/img/pages/edit.png' title='" . __("Edit", "icom") . "' /></a>" .
                  ($synch == 0 ? " <a href='" . ICOM_HOST . "wp-admin/admin.php?page=icom&post=" . $_GET['post'] . "&icom-sourceTargetSynch=" . $is . "'><img class='icom-action icom-unsync' src='" . ICOM_PLUGIN_URL . "/img/pages/not-synch.png' title='" . __("Set as synced", "icom") . "' /></a>" : "");
      }

      // stavy "v košíku"
      $cart = icom_tc_get_cart_by_page($post_id->ID, $page->lang, $val);
      $item = icom_tc_get_item_by_page($post_id->ID, $page->lang, $val);
      if (strpos($item->target_lang_import_real, $column_name) === false && in_array($cart->state, $tc['carts']['filter']['for-pages'])) {
        $link = '<a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $cart->uri . '" title="' . $cart->name . '"><img src="' . ICOM_PLUGIN_URL . 'img/pages/cart.png" /><span>' . $cart->state . '</span></a>';
      }

      if ($page->lang == $val && $icom_lang_default == $val)
        $link = "<span class='action'>" . __("THIS PAGE", "icom") . "<br>" . __("ORIGINAL", "icom") . "</span>";
      else if ($page->lang == $val)
        $link = "<span class='action'>" . __("THIS PAGE", "icom") . "</span>";
      else if ($icom_lang_default == $val)
        $link .= "<span class='action'>" . __("ORIGINAL", "icom") . "</span>";

      if ($pricelist) {
        foreach($pricelist as $val_price) {
          if ($val_price->languageCode == $val) { $price = $val_price->unitPrice->value; break; }
        }
      }

      $item = '<tr>
                <td class="icom-toolbox-check">
                  <input ' . ($page->lang == $val || $price == false || in_array($is_cart->state, $tc['carts']['filter']['for-pages']) ? 'disabled="disabled"' : 'data-icom-price="' . $price . '" onclick="toolbox_calcWaP(\'' . $wordcount . '\');"') . ' type="checkbox" value="' . $val . '" name="icom-targetLangs[]" ' . (is_array($targets) && in_array($val, $targets) ? "checked='checked'" : "") . ' />
                  <img class="icom-flag" src="' . ICOM_PLUGIN_URL . 'img/flags/' . $val . '.png" /><abbr class="icom-abbr icom-lang" title="' . $icom_lang_all[$val]->name . '">' . $val . '</abbr>
                </td>
                <td>' . $link . '</td>
              </tr>';

      if (($page->lang == $val && $icom_lang_default == $val) || $page->lang == $val)
        $language_this_page = $item;
      else if ($icom_lang_default == $val)
        $language_original = $item;
      else
        $languages .= $item;
    }

    /*

    // pokud je stránka nově naimportována a má objednaný proofreading, zobrazit hlášku
    if (icom_tc_get_item_proofreading_by_page($parent, $icom_lang_default, $page->lang) == true) {
      set_msg('error', __('Please, do not edit this page at all, it is prepared for proofreading you ordered.', 'icom'));
    }

    */

    // zobrazení informační hlášky
    echo get_msg();

    if ($page->id_post_parent != 0) {
      echo '<b>' . __("This is translation of:", "icom") . '</b>
            <select>
              <option value="">
              ' . $pages . '
            </select><br>
            <a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&post=' . $post_id->ID . '&icom-sourceDuplicate=' . $page->id_post_parent . '">' . __("Duplicate source content of this page", "icom") . '</a>
            <br><br>';
    }

    echo '<b>' . __("Select which language/s you want to add to Cart for Professional translation:", "icom") . '</b>
          <table class="icom-toolbox-langs">
          <tr>
            <th colspan="2">
              <input id="icom-toolbox-checkAll" type="checkbox" onclick="toggleToolboxCheckboxes();" />
              ' . __("Language", "icom") . '
            </th>
          </tr>'
          . $language_original . $language_this_page . $languages .
          '</table>';

    if (!empty($token)) {

      echo '<table class="icom-toolbox-price"><tr>
            <th>' . __("MAX. TOTAL WC", "icom") . '</th><td><span id="icom-toolbox-totalWc"><i></i> ' . __("words", "icom") . '</span></td>
          </tr><tr>
            <th>' . __("MAX. TOTAL PRICE", "icom") . '</th><td><span id="icom-toolbox-totalPrice"><i></i> ' . $pricelist[0]->unitPrice->currency . '</span></td>
          </tr></table>
          <input name="icom-toolbox-refresh" class="button icom-toolbox-refresh" type="submit" value="' . __("Pre-estimate", "icom") . '" />
          <script>
            jQuery(document).ready(function() {
              toolbox_calcWaP("' . $wordcount . '");
              setTimeout(function () { toolbox_initEditors(); }, 1000);
            });
          </script>

          <h4>' . __("Professional translation", "icom") . '</h4>
          <select name="icom-cart">' . $select_carts . '</select>

          <input type="checkbox" id="icom-proofreading" name="icom-proofreading" value="1" checked="checked" />
          <label for="icom-proofreading" id="icom-proofreading-label">' . __("request proofeading", "icom") . '</label>

          <span class="icom-toolbox-powered">
            ' . __("Powered by", "icom") . '
            <a href="http://www.idioma.com" target="_blank"><img src="' . ICOM_PLUGIN_URL . 'img/ico/idioma-logo.png" /></a>
          </span>

          <input type="hidden" name="icom-sourceLang" value="' . $page->lang . '" />
          <input name="icom-toolbox" class="button button-primary button-large icom-toolbox-add" type="submit" value="' . __("Add selected to cart", "icom") . '" />';
    }

    $permalink = icom_get_post_permalink($post_id->ID);
    echo '<script>
          jQuery(document).ready(function() {
            var permalink = "' . $permalink . '";

            jQuery("#sample-permalink").text(permalink);
            jQuery("#view-post-btn a").attr("href", permalink);
            jQuery("#preview-action a").attr("href", "' . ICOM_HOST . '?p=' . $post_id->ID . '&lang=' . $page->lang . '&preview=true");
            jQuery("#shortlink").val("' . ICOM_HOST . '?p=' . $post_id->ID . '&lang=' . $page->lang . '");
            ' . ($icom_lang_default != $page->lang ? 'jQuery("#edit-slug-buttons").remove();' : '') . '
          });
        </script>';
  }

  /**
   * u preview zobrazí informaci + odkaz pro vytvoření/update skutečné stránky
   * @param $post_id [object]
   */
  function icom_toolbox_handle_preview($post_id) {
    global $wpdb, $tc, $icom_lang_default;

    $post = $wpdb->get_row("SELECT id_post_parent, id_post_orig, preview, lang FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . icom_save_db($post_id->ID) . "'");
    if ($post->preview == 0) return 0;

    $item_db = icom_tc_get_item_by_page($post->id_post_parent, $icom_lang_default, $post->lang);
    $item = icom_api_get_item($item_db->uri);

    foreach($item->targetContents as $val) {
      if ($val->targetLanguage == $post->lang) {
        $lang = $val;
        break;
    } }

    echo "<script>
            jQuery(document).ready(function() {
              jQuery('#minor-publishing-actions').remove();
              html = '<b>" . __('This is only preview page.', 'icom') . "</b>';";

    // pokud není hotový PR, ale je objednán
    if (!$lang->proofreadContent && $item->forProofreading == true) {
      echo "html += '" . __('<br><br>Please wait until we finish proofreading of this translation you ordered.', 'icom') . "';";
    }

    // pokud je již preview naimportována
    if (strpos($item_db->target_lang_import, $lang->targetLanguage) !== false) {
      $url = ICOM_HOST . "wp-admin/admin.php?page=icom&post_type=" . $post_id->post_type . "&icom-importItem=" . $item->uri . "&icom-importItem-real=" . $post_id->ID . "&icom-targetCreate-of=" . $post->id_post_parent . "&icom-targetLang=" . $post->lang . ($post->id_post_orig != 0 ? "&icom-importItem-to=" . $post->id_post_orig : "");
      echo "html += '<br><br><a class=\'button\' href=\'" . $url . "\'>" . __('Publish this preview', 'icom') . "</a>';";
    }

    echo "  jQuery('#major-publishing-actions').html(html);
          });
          </script>";

    return $post->preview;
  }

  /**
   *  úprava seznamu parentovských stránek, které je možné vybrat v meta-boxu na detailu stránky
   */
  function icom_toolbox_exlude_parents($args, $post) {
    global $wpdb;

    $lang = $wpdb->get_var("SELECT lang FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $post->ID . "'");
    $pages = $wpdb->get_results("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE lang != '" . $lang . "' OR preview = '1'");
    $pages2 = array();
    foreach($pages as $page) { $pages2[] = $page->id_post; }

    $args['exclude_tree'] .= ", " . implode(", ", $pages2);
    return $args;
  }
?>
