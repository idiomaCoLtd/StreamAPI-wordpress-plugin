<?php
  /**
   * zabezpečení databáze
   * @param $string
   */
  function icom_save_db($string) {
    global $wpdb;

    $string = (get_magic_quotes_gpc() == 1 ? stripslashes($string) : $string);

    if ($wpdb->use_mysqli == false)
      return mysql_real_escape_string($string);
    else
      return $wpdb->dbh->real_escape_string($string);
  }

  /**
   * funkce, která zkracuje texty v košíku, příp. přidá tři tečky a odkaz na zobrazení celého textu
   * @param text
   * @param pocet znaku
   * @param odkaz na modal dialog
   * @param text klikacího odkazu na konci
   */
  function icom_substr($string1, $pocet, $modal = false, $label = "show") {
    $string = strip_tags(html_entity_decode($string1), "<br>");

    $lenght = mb_strlen($string, "utf-8");
    if ($lenght > $pocet) {
      $lenght = strrchr(mb_substr($string, 0, $pocet, "utf-8"), " ");
      $string = mb_substr($string, 0, $pocet - mb_strlen($lenght, "utf-8"), "utf-8");
      if ($pocet > 0) $string .= " ...";

      if ($modal == true) {
        if ($pocet > 0) $string .= "<br>";

        $hash = rand(1000, 9999);
        //$string1 = htmlspecialchars(icom_sep($string1, ICOM_SEP_SEG), ENT_QUOTES);
        $string .= "<a href='javascript:void(0);' onclick='showDialog(\"icom-dialog-substr" . $hash . "\");'>" . $label . "</a>
                    <div id='icom-dialog-substr" . $hash . "' class='icom-dialog'>
                      <div class='icom-dialog-in' style='width: 90%;'>
                        <button class='button close' onclick='return showDialog(\"icom-dialog-substr" . $hash . "\", true);'>X</button>
                        <p>" . str_replace(array(ICOM_SEP_SEG, str_replace("{TYPE}", "bbCodeAttr", ICOM_SEP_SEG)), array("<br><br>", "<br>"), $string1) . "</p>
                      </div>
                    </div>";
    } }

    return $string;
  }

  /**
   * segmentace stringu dle vybraných tagů
   * @param $html
   * @param $tagy
   */
  function icom_explode_html($tags, $html) {
    $output = $match = array();
    $regex = "/<(" . implode("|", $tags) . ")/";

    while(preg_match($regex, $html, $match, PREG_OFFSET_CAPTURE)) {
      $endTag = "</" . $match[1][0] . ">";
      $endPosition = strpos($html, $endTag);
      $output[] = substr($html, 0, $endPosition + strlen($endTag));
      $html = substr($html, $endPosition + strlen($endTag));
    }

    $output[] = substr($html, 0, strlen($html));
    return $output;
  }

  /**
   * nahrazení
   * @param text
   * @param separator
   */
  /*
  unction icom_sep($string, $sep) {
    return preg_replace("~" . str_replace("{ORDER}", "[0-9]+", ICOM_SEP_SEG) . "~", $sep, $string);
  }
  */

  /**
   * nastavení chybové hlášky
   * @param typ - 'info', 'error'
   * @param text
   */
  function set_msg($typ, $text) {
    $msg = get_option('icom_toolbox_msg');
    if (!empty($msg[1])) $text = $msg[1] . "<br>" . $text;

    update_option('icom_toolbox_msg', array($typ, $text));
  }

  /**
   * vypsání chybové hlášky
   * @param naformátovat hlášku
   */
  function get_msg($format = true) {
    $msg = get_option('icom_toolbox_msg');
    update_option('icom_toolbox_msg', '');

    $token = get_option('icom_token');
    if (empty($token)) return;

    if (!empty($msg)) {
      if ($format == true)
        return "<div class='" . ($msg[0] == "error" ? "error" : "updated") . " icom-notice-" . $msg[0] . "'><p>" . $msg[1] . "</p></div>";
      else
        return $msg;
    }

    return '';
  }

  /**
   * zjištění, zda-li nějaká chybová hláška existuje
   */
  function is_msg() {
    $msg = get_option('icom_toolbox_msg');
    return (empty($msg) ? false : true);
  }

  /**
   * odstraní diakritiku, mezery změní na -, všechna písmena malá
   * @param string
  */
  function icom_links($link) {
    $link = strtolower($link);
    $link = str_replace(array('&lt;', '&gt;'), "-", $link);
    $link = preg_replace("/[^[:alpha:][:digit:]_]/", "-", $link);
    $link = preg_replace("/[-]+/", "-", $link);
    $link = trim($link, "-");
    return $link;
  }

  /**
   * rozdělí string na jazyky, přidá k nim nový, seřadí je a vrátí zpět
   * @param $langs
   * @param $new
   */
  function icom_handle_langs($langs, $new) {
    $array = array();
    if (!empty($langs)) $array = explode(";", $langs);

    if (!in_array($new, $array)) $array[] = $new;
    sort($array);

    return implode(";", $array);
  }

?>
