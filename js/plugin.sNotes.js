
  var sNotes = {

    vars: {
      IDdata: "snotes",
      defNoteWidth: 200,
      defNoteHeight: 100,

      notes: 0,
      //adding: true,
      //moving: false,
      dragging: false,
      typing: false,

      zIndex: 0,

      pointerWidth: 28,
      pointerHeight: 25
    },

    borders: {
      main: "sNotes-borders",
      top: "sNotes-border-top",
      bottom: "sNotes-border-bottom",
      left: "sNotes-border-left",
      right: "sNotes-border-right"
    },

    texts: {
      placeholderTA: "Type note here",
      deleteConfirm: "Are you sure?"
    },

    callbacks: {
      created: false,
      moved: false,
      resized: false,
      edited: false,
      deleted: false
    },

    options: {
      instructions: true,
      readonly: false,
      container: "body"
    },

    notes: [],

    init: function(settings) {
      // nastavení
      if (settings) {
        if (settings.options !== undefined) {
          if (settings.options.instructions !== undefined) sNotes.options.instructions = settings.options.instructions;
          if (settings.options.readonly !== undefined) sNotes.options.readonly = settings.options.readonly;
          if (settings.options.container !== undefined) sNotes.options.container = settings.options.container;
        }

        if (settings.callbacks !== undefined) sNotes.callbacks = settings.callbacks;
      }

      // ocislovani prvku stranky
      sNotes.prepareNumbers();
      // vytvoreni dynamickych borderu pro zvyrazneni
      sNotes.prepareBorders();

      // zvyrazneni prvku pri pohybu mysi
      sNotes.mouseMove();
      // vytvoreni notesy pri kliknuti
      sNotes.mouseClick();

      // zmena pozice notesů pri resizingu okna
      sNotes.checkResizeWindow();

      // vygenerování již uložených poznámek
      if (settings) {
        if (settings.notes) sNotes.renderAll(settings.notes);
      }

      // instructions
      sNotes.renderInstructions();
    },

    prepareNumbers: function() {
      var i = 0;

      jQuery(sNotes.options.container + ' *').each(function() {
        if (jQuery(this).is('script') || jQuery(this).is('style') || jQuery(this).is('link')) return;

        jQuery(this).attr('data-' + sNotes.vars.IDdata, ++i).addClass('sNote-markable');
      });
    },

    prepareBorders: function() {
      if (sNotes.options.readonly == true) return;

      jQuery('body').append(
        '<div id="' + sNotes.borders.main + '">' +
          '<div id="' + sNotes.borders.top + '"></div>' +
          '<div id="' + sNotes.borders.bottom + '"></div>' +
          '<div id="' + sNotes.borders.left + '"></div>' +
          '<div id="' + sNotes.borders.right + '"></div>' +
        '</div>'
      );
    },

    mouseMove: function() {
      if (sNotes.options.readonly == true) return;

      jQuery(sNotes.options.container).mousemove(function(event) {
        //if (sNotes.vars.moving == true) return;
        if (sNotes.isMarkable(event.target) == false) return;

        if (event.target.id.indexOf('selector') !== -1 || event.target.tagName === 'BODY' || event.target.tagName === 'HTML') return;
        if (jQuery(event.target).hasClass('sNote')) return;

        var elements = {
          top: jQuery('#' + sNotes.borders.top),
          bottom: jQuery('#' + sNotes.borders.bottom),
          left: jQuery('#' + sNotes.borders.left),
          right: jQuery('#' + sNotes.borders.right)
        };

        var el = jQuery(event.target),
            targetOffset = el[0].getBoundingClientRect(),
            targetHeight = el.outerHeight(),
            targetWidth  = el.outerWidth();

        elements.top.css({
          left:  (targetOffset.left - 3),
          top:   (targetOffset.top - 3),
          width: (targetWidth + 6)
        });
        elements.bottom.css({
          top:   (targetOffset.top + targetHeight),
          left:  (targetOffset.left - 3),
          width: (targetWidth + 6)
        });
        elements.left.css({
          left:   (targetOffset.left - 3),
          top:    (targetOffset.top),
          height: (targetHeight)
        });
        elements.right.css({
          left:   (targetOffset.left + targetWidth),
          top:    (targetOffset.top),
          height: (targetHeight)
        });
      });
    },

    mouseClick: function() {
      if (sNotes.options.readonly == false) {

        jQuery(sNotes.options.container).dblclick(function(event) {
          if (sNotes.isMarkable(event.target) == false) return;
          //if (sNotes.vars.adding == false) return;

          if (event.which == 3) return;
          event.preventDefault();

          sNotes.addNote(event.target, event.pageX, event.pageY);
        });

      }

      jQuery(sNotes.options.container).click(function(event) {
        if (event.which == 3) return;
        event.preventDefault();
      });
    },

    checkResizeWindow: function() {
      /*

      var time;
      jQuery(window).resize(function() {
        clearTimeout(time);
        time = setTimeout(sNotes.resizeWindow, 500);
      });

      */

     setInterval(sNotes.resizeWindow, 1000);
    },

    resizeWindow: function() {
      for(var i in sNotes.notes) {

        var rect = sNotes.getNoteRect(sNotes.notes[i].element);
        if (rect === undefined) continue;

        jQuery("#sNote-" + sNotes.notes[i].id)
          .css("left", rect.x)
          .css("top", rect.y - sNotes.vars.pointerHeight)
          .css("width", 1 * rect.width + 1 * sNotes.vars.pointerWidth)
          .css("height", rect.height);
        jQuery("#sNote-" + sNotes.notes[i].id + " .sNote-rect")
          .css("width", rect.width)
          .css("height", rect.height);

        if (1 * rect.x + 1 * rect.width < 1 * rect.x + 1 * sNotes.notes[i].x || 1 * rect.y + 1 * rect.height < 1 * rect.y + 1 * sNotes.notes[i].y) {
          jQuery("#sNote-" + sNotes.notes[i].id + " .sNote-content")
            .css("left", 0)
            .css("top", 0);

          sNotes.notes[i].x = 0;
          sNotes.notes[i].y = 0;

          // callback
          if (sNotes.callbacks.moved != false) sNotes.callbacks.moved(sNotes.getNote(sNotes.notes[i].id));
        }


      }
    },

    renderAll: function(notes) {

      for (var i in notes) {
        if (notes[i] === null) continue;

        //sNotes.vars.notes++;
        if (sNotes.vars.notes < notes[i].id) sNotes.vars.notes = notes[i].id;

        sNotes.notes.push(notes[i]);
        sNotes.getIDdataElement(notes[i].element).addClass("sNote-busy");

        sNotes.renderNote(notes[i]);
      }
    },

    renderNote: function(note) {
      var rect = sNotes.getNoteRect(note.element);
      if (rect === undefined) return;

      sNotes.addNoteHTML(note.date, note.x, note.y, note.width, note.height, rect.x, rect.y, rect.width, rect.height, note.text);
    },

    addNote: function(el, mouseX, mouseY) {
      var now = new Date();
      var date = now.getDate() + "." + (now.getMonth() + 1) + "." + now.getFullYear() + " " + (now.getHours() < 10 ? "0" + now.getHours() : now.getHours()) + ":" + (now.getMinutes() < 10 ? "0" + now.getMinutes() : now.getMinutes());
      var no = sNotes.getIDdataElementNo(el);

      var rect = sNotes.getNoteRect(no);
      var note = {
        id: ++sNotes.vars.notes,
        date: date,
        element: no,
        x: (mouseX - rect.x),
        y: (mouseY - rect.y),
        width: sNotes.vars.defNoteWidth,
        height: sNotes.vars.defNoteHeight,
        text: ''
      };

      sNotes.notes.push(note);
      jQuery(el).addClass("sNote-busy");

      sNotes.addNoteHTML(note.date, note.x, note.y, note.width, note.height, rect.x, rect.y, rect.width, rect.height, '');

      // callback
      if (sNotes.callbacks.created != false) sNotes.callbacks.created(note);
    },

    addNoteHTML: function(date, x, y, width, height, rect_x, rect_y, rect_width, rect_height, text) {

      jQuery('body').append(
        '<div id="sNote-' + sNotes.vars.notes + '" class="sNote sNote-onlyPoint sNote-all">' +
          '<div class="sNote sNote-rect sNote-onlyPoint">' +
            '<div class="sNote sNote-content">' +
              '<div class="sNote sNote-point">' + sNotes.vars.notes + '</div>' +
              '<div class="sNote sNote-note">' +
                (sNotes.options.readonly == false ? '<i class="sNote sNote-delete"></i>' : '') +
                '<u class="sNote sNote-date">#' + sNotes.vars.notes + ' - ' + date + '</u>' +
                '<textarea class="sNote sNote-textarea" readonly="readonly" placeholder="' + sNotes.texts.placeholderTA + '">' + text + '</textarea>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>')

      // note
      jQuery("#sNote-" + sNotes.vars.notes)
        .css("left", rect_x + "px")
        .css("top", (rect_y - sNotes.vars.pointerHeight) + "px")
        .css("width", (1 * rect_width + 1 * sNotes.vars.pointerWidth) + "px")
        .css("height", (1 * rect_height + 1 * sNotes.vars.pointerHeight) + "px");
      // rect
      jQuery("#sNote-" + sNotes.vars.notes + " .sNote-rect")
        .css("left", "0px")
        .css("top", sNotes.vars.pointerHeight + "px")
        .css("width", rect_width + "px")
        .css("height", rect_height + "px");
      // content
      jQuery("#sNote-" + sNotes.vars.notes + " .sNote-rect .sNote-content")
        .css("left", x + "px")
        .css("top", y + "px");
      // note
      jQuery("#sNote-" + sNotes.vars.notes + " .sNote-rect .sNote-content .sNote-note")
        .css("width", width + "px")
        .css("height", height + "px");

      var note = "#sNote-" + sNotes.vars.notes;

      jQuery(note).mouseenter(function() {
        if (sNotes.vars.dragging == true) return;
        //sNotes.setAdding(false);

        // show rectangle
        jQuery(this).removeClass("sNote-onlyPoint");
        jQuery(this).find(".sNote-rect").removeClass("sNote-onlyPoint");
        // handle z-index
        sNotes.vars.zIndex = jQuery(this).css("z-index");
        jQuery(this).css("z-index", "99999");

      }).mouseleave(function() {
        //sNotes.setAdding(true);

        if (jQuery(this).find(".sNote-note").hasClass("sNote-active") == false && jQuery(this).find(".sNote-note").hasClass("sNote-hover") == false && sNotes.vars.dragging == false) {
          // hide rectangle
          jQuery(this).addClass("sNote-onlyPoint");
          jQuery(this).find(".sNote-rect").addClass("sNote-onlyPoint");
          // handle z-index
          jQuery(this).css("z-index", sNotes.vars.zIndex);
          sNotes.vars.zIndex = 0;
        }

      }).click(function() {
        if (jQuery(this).find(".sNote-note").hasClass("sNote-active") == true && jQuery(this).find(".sNote-note").hasClass("sNote-hover") == false) {// && sNotes.vars.typing == false) {
          jQuery(this).find(".sNote-note").removeClass("sNote-active").delay(100).hide(150);
        }
      });

      // posouvat může pouze proofreader
      if (sNotes.options.readonly == false) {

        // draggable
        jQuery(note).find(".sNote-content").on("mousedown", function() {
          // beforeStart on draggable
          jQuery(this).one("mouseup", function() { jQuery(this).off("mousemove"); });
          jQuery(this).one("mousemove", function() { jQuery(this).find(".sNote-note").hide(); });
        }).draggable({
          containment: note,
          cursorAt: { top: 15, left: 15 },
          cancel: "#sNote-" + sNotes.vars.notes + " .sNote-note .sNote",
          scroll: false,
          start: function(event, ui) {
            //sNotes.setMoving(true);
            sNotes.vars.dragging = true;
          },
          drag: function(event, ui) {
            jQuery(this).find(".sNote-note").hide();
          },
          stop: function(event, ui) {
            sNotes.vars.dragging = false;

            jQuery(this).find(".sNote-note").show();
            sNotes.moveNote(jQuery(note).attr("id").split("-")[1], ui.position.left, ui.position.top);
          }
        })

      }

      jQuery(note).find(".sNote-content").hover(function() {
        if (sNotes.vars.dragging == true) return;

		    jQuery(note).find(".sNote-note").addClass("sNote-hover").stop(true, true).show(150);
		  }, function() {
		    jQuery(note).find(".sNote-note").removeClass("sNote-hover");
		    if (jQuery(note).find(".sNote-note").hasClass("sNote-active") == false) {
          jQuery(note).find(".sNote-note").delay(100).hide(150);
        }
      });

      // resizable
      jQuery(note).find(".sNote-note").resizable({
        start: function(event, ui) {
          //sNotes.setMoving(true);
        },
        stop: function(event, ui) {
          sNotes.resizeNote(jQuery(note).attr("id").split("-")[1], ui.size.width, ui.size.height);
        }
      })
      .click(function() {
        if (sNotes.vars.dragging == true) {
          sNotes.vars.dragging = false;
          return;
        }

        if (sNotes.vars.typing == false) {
          jQuery(this).toggleClass("sNote-active");
        }
      });

      // delete
      jQuery(note).find(".sNote-delete").click(function() {
        if (confirm(sNotes.texts.deleteConfirm)) sNotes.deleteNote(jQuery(note).attr("id").split("-")[1]);
      });

      // textarea
      jQuery(note).find(".sNote-textarea").mouseenter(function() {
        sNotes.vars.typing = true;
        if (sNotes.options.readonly == false) jQuery(this).prop("readonly", false);
      }).mouseleave(function() {
        sNotes.vars.typing = false;
        jQuery(this).attr("readonly", "readonly");
      }).blur(function() {
        sNotes.editNote(jQuery(note).attr("id").split("-")[1], jQuery(this).val());
      }).click(function() {
        jQuery(note).find(".sNote-note").addClass("sNote-active");
      });

    },

    getIDdataElementNo: function(el) {
      var no = jQuery(el).data(sNotes.vars.IDdata);
      if (no === undefined || no == '')
        return sNotes.getIDdataElement(jQuery(el).parent());
      else
        return no;
    },

    getIDdataElement: function(no) {
      return jQuery(sNotes.options.container).find("[data-" + sNotes.vars.IDdata + "='" + no + "']");
    },

    getNote: function(id) {
      var note = 0;
      for (var n in sNotes.notes) {
        if (sNotes.notes[n].id == id) {
          note = n; break;
      } }

      return sNotes.notes[note];
    },

    getAll: function() {
      return sNotes.notes;
    },

    getNoteRect: function(no) {
      if (no === undefined) return;

      var rect = {},
          element = sNotes.getIDdataElement(no);

      rect.x = parseInt(jQuery(element).offset().left);
      rect.y = parseInt(jQuery(element).offset().top);
      rect.width = parseInt(jQuery(element).outerWidth());
      rect.height = parseInt(jQuery(element).outerHeight());

      return rect;
    },

    moveNote: function(id, x, y) {
      if (sNotes.options.readonly == true) return;
      //sNotes.setMoving(false);

      var note = sNotes.getNote(id);
      note.x = parseInt(x);
      note.y = parseInt(y);

      // callback
      if (sNotes.callbacks.moved != false) sNotes.callbacks.moved(note);
    },

    resizeNote: function(id, width, height) {
      if (sNotes.options.readonly == true) return;
      //sNotes.setMoving(false);

      var note = sNotes.getNote(id);
      note.width = width;
      note.height = height;

      // callback
      if (sNotes.callbacks.resized != false) sNotes.callbacks.resized(note);
    },

    editNote: function(id, text) {
      if (sNotes.options.readonly == true) return;

      var note = sNotes.getNote(id);
      note.text = text;

      // callback
      if (sNotes.callbacks.edited != false) sNotes.callbacks.edited(note);
    },

    deleteNote: function(id) {
      if (sNotes.options.readonly == true) return;

      var note = sNotes.getNote(id);
      for (var i = sNotes.notes.length; i--; ) {
        if (sNotes.notes[i].id == id) {
          sNotes.notes.splice(i, 1);
          sNotes.getIDdataElement(note.element).removeClass("sNote-busy");
          break;
      } }

      jQuery("#sNote-" + id).remove();
      //sNotes.setAdding(true);

      // callback
      if (sNotes.callbacks.deleted != false) sNotes.callbacks.deleted(note);
    },

    /*
    setAdding: function(value) {
      sNotes.vars.adding = value;
    },

    setMoving: function(value) {
      sNotes.vars.moving = value;
    },
    */

    isMarkable: function(el) {
      if (jQuery(el).hasClass("sNote-busy") || !jQuery(el).hasClass("sNote-markable") || sNotes.vars.dragging == true) {

        jQuery("#" + sNotes.borders.main).fadeOut(250);
        return false;

      } else {

        jQuery("#" + sNotes.borders.main).fadeIn(250);
        return true;

      }
    },

    renderInstructions: function() {
      if (sNotes.options.instructions == false || sNotes.options.readonly == true) return;

      var html = "<div id='sNote-instructions' class='sNote-instructions-small'>" +
                    "<div id='sNote-instructions-small'></div>" +
                    "<div id='sNote-instructions-large'>" +
  		                "<h1>sNotes instructions</h1>" +
  		                "<h2>Use these sNotes for commenting on non-text related issues (page layout, formatting, etc.) or issues with text, which idioma has not produced.</h2>" +
                      "<ol>" +
                        "<li>Move mouse over the page. Red rectangles will appear to show all individual page elements and frames. Select the smallest one that contains the issue you want to comment.</li>" +
                        "<li>Inside the frame, double-click on the position where the comment should apply. The frame turns  yellow and a text area appears.</li>" +
                        "<li>Click inside and type your comment. Then click  the comment title to collapse the note back into the pointer.</li>" +
                        "<li>The position of the pointer is locked to stay inside the frame only.</li>" +
                      "</ol>" +
  		              "</div>" +
                  "</div>";
      jQuery('body').append(html);

      jQuery('#sNote-instructions').click(function() {
        jQuery(this).toggleClass('sNote-instructions-small');
      });

    },

    disable: function() {
    	sNotes.options.readonly = true;

      jQuery('#sNotes-borders').hide();
    	jQuery('.sNote').hide();
    	jQuery('#sNote-instructions').hide();
    },

    enable: function() {
    	sNotes.options.readonly = false;

      jQuery('#sNotes-borders').show();
    	jQuery('.sNote').show();
    	jQuery('#sNote-instructions').show();
    }

  };
