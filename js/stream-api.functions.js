
  function tabs(id) {
    jQuery(".icom-tabs").removeClass("icom-active");

    jQuery("#" + id + "_a").addClass("icom-active");
    jQuery("#" + id + "_div").addClass("icom-active");
  }

  function toggleToolboxCheckboxes() {
    var is = jQuery("#icom-toolbox-checkAll").is(":checked");

    jQuery(".icom-toolbox-langs td input").each(function() {
      if (jQuery(this).prop("disabled") == true) return true;

      jQuery(this).prop("checked", is);
    });
  }

  function showContextMenu(el) {
    jQuery(el).find('.icom-table-menu').show(100).addClass("active");
    jQuery(el).find('.icom-table-menu').mouseleave(function() {
      //jQuery(this).hide(100);
      jQuery('.icom-table-menu.active').hide(100).removeClass("active");
    });
  }

  function showDialog(id, close) {
    if (close === undefined) close = false;

    if (close == false)
      jQuery("#" + id).fadeIn(250);
    else
      jQuery("#" + id).fadeOut(250);

    return false;
  }

  function addPageByTranslation(post, target) {
    jQuery("#icom-create-target-by").val(post);
    jQuery("#icom-target").val(target);

    showDialog("icom-dialog-carts");
  }

  function addPagesByTranslation(host) {
    var icom_item = "",
        icom_target = "",
        icom_source = jQuery("#icom-bulk-source").val(),
        icom_cart = jQuery("#icom-bulk-cart").val(),
        icom_pr = jQuery("#icom-bulk-proofreading").is(":checked"),
        error = "";

    jQuery("table.widefat th input[name='post[]']").each(function() {
      if (jQuery(this).is(":checked") == true) icom_item += "&icom-targetCreate-by[]=" + jQuery(this).val();
    });

    jQuery("#icom-bulk-target span.icom-target input").each(function() {
      if (jQuery(this).is(":checked") == true) icom_target += "&icom-targetLangs[]=" + jQuery(this).val();
    });

    if (icom_cart == '') error += "You have to select one of your carts" + "\n";
    if (icom_target == '') error += "You have to select at least one target language" + "\n";
    if (icom_item == '') error += "You have to select at least one item to translate" + "\n";

    if (error != '')
      alert(error);
    else
      window.location.href = host + icom_item + icom_target + "&icom-sourceLang=" + icom_source + "&icom-cart=" + icom_cart + "&icom-proofreading=" + icom_pr;

    return false;
  }

  function changeNewCartSource() {
    jQuery("input.icom-addCart-source").hide(0);
    jQuery("select.icom-addCart-source").show(0);

    return showDialog("icom-dialog-addCart-changeSource", true);
  }

  function validNewCart() {
    var error = '';

    if (jQuery("#icom-addCart-name").val() == '') error += 'You have to fill cart name' + '\n';

    if (error != '') {
      alert(error);
      return false;
    }
  }

  function showCartBulkAction(action, url) {
    jQuery(".icom-cart-bulk-action").hide(0);
    jQuery("#icom-cart-bulk-data-url").val('');
    jQuery("#icom-cart-bulk-data-action").val('');
    if (action == '') return;

    var actions = action.split(";");
    jQuery("#icom-cart-bulk-" + actions[0]).fadeIn(250);
    jQuery("#icom-cart-bulk-data-url").val(url + '&icom-cart' + actions[0] + '=' + actions[1]);
    jQuery("#icom-cart-bulk-data-action").val(actions[0]);
  }

  function urlCartBulkAction() {
    var url = "";
    var action = jQuery("#icom-cart-bulk-data-action").val();

    jQuery("#icom-cart-bulk-" + action + " span.icom-checks input").each(function() {
      if (jQuery(this).is(":checked") == true) url += "&icom-language" + action + "[]=" + jQuery(this).val();
    });

    if (action == "" || url == "") return false;
    window.location.href = jQuery("#icom-cart-bulk-data-url").val() + url;
  }

  function unCheckAll(el) {
    jQuery("td.item-check input").attr("checked", jQuery(el).is(":checked"));
  }

  function urlItemsBulkAction(url) {
    var action = jQuery("#select-cart-bulk-action-item").val();
    var items = "";

    jQuery("td.item-check input").each(function() {
      if (jQuery(this).is(":checked") == true) items += "&icom-bulkAction-items[]=" + jQuery(this).val();
    });

    if (items == "" || action == "") return;
    window.location.href = url + "&icom-bulkAction-action=" + action + items;
  }

  function changeItemProofreading(el, url, uri) {
    var checked = (jQuery(el).is(":checked") ? true : false);

    jQuery(el).attr("checked", (checked == false ? true : false)).attr("disabled", true);
    jQuery.get(url + "&icom-setItemProofreading=" + uri + "&icom-setItemProofreading-state=" + checked, function(data) {
      jQuery(el).attr("checked", checked).attr("disabled", false);
    });
  }

  function saveCheckbox(el, url, get) {
    var checked = (jQuery(el).is(":checked") ? true : false);

    jQuery(el).attr("checked", (checked == false ? true : false)).attr("disabled", true);
    jQuery.get(url + "&" + get + "=" + checked, function(data) {
      jQuery(el).attr("checked", checked).attr("disabled", false);
    });
  }

  function validateSaveBBcodeForm() {
    var error = '';
    if (jQuery("#bbcode-name").val() == '') error += 'Fill BB tag name' + '\n';

    if (error != '') {
      alert(error);
      return false;
    }

    return true;
  }

  // === POČÍTÁNÍ WC A PRICE V TOOLBOXU ===
  function toolbox_initEditors() {
    tinymce.editors[0].onKeyUp.add(function (ed, e) {
      if (e.keyCode == 32 || e.keyCode == 8) toolbox_resetWaP();
    });

    jQuery("#content").keyup(function(e) {
      if (e.keyCode == 32 || e.keyCode == 8) toolbox_resetWaP();
    });
  }

  /*
  function getEditorWc() {
    var wc = 0;

    if (jQuery("#content").css("display") == "none") {
      wc = tinymce.editors[0].getContent();
    } else {
      wc = jQuery("#content").val();
    }

    return wc.replace(/(<([^>]+)>)/gi, "").replace(/ +/gi, " ").replace(/ $/gi, "").split(" ").length;
  }
  */

  function toolbox_resetWaP() {
    jQuery("#icom-toolbox-totalWc i").text("- - -");
    jQuery("#icom-toolbox-totalPrice i").text("- - -");

    jQuery("input.icom-toolbox-refresh").show();
  }

  function toolbox_calcWaP(wc) {
    if (jQuery("#icom-toolbox-totalWc i").text() == "- - -") return;

    var checks = 0,
        price = 0;
    //var wc = getEditorWc();

    jQuery(".icom-toolbox-check").each(function() {
      if (jQuery(this).find("input").is(":checked") == true) {
        var lPrice = jQuery(this).find("input").data("icom-price");

        checks += 1;
        price += (wc * 1) * (lPrice * 1);
      }
    });
    price = Math.round(price * 100) / 100;

    var res1 = wc * checks;
    var res2 = price;
    if (wc == 0)
      res1 = res2 = "- - -";
    else
      jQuery("input.icom-toolbox-refresh").hide();

    jQuery("#icom-toolbox-totalWc i").text(res1);
    jQuery("#icom-toolbox-totalPrice i").text(res2);
  }
  // /=== POČÍTÁNÍ WC A PRICE V TOOLBOXU ===
