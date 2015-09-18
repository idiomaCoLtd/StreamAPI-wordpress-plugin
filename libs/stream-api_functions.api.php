<?php

  function debug($icom) {
    var_dump($icom->get_code());
    var_dump($icom->get_error());
    var_dump($icom->get_location());
    var_dump($icom->get_body());
  }

  /**
   * přidání košíku
   * @param $name
   * @param $source
   */
  function icom_api_add_cart($name, $source) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array('name' => $name,
                   'sourceLanguage' => $source);

    try {
      $icom->request("api/uhti/carts", $array, "POST");
    } catch (iComException $ie) { }

    if ($icom->get_code() == 201) {
      icom_tc_add_cart($name, $icom->get_location(), $source);

      set_msg('info', '<a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $icom->get_location() . '">Cart</a> was created.');
    } else {
      set_msg('error', $icom->get_error());
    }
  }

  /**
   * smazání košíku
   * @param uri
   */
  function icom_api_delete_cart($uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api" . $uri, null, 'DELETE');
		} catch (iComException $ie) { }

		if ($icom->get_code() == 204) {
      icom_tc_delete_cart($uri);

      set_msg('info', 'Cart was deleted.');
		} else {
		  set_msg('error', $icom->get_error());
		}
	}

  /**
   * přidání položky do košíku
   * @param $post_id
   * @param $uri
   * @param $source
   * @param $targets
   * @param $proofreading
   * @param $post_title
   * @param $post_content
   */
  function icom_api_add_item($post_id, $uri, $source, $targets, $proofreading, $post_title, $post_content) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    /*
    icom_segmentation($post_id, $source, $targets, "<h1>" . $post_title . "</h1>" . $post_content);
    $segments = array('segments' => 0, 'string' => '');
    foreach($targets as $target) {
      $temp = icom_segmentation_export($post_id, $source, $target);
      if ($temp['segments'] > $segments['segments']) {
        $segments['segments'] = $temp['segments'];
        $segments['string'] = $temp['string'];
    } }

    if ($segments['segments'] == 0) return false;
    */

    $content = icom_segmentation_bbcode($post_content);
    $sourceContent = $post_title . ICOM_SEP_SEG . $content['content'] . ICOM_SEP_SEG . implode(str_replace("{TYPE}", "bbCodeAttr", ICOM_SEP_SEG), $content['attributes']);

    $array = array("name" => $post_title,
                   "customId" => $post_id,
                   "contentType" => "Html",
                   "originalReferenceUrl" => ICOM_HOST . "?p=" . $post_id,
                   "forProofreading" => $proofreading,
                   "sourceContent" => $sourceContent,
                   "targetLanguages" => $targets,
                   "SpecialTags" => $content['regexes'],
                  );

    try {
      $icom->request("api" . $uri . "/items", $array, "POST");
    } catch (iComException $ie) { }

    if ($icom->get_code() == 201) {
      $item = icom_api_get_item($icom->get_location());
      icom_tc_add_item($uri, $post_title, $post_id, $item->uri, $item->wordCount, $source, $targets, $proofreading);
      icom_tc_change_state($uri, 'Setup');

      set_msg('info', '<a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $uri . '">Item</a> was added to cart.');
    } else {
      set_msg('error', $icom->get_error());
    }

    return true;
  }

	/**
	 * smazání itemu
	 * @param $delete - uri pro smazání
	 * @param $uri - uri košíkku
	 */
	function icom_api_delete_item($delete, $uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api" . $delete, null, 'DELETE');
		} catch (iComException $ie) { }

		if ($icom->get_code() == 204) {
      icom_tc_delete_item($delete);
      icom_tc_change_state($uri, 'Setup');

      set_msg('info', 'Item was deleted.');
		} else {
		  set_msg('error', $icom->get_error());
		}
	}

	/**
	 * vrátí vybranou položku
	 * @param uri
	 */
	function icom_api_get_item($uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api" . $uri, null, 'GET');
		} catch (iComException $ie) { }

		return $icom->get_body();
	}

	/**
	 * vrátí stream souboru
	 * @param uri
	 */
	function icom_api_get_file($uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api" . $uri, null, 'GET');
		} catch (iComException $ie) { }

		if ($icom->get_code() == 200) {
		  header("Content-type: application/octet-stream");
      header("Content-disposition: attachment;filename=" . $uri . ".pdf");

      echo $icom->get_body();
    }
	}

	/**
	 * objednání estimace
	 * @param $estimation - uri pro estimaci
	 * @param $uri - uri košíku
	 */
	function icom_api_set_estimation($estimation, $uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array("notificationUri" => ICOM_HOST . "wp-admin/admin.php?page=icom&icom-cart=" . $uri . "&icom-cartNotif=estimation");

    try {
      $icom->request("api" . $estimation, $array, "PUT");
    } catch (iComException $ie) { }

    if ($icom->get_code() == 202) {
      icom_tc_change_state($uri, 'EstimationStarted');

      set_msg('info', '<a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $uri . '">Cart</a> estimation was started.');
    } else {
      set_msg('error', $icom->get_error());
    }
  }

  /**
   * objednání překladu
   * @param $order - uri orderu
   * @param $uri - uri košíku
   */
  function icom_api_set_order($order, $uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array("notificationUri" => ICOM_HOST . "wp-admin/admin.php?page=icom&icom-cart=" . $uri . "&icom-cartNotif=order");

    try {
      $icom->request("api" . $order, $array, "POST");
    } catch (iComException $ie) { }

    if ($icom->get_code() == 202) {
      icom_tc_change_state($uri, 'TranslationStarted');

      set_msg('info', '<a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $uri . '">Cart</a> was ordered.');
    } else {
      set_msg('error', $icom->get_error());
    }
  }

  /**
   * start proofredingu
   * @param $proofreading - uri orderu
   * @param jazyky, pro které se spustí proof
   * @param $uri - uri košíku
   */
  function icom_api_set_proofreading($proofreading, $languages, $uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array("languagesToProofread" => $languages, "notificationUri" => ICOM_HOST . "wp-admin/admin.php?page=icom&icom-cart=" . $uri . "&icom-cartNotif=proofreading");

    try {
      $icom->request("api" . $proofreading, $array, "POST");
    } catch (iComException $ie) { }

    if ($icom->get_code() == 202) {
      icom_tc_sync_state($uri);

      set_msg('info', '<a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $uri . '">Cart</a> proofreading was started.');
    } else {
      set_msg('error', $icom->get_error());
    }
  }

  /**
   * zavření košíku
   * @param $close - uri zavření
   * @param $uri - uri košíku
   */
  function icom_api_set_close($close, $uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api" . $close, null, "POST");
    } catch (iComException $ie) { }

    if ($icom->get_code() == 202) {
      icom_tc_change_state($uri, 'Closed');

      set_msg('info', '<a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $uri . '">Cart</a> was closed and added to archive.');
    } else {
      set_msg('error', $icom->get_error());
    }
  }

  /**
   * smazání jazyka z objednaného košíku
   * @param cancel uri
   * @param languages k uzavření
   * @param uri košíku
   */
  function icom_api_set_cancel($cancel, $languages, $uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array("targetLanguages" => $languages);

    try {
      $icom->request("api" . $cancel, $array, "POST");
    } catch (iComException $ie) { }

    if ($icom->get_code() == 200) {
      icom_tc_set_cancel($languages, $uri);
      icom_tc_sync_state($uri);

      set_msg('info', 'Selected languages - ' . implode(", ", $languages) . ' - were cancelled from <a href="' . ICOM_HOST . 'wp-admin/admin.php?page=icom&icom-cart=' . $uri . '">cart</a>');
    } else {
      set_msg('error', $icom->get_error());
    }
  }

  /**
   * získání podporovaných jazyků
   * @param upravit výpis
   */
  function icom_api_get_languages($edit_return = true) {
    global $api;

    $token = get_option('icom_token');
    if (empty($token)) $token = array('oauth_token' => '', 'oauth_token_secret' => '');

    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api/languages", null, "GET");
    } catch (iComException $ie) { }

    if ($edit_return == true) {
      $return = array();

      $langs = $icom->get_body();
      foreach($langs as $val) { $return[$val->code] = $val; }

      return $return;
    }

    return $icom->get_body();
  }

  /**
   * získání košíků klienta
   * @our - vrátit pouze naše košíky (ty co jsou uložené v naší databázi) - pokud FALSE, vrátí se všechny košíky (i z jiných CMS)
   *
   * POZN. = není nikde použito, ale ponechat, časem se bude hodit
   */
  function icom_api_get_carts($our_carts = true) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api/uhti/carts", null, 'GET');
    } catch (iComException $ie) { }

    // filtr pouze vlastních košíků
    if ($our_carts == true) {
      $return = array();

      $carts = $icom->get_body();
      foreach($carts as $val) {
        if (icom_tc_get_cart($val->uri) == null) continue;
        $return[] = $val;
      }

      return $return;
    }

    return $icom->get_body();
  }

  /**
   * získání jednoho košíku klienta
   * @param uri
   */
  function icom_api_get_cart($uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api" . $uri, null, 'GET');
    } catch (iComException $ie) { }

    return $icom->get_body();
  }

  /**
   * získání položek konkrétního košíku
   * @param uri
   */
  function icom_api_get_cart_items($uri) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api" . $uri, null, 'GET');
    } catch (iComException $ie) { }

    return $icom->get_body();
  }

  /**
   * získání ceníku pro daný jazyk
   * @param $lang
   */
  function icom_api_get_pricelist($lang) {
    global $api;

    $token = get_option('icom_token');
    if (empty($token)) $token = array('oauth_token' => '', 'oauth_token_secret' => '');

    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api/uhti/clientpricelists/" . $lang, null, 'GET');
    } catch (iComException $ie) { }

    if ($icom->get_code() == 200) {
      return $icom->get_body();
    } else {
      set_msg('error', $icom->get_error());
      return;
    }

  }

  /**
   * vytáhne data o přihlášeném uživateli
   */
  function icom_api_get_user() {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    try {
      $icom->request("api/uhti/currentUser", null, "GET");
    } catch (iComException $ie) { }

    return $icom->get_body();
  }

  /**
   * nastavení proofreadingu u položky
   * @param $uri - uri položky
   * @param $state
   */
  function icom_api_set_item_proofreading($uri, $state) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array('forProofreading' => $state);

    try {
      $icom->request("api" . $uri, $array, "PUT");
    } catch (iComException $ie) { }

    icom_tc_set_item_proofreading($uri, ($state == "true" ? 1 : 0));
  }

  /**
   * nastavení targetURL u položky, kvůli proofreadingu
   * @param $uri
   * @param $targetLang
   * @param $targetUrl
   */
  function icom_api_set_item_proofreading_ready($uri, $targetLang, $targetUrl) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array('targetContents' => array('targetLanguage' => $targetLang,
                                             'translatedReferenceUrl' => str_replace("&icom-cartItem=" . $uri, "", $targetUrl),
                                             'activeTranslatedReferenceUrl' => $targetUrl)
                  );

    try {
      $icom->request("api" . $uri, $array, "PUT");
    } catch (iComException $ie) { }

  }

  /**
   * změna položky "customData" u konkrétního target jazyka
   * @param $uri položky
   * @param $lang položky
   * @param $data k uložení
   */
  function icom_api_custom_data($uri, $targetLang, $data) {
    global $api;

    $token = get_option('icom_token');
    $icom = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret'], $token['oauth_token'], $token['oauth_token_secret']);
    $icom->setBaseUrl($api['url']['base']);

    $array = array('targetContents' => array('targetLanguage' => $targetLang,
                                             'customData' => (empty($data) ? "String.Empty" : $data))
                  );

    try {
      $icom->request("api" . $uri, $array, "PUT");
    } catch (iComException $ie) { }
  }
?>
