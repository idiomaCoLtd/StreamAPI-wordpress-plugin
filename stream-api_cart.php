<?php
  function icom_cart() {
    global $wpdb, $tc, $icom_lang_all, $icom_lang_default;

    if (!current_user_can('edit_posts')) {
      echo "<span class='icom-error'>" . __("You do not have rights to manage this page", "icom") . "</span>";
      return;
    }

    echo '<div class="wrap">';
    echo '<h2>' . __("Stream API - carts", "icom") . '</h2>';

    // === POST UDÁLOSTI

    // estimace
    if (isset($_GET['icom-cartEstimation'])) {
      icom_api_set_estimation($_GET['icom-cartEstimation'], $_GET['icom-cart']);
    }
    // order
    if (isset($_GET['icom-cartOrder'])) {
      icom_api_set_order($_GET['icom-cartOrder'], $_GET['icom-cart']);
    }
    // close
    if (isset($_GET['icom-cartClose'])) {
      icom_api_set_close($_GET['icom-cartClose'], $_GET['icom-cart']);
    }
    // proofreading
    if (isset($_GET['icom-languageProofreading'])) {
      icom_api_set_proofreading($_GET['icom-cartProofreading'], $_GET['icom-languageProofreading'], $_GET['icom-cart']);
    }
    // cancel
    if (isset($_GET['icom-languageCancel'])) {
      icom_api_set_cancel($_GET['icom-cartCancel'], $_GET['icom-languageCancel'], $_GET['icom-cart']);
    }
    // delete cart
    if (isset($_GET['icom-cartDelete'])) {
      icom_api_delete_cart($_GET['icom-cartDelete']);
    }
    // delete item
    if (isset($_GET['icom-cartDelete-item'])) {
      icom_api_delete_item($_GET['icom-cartDelete-item'], $_GET['icom-cart']);
    }
    // bulk action u itemů
    if (isset($_GET['icom-bulkAction-action'])) {
      foreach($_GET['icom-bulkAction-items'] as $item) {
        switch($_GET['icom-bulkAction-action']) {
          case "delete":
            icom_api_delete_item($item, $_GET['icom-cart']);
            break;

          case "proofSet":
            icom_api_set_item_proofreading($item, true);
            break;

          case "proofUnset":
            icom_api_set_item_proofreading($item, false);
            break;

          default:
            break;
    } } }
    // označení proofreadingu u konkrétní položky
    if (isset($_GET['icom-setItemProofreading'])) {
      icom_api_set_item_proofreading($_GET['icom-setItemProofreading'], $_GET['icom-setItemProofreading-state']);
      echo "OK"; // zpráva pro JS, že script doběhl
      exit();
    }
    // vytvoření košíku
    if (isset($_POST['icom-addCart'])) {
      if (is_msg() == false) {
        icom_api_add_cart($_POST['icom-addCart-name'], $_POST['icom-addCart-source']);
        unset($_POST);
    } }

    // /=== POST UDÁLOSTI

    // dostupné jazyky pro volbu source jazyka v košíku
    $source = "";
    $source_default = "";
    foreach($icom_lang_all as $val) {
      $source .= '<option value="' .  $val->code . '" ' . ((isset($_POST['icom-addCart-source']) && $_POST['icom-addCart-source'] == $val->code) || (!isset($_POST['icom-addCart-source']) && $val->code == $icom_lang_default) ? "selected='selected'" : "") . '>' . $val->name . '</option>';
      if ($val->code == $icom_lang_default) $source_default = $val->name;
    }

    // zobrazení informační hlášky
    echo get_msg();

    if (!isset($_GET['icom-cartsFilter']['state'])) $_GET['icom-cartsFilter']['state'] = "";

    echo '<div class="tablenav top">

          <div class="alignleft actions">
          ' .
          (isset($_GET['icom-cart'])
          ?
            '<a class="button" href="?page=icom">' . __("&laquo; BACK TO LIST", "icom") . '</a>'
          :
            '<form action="' . ICOM_HOST . 'wp-admin/admin.php" method="get">
            <input type="hidden" name="page" value="icom" />

              <select name="icom-cartsFilter[state]">
                <option value="">Not closed</option>
                <option value="Setup" ' . ($_GET['icom-cartsFilter']['state'] == "Setup" ? "selected='selected'" : "") . '>' . __("Setup", "icom") . '</option>
                <option value="EstimationFinished" ' . ($_GET['icom-cartsFilter']['state'] == "EstimationFinished" ? "selected='selected'" : "") . '>' . __("Estimated", "icom") . '</option>
                <option value="TranslationStarted" ' . ($_GET['icom-cartsFilter']['state'] == "TranslationStarted" ? "selected='selected'" : "") . '>' . __("Translating", "icom") . '</option>
                <option value="TranslationFinished" ' . ($_GET['icom-cartsFilter']['state'] == "TranslationFinished" ? "selected='selected'" : "") . '>' . __("Translated", "icom") . '</option>
                <option value="ProofreadingStarted" ' . ($_GET['icom-cartsFilter']['state'] == "ProofreadingStarted" ? "selected='selected'" : "") . '>' . __("In proofreading", "icom") . '</option>
                <option value="ProofreadingFinished" ' . ($_GET['icom-cartsFilter']['state'] == "ProofreadingFinished" ? "selected='selected'" : "") . '>' . __("Proofreaded", "icom") . '</option>
                <option value="Closed" ' . ($_GET['icom-cart-filter']['state'] == "Closed" ? "selected='selected'" : "") . '>' . __("Closed", "icom") . '</option>
              </select>
              <input class="button" type="submit" value="' . __("Filter carts", "icom") . '" />'
          ) . '
          </div>

          </form>
          <form action="' . ICOM_HOST . 'wp-admin/admin.php?page=icom" method="post">

            <div class="tablenav-pages one-page">
              <input class="button" type="button" value="' . __("NEW CART", "icom") . '" onclick="showDialog(\'icom-dialog-addCart\');"  />
            </div>

            <div id="icom-dialog-addCart" class="icom-dialog">
              <div class="icom-dialog-in" style="width: 500px;">
                <button class="button close" onclick="return showDialog(\'icom-dialog-addCart\', true);">X</button>

                <h3>' . __("Add new cart to your account", "icom") . '</h3>
                <span>' . __("Cart name", "icom") . '</span>
                <br>
                <input type="text" name="icom-addCart-name" id="icom-addCart-name" />
                <br>
                <span>' . __("Source language", "icom") . '</span>
                <br>
                <input class="icom-addCart-source" type="text" readonly="readonly" onclick="showDialog(\'icom-dialog-addCart-changeSource\');" value="' . $source_default . '" />
                <select class="icom-addCart-source" name="icom-addCart-source">
                  ' . $source . '
                </select>
                <br><br>
                <input class="button" type="submit" onclick="return validNewCart();" value="' . __("Add new cart", "icom") . '" name="icom-addCart" />

              </div>
            </div>

            <div id="icom-dialog-addCart-changeSource" class="icom-dialog">
              <div class="icom-dialog-in" style="width: 500px;">
                <button class="button close" onclick="return showDialog(\'icom-dialog-addCart-changeSource\', true);">X</button>

                <h3>' . __("Do you want to change cart source language?", "icom") . '</h3>
                <p>' . str_replace('{LANG}', '<abbr class="icom-abbr" title="' . $icom_lang_all[$icom_lang_default]->name . '">' . $icom_lang_default . '</abbr>', __("Source language of your cart is {LANG}, default language of your website. Do you really want to change it?", "icom")) . '</p>
                <input type="button" class="button" onclick="return changeNewCartSource();" value="' . __("Sure, I know what I am doing", "icom") . '" />

              </div>
            </div>

          </form>

          </div>';

    // SEZNAM KOŠÍKŮ
    if (!isset($_GET['icom-cart'])) {

      echo '<div class="icom-tabs icom-cart">
            <table class="icom-table icom-carts"><tr>
              <th></th>
              <th>' . __("Name", "icom") . '</th>
              <th>' . __("idioma no.", "icom") . '</th>
              <th>' . __("State", "icom") . '</th>
              <th>' . __("Wordcount", "icom") . '</th>
              <th>' . __("Price", "icom") . '</th>
              <th>' . __("Items", "icom") . '</th>
              <th>' . __("Source language", "icom") . '</th>
              <th>' . __("Target languages", "icom") . '</th>
              <th></th>
            </tr>';

      $carts = icom_tc_get_carts(true);
      if (empty($carts)) {

        echo '<tr>
                <td colspan="9">' . __("You have no cart created. Please, type name of cart and select its source language at the top of page to create it.", "icom") . '</td>
              </tr>';

      } else {

        foreach($carts as $val) {
          $cart = icom_api_get_cart($val->uri);

          // synchronizace stavu košíku
          icom_tc_change_state($val->uri, $cart->state);

          // pokud uživatel nastavil filter, vyber pouze ty správné
          if (icom_cart_filter($_GET['icom-cartsFilter']['state'], $cart->state) == false) continue;

          $items = $wpdb->get_var("SELECT COUNT(id) FROM " . $wpdb->prefix . "icom_tc_items WHERE id_cart = (SELECT id FROM " . $wpdb->prefix . "icom_tc_carts WHERE uri = '" . $val->uri . "')");
          $items_proofreading = $wpdb->get_var("SELECT COUNT(id) FROM " . $wpdb->prefix . "icom_tc_items WHERE id_cart = (SELECT id FROM " . $wpdb->prefix . "icom_tc_carts WHERE uri = '" . $val->uri . "') AND proofreading = '1'");

          // targetové jazyky
          $targets = array();
          foreach($cart->targetLanguages as $v) {
            $targets[] = '<abbr class="icom-abbr" title="' . $icom_lang_all[$v]->name . '">' . $v . '</abbr>';
          }

          echo '<tr>
                  <td>' . (in_array($cart->state, $tc['carts']['filter']['for-delete']) ? '<a class="icom-delete" href="?page=icom&icom-cartDelete=' . $val->uri . '" onclick="return confirm(\'' . __("Are you sure to delete cart?", "icom") . '\');">X</a>' : '') . '</td>
                  <td>' . $cart->name . '</td>
                  <td>' . $cart->idiomaNumber . '</td>
                  <td>' . $cart->state . '</td>
                  <td>' . $wpdb->get_var("SELECT SUM(wordcount) FROM " . $wpdb->prefix . "icom_tc_items i, " . $wpdb->prefix . "icom_tc_carts c WHERE c.id = i.id_cart AND c.uri = '" . $val->uri . "'") . '</td>
                  <td>' . ($cart->state == "Setup" ? "- - -" : ($cart->state == "EstimationStarted" ? "<img src='" . ICOM_PLUGIN_URL . "img/ico/loading.GIF' />" : $cart->priceInfo->totalPrice->value . ' ' . $cart->priceInfo->totalPrice->currency)) . '</td>
                  <td>' . $items . '</td>
                  <td><abbr class="icom-abbr" title="' . $icom_lang_all[$cart->sourceLanguage]->name . '">' . $cart->sourceLanguage . '</abbr></td>
                  <td>' . implode(", ", $targets) . '</td>
                  <td>' . '<a href="?page=icom&icom-cart=' . $val->uri . '">' . __("detail", "icom") . '</a>'
                        . (in_array($cart->state, $tc['carts']['filter']['for-estimation']) && !empty($items) ? ' / <a href="?page=icom&icom-cart=' . $val->uri . '&icom-cartEstimation=' . $cart->estimationCommandUri . '" onclick="return confirm(\'' . __("Are you sure to estimate cart?", "icom") . '\');">' . __("estimate", "icom") . '</a>' : '')
                        . (in_array($cart->state, $tc['carts']['filter']['for-order']) ? ' / <a href="?page=icom&icom-cart=' . $val->uri . '&icom-cartOrder=' . $cart->orderCommandUri . '" onclick="return confirm(\'' . __("Are you sure to order cart?", "icom") . '\');">' . __("order", "icom") . '</a>' : '')
                        . (in_array($cart->state, $tc['carts']['filter']['for-close']) ? ' / <a href="?page=icom&icom-cart=' . $val->uri . '&icom-cartClose=' . $cart->closeCommandUri . '" onclick="return confirm(\'' . __("Are you sure to close cart?", "icom") . '\');">' . __("close", "icom") . '</a>' : '')
                  . '</td>
                </tr>';
        }

      }

      echo '</table>
            </div>';

    // DETAIL KONKRÉTNÍHO KOŠÍKU
    } else {

      $tr = "<abbr class='icom-abbr icom-abbr-lighter' title='" . __('Translation', 'icom') . "'>" . __('TR', 'icom') . "</abbr>";
      $pr = "<abbr class='icom-abbr icom-abbr-lighter' title='" . __('Proofreading', 'icom') . "'>" . __('PR', 'icom') . "</abbr>";

      // KOŠÍK a JEHO POLOŽKY
      $cart = icom_api_get_cart($_GET['icom-cart']);
      $items = icom_api_get_cart_items($cart->itemsDetailsUri);
      $pricelist = icom_api_get_pricelist($cart->sourceLanguage);

      // synchronizace stavu košíku
      icom_tc_change_state($cart->uri, $cart->state);

      $wordcount = 0;
      $table = "";
      $languages = array();
      $price = 0;
      $proofreading = false;

      if (!empty($items)) {
        foreach($items as $item) {

          //var_dump($item);

          $imports = array();
          $targetsLangs = array();
          $targetsTexts = array();
          $targetsUrl = array();
          $targetsComments = array();

          if ($item->forProofreading == true) $proofreading = true;
          $wordcount += $item->wordCount;
          $post_type = $wpdb->get_var("SELECT post_type FROM " . $wpdb->prefix . "posts WHERE ID = '" . $item->customId . "'");

          // targetové jazyky
          foreach($item->targetLanguages as $val) {
            if (!in_array($val, $languages)) $languages[] = $val;

            foreach($pricelist as $v) {
              if ($val == $v->languageCode) {
                $price += $v->unitPrice->value * $item->wordCount; break;
            } }

            $targetsLangs[] = '<abbr class="icom-abbr" title="' . $icom_lang_all[$val]->name . '">' . $val . '</abbr>';
          }

          // targetové překlady
          foreach($item->targetContents as $value) {
            if (empty($value->translatedContent)) continue;

            $imported = false;
            $imported_pr = false;

            $target_preview = $wpdb->get_var("SELECT id_post FROM " . $wpdb->prefix . "icom_translations WHERE preview = '1' AND id_post_parent = '" . $item->customId . "' AND lang = '" . $value->targetLanguage . "'");
            // pokud ještě není vytvořené preview
            if ($target_preview == null) {
              $imports[] = "<a href='?page=icom&post_type=" . $post_type . "&icom-targetCreate-of=" . $item->customId . "&icom-importItem=" . $item->uri . "&icom-sourceLang=" . $cart->sourceLanguage . "&icom-targetLang=" . $value->targetLanguage . "'>" . __("import", "icom") . " " . $value->targetLanguage . "</a> " . $tr;

            // pokud již preview pro tuto stránku existuje
            } else {
              $dialog = rand(100, 999);

              $itemDB = $wpdb->get_row("SELECT target_lang_import, proofreading_lang_import FROM " . $wpdb->prefix . "icom_tc_items WHERE uri = '" . $item->uri . "'");
              $imported = (strpos($itemDB->target_lang_import, $value->targetLanguage) !== false ? true : false);
              $imported_pr = (strpos($itemDB->proofreading_lang_import, $value->targetLanguage) !== false ? true : false);

              $import = ($imported == true ? $value->targetLanguage . " " . __("imported", "icom") . " " . $tr . ", " : "");
              $import .= "<a href='javascript:void(0);' onclick='return showDialog(\"icom-dialog-import-" . $dialog . "\");'>" . ($imported == true ? __("again?", "icom") . "</a>" : __("import", "icom") . " " . $value->targetLanguage . "</a> " . $tr);

              // proofreading
              //if (in_array($cart->state, $tc['carts']['filter']['for-import-proofreading'])) {
              if (in_array($value->targetLanguage, $cart->languagesWithFinishedProofreading)) {

                $import .= " / ";
                if ($item->forProofreading == true && $value->proofreadingOk == false) {
                  $import .= ($imported_pr == true
                              ?
                                $value->targetLanguage . " imported " . $pr . ", <a href='javascript:void(0);' onclick='return showDialog(\"icom-dialog-import-" . $dialog . "-proof\");'>" . __("again?", "icom") . "</a>"
                              :
                                "<a href='javascript:void(0);' onclick='return showDialog(\"icom-dialog-import-" . $dialog . "-proof\");'>" . __("import", "icom") . " " . $value->targetLanguage . "</a> " . $pr
                              ) .
                              "<div id='icom-dialog-import-" . $dialog . "-proof' class='icom-dialog'>
                                <div class='icom-dialog-in' style='width: 500px;'>

                                  <button class='button close' onclick='return showDialog(\"icom-dialog-import-" . $dialog . "-proof\", true);'>X</button>
                                  <h3>" . __("Do you really want to import proofreading content?", "icom") . "</h3>
                                  <p>" . __("The original content of this page will be overwritten by this proofreading content.", "icom") . "</p>
                                  <a class='button' href='?page=icom&post_type=" . $post_type . "&icom-importItem=" . $item->uri . "&icom-sourceLang=" . $cart->sourceLanguage . "&icom-targetLang=" . $value->targetLanguage . "&icom-importItem-to=" . $target_preview . "&icom-importItem-option=proofreading'>" . __("Yes, I do", "icom") . "</a>

                                </div>
                              </div>";
                } else {
                  $import .= $value->targetLanguage . " " . $pr . " " . __("is OK", "icom");
                  icom_tc_change_import_langs($item->uri, $value->targetLanguage, "proofreading_lang_import");
                }

              }

              // nalezení případného reálné stránky k tomuto preview
              //$target_real = $wpdb->get_var("SELECT id_post_orig FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $target_preview . "' AND lang = '" . $value->targetLanguage . "'");
              //if ($target_real != null) $target_preview = $target_real . "|" . $target_preview;

              // importy překladů
              $import .= "<div id='icom-dialog-import-" . $dialog . "' class='icom-dialog'>
                            <div class='icom-dialog-in' style='width: 500px;'>

                              <button class='button close' onclick='return showDialog(\"icom-dialog-import-" . $dialog . "\", true);'>X</button>
                              <h3>" . __("What do you want to do now?", "icom") . "</h3>
                              <p>" . __("This page already exists with another content. Select one option:", "icom") . "</p>
                              <select onchange='jQuery(\"#icom-dialog-import-" . $dialog . "-submit\").attr(\"href\", \"?page=icom&post_type=" . $post_type . "&icom-importItem=" . $item->uri . "&icom-sourceLang=" . $cart->sourceLanguage . "&icom-targetLang=" . $value->targetLanguage . "&icom-importItem-to=" . $target_preview . "&icom-importItem-option=\" + this.value);'>
                                <option value=''>--- " . __("select one option", "icom") . " ---</option>
                                <option value='over'>" . __("overwrite original content", "icom") . "</option>
                                <option value='before'>" . __("insert before original content", "icom") . "</option>
                                <option value='after'>" . __("insert after original content", "icom") . "</option>
                              </select>
                              <p>" . __("NOTE: New content will be inserted between signs <i>&lt;!--&nbsp;ICOM_START&nbsp--&gt;</i> and <i>&lt;!--&nbsp/ICOM_END&nbsp--&gt;</i>, which are visible in HTML mode.", "icom") . "</p>
                              <a href='#' class='button' id='icom-dialog-import-" . $dialog . "-submit'>" . __("OK", "icom") . "</a>

                            </div>
                          </div>";

              $imports[] = $import;
            }

            // překlady z TC
            $targetsTexts[] = icom_substr($value->translatedContent, 0, true, __("show", "icom") . " " . $value->targetLanguage) .
                              // odkazy na target jazyky
                              ($imported == true && $item->forProofreading == true
                              ?
                                " / <a href='" . $value->translatedReferenceUrl . "' onclick='window.open(this.href); return false;'>" . $value->targetLanguage . " " . __("page", "icom") . "</a> " . $tr .
                                (in_array($value->targetLanguage, $cart->languagesWithFinishedProofreading) && !empty($value->customData) // PR komentáře z sNotes
                                ?
                                  ($imported_pr == false
                                  ?
                                    " / <a href='" . $value->activeTranslatedReferenceUrl . "' onclick='window.open(this.href); return false;'>" . __("comments", "icom") . "</a> " . $pr
                                  :
                                    ""
                                  )
                                :
                                  ""
                                )
                              :
                                ""
                              );

            // komentáře proofreaderů
            $targetsComments[] = (in_array($value->targetLanguage, $cart->languagesWithFinishedProofreading) && !empty($value->proofreadersComment) ? icom_substr($value->proofreadersComment, 0, true, __("comments", "icom")) . " " . $pr : "");
          }

          // položka košíku
          $table .= "<tr>
                      <td class='item-check'>
                        <input type='checkbox' name='item[]' value='" . $item->uri . "' />
                      </td>
                      <td>" . $item->name . "</td>
                      <td>" . $item->wordCount . "</td>
                      <td>
                        <input type='checkbox' " . ($item->forProofreading == true ? "checked='checked'" : "") . " " . (in_array($cart->state, $tc['carts']['filter']['for-estimation']) ? "onclick='changeItemProofreading(this, \"" . ICOM_HOST . "wp-admin/admin.php?page=icom\", \"" . $item->uri . "\");'" : "disabled='disabled'") . " />
                        " . implode("<br>", $targetsComments) . "
                      </td>
                      <td><a href='" . $item->originalReferenceUrl . "' onclick='window.open(this.href); return false;'>" . __("page", "icom") . "</a> / " . icom_substr($item->sourceContent, 0, true) . "</td>
                      <td>" . implode("<br>", $targetsLangs) . "</td>
                      <td>" . implode("<br>", $targetsTexts) . "</td>
                      <td class='item-action'>" . implode("<br>", $imports) . "</td>
                      <td class='item-delete'>" . (in_array($cart->state, $tc['carts']['filter']['for-delete']) ? "<a class='icom-delete' href='?page=icom&icom-cart=" . $_GET['icom-cart'] . "&icom-cartDelete-item=" . $item->uri . "' onclick='return confirm(\"" . __("Are you sure to delete item?", "icom") . "\");'>X</a>" : "&nbsp;") . "</td>
                    </tr>";

        } // foreach items
      }

      echo '<div class="icom-tabs icom-cart">
              <h2>' . $cart->name . '</h2>

              <table class="icom-cart-details">
                <tr class="icom-cart-details-status">
                  <th>' . __("Status:", "icom") . '</th><td>' . $cart->state . '</td>
                </tr><tr>
                  <th>' . __("idioma number:", "icom") . '</th><td>' . $cart->idiomaNumber . '</td>
                </tr><tr>
                  <th>' . __("Source language:", "icom") . '</th><td>' . $icom_lang_all[$cart->sourceLanguage]->name . '</td>
                </tr>
              </table>

              <table class="icom-cart-prices">
                <tr class="icom-cart-prices-price">
                  <th>Total price:</th>
                  <td>' . (empty($items)
                          ?
                            ''
                          :
                            ($cart->state == "Setup" || $cart->state == "EstimationStarted"
                            ?
                              __("max.", "icom") . ' <b>' . $price . '</b> ' . $pricelist[0]->unitPrice->currency
                            :
                              '<b>' . $cart->priceInfo->totalPrice->value . '</b> ' . $cart->priceInfo->totalPrice->currency
                            )
                          ) .
                  '</td>
                  <td rowspan="3">' . (!empty($cart->accountingPdfUri) ? '<a href="?page=icom&icom-cartPdf=' . $cart->accountingPdfUri . '"><img src="' . ICOM_PLUGIN_URL . 'img/ico/pdf.png" /></a>' : '') . '</td>
                </tr><tr class="icom-cart-prices-wcs">
                  <th>' . __("Total wordcount:", "icom") . '</th>
                  <td>' . $wordcount . ' ' . __("words (of source pages)", "icom") . '</td>
                </tr><tr class="icom-cart-prices-langs">
                  <th>' . __("Amount of languages:", "icom") . '</th>
                  <td>' . count($languages) . ' - ' . implode(", ", $languages) . '</td>
                </tr>
              </table>

              <div class="icom-cart-actions">' .
                // DELETE
                (in_array($cart->state, $tc['carts']['filter']['for-delete']) ? "<a class='button icom-delete' href='?page=icom&icom-cartDelete=" . $cart->uri . "' onclick='return confirm(\"" . __("Are you sure to delete cart?", "icom") . "\");'>X</a>" : "") .
                // ESTIMATION
                (in_array($cart->state, $tc['carts']['filter']['for-estimation']) && !empty($items) ? "<a class='button icom-action' href='?page=icom&icom-cart=" . $_GET['icom-cart'] . "&icom-cartEstimation=" . $cart->estimationCommandUri . "' onclick='return confirm(\'" . __("Are you sure to estimate cart?", "icom") . "\');'>" . __("ESTIMATE CART", "icom") . "</a>" : "") .
                // REFRESH
                (in_array($cart->state, $tc['carts']['filter']['for-refresh']) ? "<a class='button icom-action' href='?page=icom&icom-cart=" . $_GET['icom-cart'] . "'>" . __("REFRESH CART", "icom") . "</a>" : "") .
                // ORDER
                (in_array($cart->state, $tc['carts']['filter']['for-order']) ? "<a class='button icom-action' href='?page=icom&icom-cart=" . $_GET['icom-cart'] . "&icom-cartOrder=" . $cart->orderCommandUri . "' onclick='return confirm(\'" . __("Are you sure to order cart?", "icom") . "\');'>" . __("ORDER CART", "icom") . "</a>" : "") .
                // CLOSE
                (in_array($cart->state, $tc['carts']['filter']['for-close']) ? "<a class='button icom-action' href='?page=icom&icom-cart=" . $_GET['icom-cart'] . "&icom-cartClose=" . $cart->closeCommandUri . "' onclick='return confirm(\'" . __("Are you sure to close cart?", "icom") . "\');'>" . __("CLOSE CART", "icom") . "</a>" : "") . '

                <select id="select-cart-bulk-action" size="1" onchange="showCartBulkAction(this.value, \'' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $_GET['icom-cart'] . '\');">
                  <option value="">' . __('Language bulk actions', 'icom') . '</option>
                  ' .
                  (!empty($items) ? '<option value="Cancel;' . $cart->cancelLanguagesUri . '">' . __('Cancel languages', 'icom') . '</option>' : '') .
                  ($proofreading == true ? '<option value="Proofreading;' . $cart->proofreadingCommandUri . '">' . __('Proofread languages', 'icom') . '</option>' : '')
                  . '
                </select>
                <input type="hidden" id="icom-cart-bulk-data-url" />
                <input type="hidden" id="icom-cart-bulk-data-action" />
                <a class="button icom-apply" href="javascript:void(0);" onclick="urlCartBulkAction();">' . __('Apply', 'icom') . '</a>
              </div>';

      $bulk_cancel = "";
      $bulk_proof = "";
      foreach($cart->targetLanguages as $val) {
        $bulk_item = '<span class="icom-checks icom-langs">
                        <input id="item-' . $val . '" type="checkbox" value="' . $val . '" onchange="jQuery(this).parent().toggleClass(\'icom-active\');" />
                        <label for="item-' . $val . '">' . $icom_lang_all[$val]->name . '</label>
                      </span>';

        if (!in_array($val, $cart->languagesWithFinishedTranslation)) {
          $bulk_cancel .= $bulk_item;
        } else {
          if (!in_array($val, $cart->languagesWithStartedProofreading) && !in_array($val, $cart->languagesWithFinishedProofreading) && $proofreading == true) {
            $bulk_proof .= $bulk_item;
      } } }

      echo '<div id="icom-cart-bulk-Cancel" class="icom-cart-bulk-action">
              ' . $bulk_cancel . '
              <div class="icom-cleaner"></div>
            </div>
            <div id="icom-cart-bulk-Proofreading" class="icom-cart-bulk-action">
              ' . $bulk_proof . '
              <div class="icom-cleaner"></div>
            </div>';

      echo '<select id="select-cart-bulk-action-item" size="1">
              <option value="">' . __('Item bulk actions', 'icom') . '</option>
              <option value="proofSet">' . __('Set proofreading', 'icom') . '</option>
              <option value="proofUnset">' . __('Unset proofreading', 'icom') . '</option>
              <option value="delete">' . __('Delete', 'icom') . '</option>
            </select>
            <a class="button icom-apply" href="javascript:void(0);" onclick="urlItemsBulkAction(\'' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $_GET['icom-cart'] . '\');">' . __('Apply', 'icom') . '</a>';

      echo '<table class="icom-table icom-cart"><tr>
              <th class="item-check">
                <input type="checkbox" onclick="unCheckAll(this)" />
              </th>
              <th>' . __("Name", "icom") . '</th>
              <th>' . __("Wordcount", "icom") . '</th>
              <th>' . __("Proofreading", "icom") . '</th>
              <th>' . __("Source texts", "icom") . '</th>
              <th>' . __("Targets", "icom") . '</th>
              <th>' . __("Targets texts", "icom") . '</th>
              <th class="item-action"></th>
              <th class="item-delete"></th>
            </tr>
            ' . $table . '
            </table>
          </div>';

    }

    echo '</div>'; // div.wrap
  }

  /**
   * funkce filtruje košíky dle stavu
   * @param $filter - stav vybraný uživatelem
   * @param $state - stav košíku
   */
  function icom_cart_filter($filter, $state) {
    if (empty($filter) && $state == "Closed") return false;
    if (!empty($filter) && $filter != $state) return false;

    return true;
  }
?>
