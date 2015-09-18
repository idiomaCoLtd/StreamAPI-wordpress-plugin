<?php
  /**
   * přilinkování knihovny stickyNotes pro účely proofreadingu
   * - přilinkuje JS knihovnu, která dovoluje označovat na stránce objekty a přidávat k nim poznámky
   */
  function icom_proofreading() {
    if (!isset($_GET['icom-previewHash']) || empty($_GET['icom-previewHash']) || !isset($_GET['icom-targetLang']) || empty($_GET['icom-targetLang'])) return;
?>
    <script>
      // automatické přihlášení
      jQuery(document).ready(function() {
        jQuery('.post-password-form input[type=\'password\']').val('<?= base64_decode($_GET['icom-previewHash']); ?>');
        jQuery('.post-password-form').submit();
      });
    </script>
<?php

    $item_db = icom_tc_get_item($_GET['icom-cartItem']);
    $item = icom_api_get_item($_GET['icom-cartItem']);
    if (!$item_db || $item_db->proofreading == 0) return;
    $cart_db = icom_tc_get_item_cart($_GET['icom-cartItem']);
    $cart = icom_api_get_cart($cart_db->uri);

    $edit = false;
    $readonly = false;
    $custom_data = false;
    foreach($item->targetContents as $val) {
      if ($val->targetLanguage != $_GET['icom-targetLang']) continue;

      if (in_array($val->targetLanguage, $cart->languagesWithStartedProofreading)) $edit = true;
      if (in_array($val->targetLanguage, $cart->languagesWithFinishedProofreading)) $readonly = true;

      $custom_data = (!empty($val->customData) ? explode("#", $val->customData) : "");
      break;
    }
    if ($customData === false) return;
    if ($edit == false && $readonly == false) return;

    // developer version
    //$edit = true; $readonly = false;

    $notes = array();
    if (!empty($custom_data)) {
      foreach($custom_data as $key => $val) {
        $note = explode(";", $val, 8);
        $notes[] = $key . ": {'id': " . $note[0] . ", 'element': " . $note[1] . ", 'date': '" . $note[2] . "', 'width': '" . $note[3] . "', 'height': '" . $note[4] . "', 'x': '" . $note[5] . "', 'y': '" . $note[6] . "', 'text': '" . str_replace("\n", "\\n", $note[7]) . "'}";
    } }
?>

    <script>
      jQuery(document).ready(function() {

        jQuery("div#comments").remove();
        jQuery("div#wpadminbar").remove();
        jQuery("html").css("cssText", "margin: 0 !important;");

        <?php if ($edit == true) { ?>
        function saveNotes(notes) {
          var sNotes = [];
          for(var note in notes) {
            sNotes.push(notes[note].id + ';' + notes[note].element + ';' + notes[note].date + ';' + notes[note].width + ';' + notes[note].height + ';' + notes[note].x + ';' + notes[note].y + ';' + notes[note].text);
          }
          jQuery.post('<?= ICOM_HOST; ?>wp-admin/admin.php?page=icom', { "icom-snotesData": sNotes.join("#"), "icom-cartItem": "<?= $_GET['icom-cartItem']; ?>", "icom-targetLang": "<?= $_GET['icom-targetLang']; ?>" } );
        }
        <?php } ?>

        var settings = {

        <?php if ($edit == true) { ?>
          callbacks: {
            created: (function(note) { saveNotes(sNotes.getAll()); }),
            moved: (function(note) { saveNotes(sNotes.getAll()); }),
            resized: (function(note) { saveNotes(sNotes.getAll()); }),
            edited: (function(note) { saveNotes(sNotes.getAll()); }),
            deleted: (function(note) { saveNotes(sNotes.getAll()); })
          },
          <?php } ?>

          options: {
            container: 'body'
            <?php if ($readonly == true) { ?>
            ,readonly: true
            ,instructions: false
            <?php } ?>
          },

          notes: {
            <?= implode(",", $notes); ?>
          }

        };
        sNotes.init(settings);
      });
    </script>

<?php
  }
?>
