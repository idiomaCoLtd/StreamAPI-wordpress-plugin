<?php
  require_once ABSPATH . "wp-admin/includes/upgrade.php";

  /**
   * Nastavi strukturu permalinku na custom a vlozi
   * definovanou hodnotu
   *
   * @global type $wp_rewrite
   */
  function icom_set_permalink_struct() {
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure(ICOM_PERMALINK_STRUCT);
  }

  function icom_settings() {
    global $wpdb, $api, $icom_lang_all, $icom_lang_default, $icom_lang_selected;

    $icom_lang_default_front = get_option('icom_language_default_front');
    $icom_lang_selected_front = get_option('icom_language_selected_front');
    sort($icom_lang_selected_front);

    if (!current_user_can('manage_options')) {
      set_msg('error', __('You do not have rights to manage this page', 'icom'));
      echo get_msg();
      return;
    }

    if (isset($_GET['icom-bbcode-checkNew'])) {
      update_option('icom_bbcode_check', $_GET['icom-bbcode-checkNew']);
      echo "OK";
      exit();
    }

    if (isset($_POST['icom-permastruct-allow'])) {
      update_option('icom_rewrite_permastruct', $_GET['icom-permastruct-allow']);
      echo "OK";
      exit();
    }

    if (isset($_GET['icom-bbcode-delete'])) {
      $wpdb->query("DELETE FROM " . $wpdb->prefix . "icom_bbcode WHERE id = '" . icom_save_db($_GET['icom-bbcode-delete']) . "'");
    }

    if (isset($_POST['bbcode-submit'])) {
    	$error = "";
    	if ($wpdb->get_var("SELECT count(id) FROM " . $wpdb->prefix . "icom_bbcode WHERE name LIKE '" . icom_save_db($_POST['bbcode-name']) . "'") > 0) $error .= __('This BB tag is already saved', 'icom');

    	if (empty($error)) {
      	$regex = "\\\[" . $_POST['bbcode-name'] . "( ?[\\\w\\\-]+=\"[^\"]*\")*\\\]";
      	if (isset($_POST['bbcode-paired'])) $regex .= "\n\\\[/" . $_POST['bbcode-name'] . "\\\]";

      	$wpdb->query("INSERT INTO " . $wpdb->prefix . "icom_bbcode (name, attrs, regex) VALUES ('" . icom_save_db($_POST['bbcode-name']) . "', '" . icom_save_db($_POST['bbcode-attrs']) . "', '" . $regex . "')");

      	set_msg('info', __('New BB tag was successfully added'));
      } else {
      	set_msg('error', $error);
      }
      echo get_msg();
    }

    if (isset($_POST['permastruct-submit'])) {
      icom_set_permalink_struct();
    }

    $token = get_option('icom_token');

    echo '<div class="wrap">';
    echo '<h2>' . __('Stream API - settings', 'icom') . '</h2>';

    echo '<script>
            jQuery(document).ready(function() {
              var tab = location.href.split("#")[1];
              if (tab) tabs(tab);
            });
          </script>';

    //taby
    echo '<a id="icom-tab_0_a" class="icom-tabs" onclick="tabs(\'icom-tab_0\');" href="' . ICOM_ACTUAL . '#icom-tab_0">' . __('Authorization', 'icom') . '</a>
          <a id="icom-tab_1_a" class="icom-tabs icom-active" onclick="tabs(\'icom-tab_1\');" href="' . ICOM_ACTUAL . '#icom-tab_1">' . __('Languages', 'icom') . '</a>
          <a id="icom-tab_2_a" class="icom-tabs" onclick="tabs(\'icom-tab_2\');" href="' . ICOM_ACTUAL . '#icom-tab_2">' . __('BB code', 'icom') . '</a>';
          //<a id="icom-tab_3_a" class="icom-tabs" onclick="tabs(\'icom-tab_3\');" href="' . ICOM_ACTUAL . '#icom-tab_3">' . __('Settings', 'icom') . '</a>';

    if (empty($api['consumer'])) {
      set_msg('error', __('You have to set consumer token.', 'icom'));
      echo get_msg();
    } else {
      icom_authorization();
    }

    // ulozeni "default-language" a "default-front-language"
    if (isset($_GET['icom-language-action'])) {
      switch($_GET['icom-language-action']) {
        case "default":
          update_option('icom_language_default', $_GET['icom-language-code']);
          break;

        case "default-front":
          update_option('icom_language_default_front', $_GET['icom-language-code']);
          break;

        default:
          break;
      }
      echo "<script> window.location = '" . ICOM_HOST . "wp-admin/admin.php?page=icom-settings'; </script>";
    }

    // ulozeni "selected languages"
    if (isset($_POST['icom-language-selected-save'])) {
      $_POST['language_selected'][] = get_option('icom_language_default');
      update_option('icom_language_selected', $_POST['language_selected']);

      echo "<script> window.location = '" . ICOM_HOST . "wp-admin/admin.php?page=icom-settings'; </script>";
    }

    // ulozeni "selected languages"
    if (isset($_POST['icom-language-selected-front-save'])) {
      $_POST['language_selected_front'][] = get_option('icom_language_default_front');
      update_option('icom_language_selected_front', $_POST['language_selected_front']);

      echo "<script> window.location = '" . ICOM_HOST . "wp-admin/admin.php?page=icom-settings'; </script>";
    }

    $language = array('default' => '', 'selected' => '',
                      'default_front' => '', 'selected_front' => '',
                      'all' => '', 'all_selected' => '');

    foreach($icom_lang_all as $val) {

      $flag = (file_exists(__DIR__ . '/img/flags/' . $val->code . '.png')
              ?
                '<img src="' . ICOM_PLUGIN_URL . 'img/flags/' . $val->code . '.png" alt="' . $val->code . '" />'
              :
                $flag = '[' . $val->code . ']');

      // vsechny jazyky
      $language['all'] .= '<span class="icom-checks icom-langs ' . (in_array($val->code, $icom_lang_selected) ? 'icom-active' : '') . '">
                            <input type="checkbox" name="language_selected[]" id="lang_' . $val->code . '" value="' . $val->code . '" ' . ($val->code == $icom_lang_default ? 'checked="checked" disabled="disabled"' : '') . (in_array($val->code, $icom_lang_selected) ? 'checked="checked"' : '') . ' onchange="jQuery(this).parent().toggleClass(\'icom-active\');" />
                            <label for="lang_' . $val->code . '">' . $flag . '<span>' . $val->name .'</span></label>
                           </span>';

      if (in_array($val->code, $icom_lang_selected)) {
        // vsechny "selected" jazyky
        $language['all_selected'] .= '<span class="icom-checks icom-langs ' . (in_array($val->code, $icom_lang_selected_front) ? 'icom-active' : '') . '">
                                        <input type="checkbox" name="language_selected_front[]" id="lang_' . $val->code . '_front" value="' . $val->code . '" ' . ($val->code == $icom_lang_default_front ? 'checked="checked" disabled="disabled"' : '') . (in_array($val->code, $icom_lang_selected_front) ? 'checked="checked"' : '') . ' onchange="jQuery(this).parent().toggleClass(\'icom-active\');" />
                                        <label for="lang_' . $val->code . '_front">' . $flag . '<span>' . $val->name .'</span></label>
                                      </span>';

  			$span = '<span class="icom-checks icom-langs icom-langs-selected {ACTIVE-CLASS}" onclick="showDialog(\'icom-dialog-languageAction-{HASH}\');">
                    <label>' . $flag . ' <span>' . $val->name .'</span></label>
                  </span>
                  <div id="icom-dialog-languageAction-{HASH}" class="icom-dialog">
                    <div class="icom-dialog-in" style="width: 500px;">
                      <button class="button close" onclick="return showDialog(\'icom-dialog-languageAction-{HASH}\', true);">X</button>

                      <h3>'. __('Set as default language', 'icom').'</h3>
                      <p>'. __('Do you want to set this language as default?', 'icom').'</p>

                      <a class="button" href="?page=icom-settings&icom-language-code=' . $val->code . '&icom-language-action={ACTION}" id="icom-dialog-{HASH}-languageAction-submit">'. __('Yes', 'icom').'</a>
                    </div>
                  </div>';

        $hash = rand(100, 999);
        // icom_lang_selected
        if ($val->code == $icom_lang_default)
          $language['default'] .= str_replace(array("{ACTION}", "{HASH}", "{ACTIVE-CLASS}"), array("default", $hash, "icom-active"), $span);
        else
          $language['selected'] .= str_replace(array("{ACTION}", "{HASH}", "{ACTIVE-CLASS}"), array("default", $hash, ""), $span);

        $hash = rand(100, 999);
        // icom_lang_selected_front
        if ($val->code == $icom_lang_default_front)
          $language['default_front'] .= str_replace(array("{ACTION}", "{HASH}", "{ACTIVE-CLASS}"), array("default-front", $hash, "icom-active"), $span);
        else if (in_array($val->code, $icom_lang_selected_front))
          $language['selected_front'] .= str_replace(array("{ACTION}", "{HASH}", "{ACTIVE-CLASS}"), array("default-front", $hash, ""), $span);
      }
    }

    // TAB S JAZYKY
    echo '<div id="icom-tab_1_div" class="icom-tabs icom-active">
            <form action="' . ICOM_ACTUAL . '" method="post">
              <h3>'. __('Selected languages of website', 'icom').'</h3>
              <p>
                1) Click on <i>Add / remove languages</i> button to select languages of your website.<br>
                2) Click on one of selected language to set it as default language of your website. To change default language, just click another selected language.<br>
                3) FYI : Website default language is the green one.
              </p>
              <button class="button" onclick="return showDialog(\'icom-dialog-languages\');">'. __('Add / remove languages', 'icom').'</button>
              <br><br>
              ' . $language['default'] . $language['selected'] . '
              <div class="icom-cleaner"></div>
              <br><br>

              <h3>'. __('Available languages of website', 'icom').'</h3>
              <p>
                1) Click on <i>Add / remove languages</i> button to select languages you want to be available for website visitors.<br>
                2) Click on one of selected language to set it as default language.
              </p>
              <button class="button" onclick="return showDialog(\'icom-dialog-languages-front\');">'. __('Add / remove languages', 'icom').'</button>
              <br><br>
              ' . $language['default_front'] . $language['selected_front'] . '
              <div class="icom-cleaner"></div>

              <div id="icom-dialog-languages" class="icom-dialog">
                <div class="icom-dialog-in" style="width: 90%;">
                  <button class="button close" onclick="return showDialog(\'icom-dialog-languages\', true);">X</button>
                  <button class="button save" name="icom-language-selected-save">'. __('Save', 'icom').'</button>

                  <h3>'. __('Supported languages', 'icom').'</h3>
                  ' . $language['all'] . '
                  <div class="icom-cleaner"></div>
                </div>
              </div>

              <div id="icom-dialog-languages-front" class="icom-dialog">
                <div class="icom-dialog-in" style="width: 50%;">
                  <button class="button close" onclick="return showDialog(\'icom-dialog-languages-front\', true);">X</button>
                  <button class="button save" name="icom-language-selected-front-save">'. __('Save', 'icom').'</button>

                  <h3>'. __('Available languages', 'icom').'</h3>
                  ' . $language['all_selected'] . '
                  <div class="icom-cleaner"></div>
                </div>
              </div>

            </form>
          </div>';

    $bbCodeCheck = get_option('icom_bbcode_check');
    // TAB S BB-TAGY
    echo '<div id="icom-tab_2_div" class="icom-tabs">
            <h3>' . __('Why we need to specify BB tags?', 'icom') . '</h3>
            <p>' . __('BB tags are special tags <a target="_blank" href="http://en.wikipedia.org/wiki/BBCode">(read more)</a> used by webmasters. Their purpose is format and style your texts without any knowledge of HTML or CSS technology. We need to know when square brackets means BB tags and when are used only for formatting your text. And that is why we need to specify used BB tags.', 'icom') . '</p>
            <br>

            <h3>' . __('Add new BB tag', 'icom') . '</h3>
            <input type="checkbox" onclick="saveCheckbox(this, \'' . ICOM_ACTUAL . '\', \'icom-bbcode-checkNew\');" ' . ($bbCodeCheck == "true" ? "checked='checked'" : "") . ' /> ' . __('Check new BB codes before texts are added to cart and translated') . '
            <br><br>

            <form action="' . ICOM_ACTUAL . '" method="post" accept-charset="utf-8" onsubmit="return validateSaveBBcodeForm();">
              <input class="icom-text bbcode-name" type="text" name="bbcode-name" id="bbcode-name" placeholder="' . __('BB tag name', 'icom') . '" />
              <input class="icom-text bbcode-attrs" type="text" name="bbcode-attrs" id="bbcode-attrs" placeholder="' . __('atributes to translate (use comma to separate more)', 'icom') . '" />
              <input class="icom-checkbox bbcode-paired" type="checkbox" name="bbcode-paired" id="bbcode-paired" /><label for="bbcode-paired">' . __('Paired BB tag', 'icom') . '</label>
              <br>
              <input class="icom-submit button" type="submit" name="bbcode-submit" value="' . __('Save', 'icom') . '" />
            </form>

            <br>

            <h3>Saved BB codes</h3>
            <table class="icom-table icom-bb-code">
              <tr>
                <th>' . __('Name', 'icom') . '</th>
                <th>' . __('Attributes to translate', 'icom') . '</th>
                <th>' . __('Regex', 'icom') . '</th>
                <th></th>
              </tr>';

    $regexs = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "icom_bbcode ORDER BY id DESC");
    foreach($regexs as $regex) {
      echo '<tr>
              <td>' . $regex->name . '</td>
              <td>' . $regex->attrs . '</td>
              <td>' . nl2br($regex->regex) . '</td>
              <td><a href="' . ICOM_ACTUAL . '&icom-bbcode-delete=' . $regex->id . '" class="icom-delete" onclick="return confirm(\'' . __('Are you sure?', 'icom') . '\');">X</a></td>
            </tr>';
    }

    echo '  </table>
          </div>';

    // TAB SETTINGS
    /*
     *  Pri klinkuti na 'Set permalink structure for one times' potrebuji zavolat fci z icom_init.php icom_set_permalink_struct()
     */
    /*
    $permaStruct = get_option('icom_rewrite_permastruct');
    echo '<div id="icom-tab_3_div" class="icom-tabs">
            <h3>' . __('Permalink structure', 'icom') . '</h3>
            <p>' . __('You must set permalink structure like this for proper functionality Stream-API plugin:', 'icom') . ' <strong>/%lang%/%icompage%/</strong></p>

            <form action="' . ICOM_ACTUAL . '" method="post" accept-charset="utf-8">
              <button class="button" name="permastruct-submit">' . __('Set permalink structure now', 'icom') . '</button>
            </form>
            <br><br>

            <input type="checkbox" onclick="saveCheckbox(this, \'' . ICOM_ACTUAL . '\', \'icom-permastruct-allow\');" ' . ($permaStruct == "true" ? 'checked="checked"' : '') . ' /> ' . __('Allow Wordpress permanently rewrite permalink structure for Stream-API plugin usuage', 'icom') . '
          </div>';

    echo '</div>'; // div.wrap
    */
  }

  /**
   * OAuth authorization process with TC server
   * @global TC_OAuth $api
   */
  function icom_authorization() {
    global $api, $tc;

    if (empty($api['consumer'])) {
      set_msg('error', __('You must set Consumer token.', 'icom'));
      echo get_msg();
    }

    $sandbox = get_option('icom_sandbox');
    $token = get_option('icom_token');

    $oauth = new TC_OAuth($api['consumer']['key'], $api['consumer']['secret']);
    $oauth->setBaseUrl($api['url']['base']);

    $_wpnonce = wp_create_nonce('icom-settings-authSandbox');
    // TAB S AUTORIZACÍ
    echo '<div id="icom-tab_0_div" class="icom-tabs">
          <h3>'. __('Account type', 'icom').'</h3>
          <input name="icom-service" type="radio" ' . ($sandbox == "true" ? 'checked="checked"' : '') . (empty($sandbox) ? 'onclick="if (confirm(\'Are you sure to connect in sandbox mode?\')) window.location = \'?page=icom-settings&icom-authSandbox=true&_wpnonce=' . $_wpnonce . '\';"' : 'disabled="disabled"') . ' /> '. __('Run in Sandbox mode', 'icom').' (http://icom.tc6.idioma.com)
          <br>
          <input name="icom-service" type="radio" ' . ($sandbox == "false" ? 'checked="checked"' : '') . (empty($sandbox) ? 'onclick="if (confirm(\'Are you sure to connect in production mode?\')) window.location = \'?page=icom-settings&icom-authSandbox=false&_wpnonce=' . $_wpnonce . '\';"' : 'disabled="disabled"') . ' /> '. __('Run in Production mode', 'icom').' (http://tc6.idioma.com)
          <br>';

    if (!empty($sandbox)) {

      // NEMAME ZADNY TOKEN
      if (!isset($_SESSION['request_token'])) {

        try {
            $oauth->request($api['url']['service'], null, "POST");

            parse_str($oauth->get_body(), $requestToken);

            $_SESSION['request_token'] = $requestToken['oauth_token'];
            $_SESSION['request_token_secret'] = $requestToken['oauth_token_secret'];

        } catch(iComException $ue) { }

      // MAME REQUEST_TOKEN a POTREBUJEME ACCESS_TOKEN
      } elseif (empty($token)) {
        $oauth->setToken($_SESSION['request_token'], $_SESSION['request_token_secret']);
      	try {
      		$oauth->request($api['url']['access'], null, "POST");
      		if (strpos($oauth->get_body(), "oauth_token") !== false && strpos($oauth->get_body(), "oauth_token_secret") !== false) {
      		  parse_str($oauth->get_body(), $accessToken);
            update_option('icom_token', $accessToken);

            unset($_SESSION['request_token']);
            unset($_SESSION['request_token_secret']);
          }
      	} catch(iComException $ue) { }

      }

    } else {
      echo '<div class="error icom-notice-error"><p>' . __('This installation of the idioma Stream-API is not paired yet to any client\'s user account at idioma server, neither to free sandbox account.<br>Please get it paired, before you start using it.', 'icom') . '</p></div>';
    }

    $token = get_option('icom_token');
    if (!empty($token)) {

      $disconnect = true;
      $carts = icom_tc_get_carts(true);
      foreach($carts as $val) {
        if (!in_array($val->state, $tc['carts']['filter']['for-close']) && $val->state == "Closed") { $disconnect = false; break; }
      }

      if ($disconnect == true) {
        $_wpnonce = wp_create_nonce('icom-settings-authSandbox');
        echo '<br><a href="?page=icom-settings&icom-authSandbox=&_wpnonce=' . $_wpnonce . '" class="button" onclick="return confirm(\'' . ($sandbox == "false" ? __('CAUTION PLEASE\n\nMake sure all your translated pages were imported. If you switch from Production mode to Sandbox mode, these translations will not be longer available.\n\nAre you sure to disconnect?', 'icom') : __('Are you sure to disconnect?', 'icom')) . '\');">' . __('Disconnect this client', 'icom') . '</a>';
      } else {
        echo '<p>' . __('If you want to change mode, you have to delete all not-ordered carts and/or wait until all translations are finished first.', 'icom') . '</p>';
      }

      $user = icom_api_get_user();
      echo '<h3>' . __('User info', 'icom') . '</h3>
            <table class="icom-table icom-settings">
              <tr>
                <th>' .  __('User', 'icom') . '</th>
                <td>' . $user->userName . '</td>
              </tr>
              <tr>
                <th>' . __('E-mail', 'icom') . '</th>
                <td>' . $user->email . '</td>
              </tr>
            </table>
            <br>
            <a href="https://tc6.idioma.com" class="button" onclick="window.open(this.href); return false;">' . __('Access idioma account', 'icom') . '</a>';

    } else if (!empty($sandbox)) {
      echo '<br><a href="' . $api['url']['base'] . $api['url']['grant'] . "?oauth_token=" . urlencode($_SESSION['request_token']) . '" class="button">' . __('Get authorize iCom plugin', 'icom') . '</a>';
    }

    echo '</div>';
  }
?>
