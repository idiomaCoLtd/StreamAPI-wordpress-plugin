<?php
/*
  Plugin Name: iCom Lang Switcher Widget
  Plugin URI: http://icom.idioma.com/icom-lang-switcher
  Description: Widget for switch languages collaborate with iCom plugin.
  Version: 0.1
  Author: Antonín Neumann, BeCorp.cz
  Author URI: http://www.becorp.cz
  License: GNU GPLv3
*/

class iComLangSwitcher extends WP_Widget {
    /**
	 * Nastavení widgetu
     *  - jméno, popisek, atd.
	 */
	function iComLangSwitcher() {
        //description - popisek v administraci
        $widget_ops = array('classname' => 'iComLangSwitcher', 'description' => 'Widget for switch languages.' );
        //2 parametr je nadpis widgetu v administraci
        $this->WP_Widget('iComLangSwitcher', 'Languages switcher', $widget_ops);
  }


  /**
	 * Zobrazení nastavení widgetu v administraci
	 *
	 * @param array $instance The widget options
	 */
  function form($instance)
  {
    $instance = wp_parse_args( (array) $instance, array( 'title' => '' , 'button' => 'Change Lang') );
    $title = $instance['title'];
    $button = $instance['button'];
    $inline = $instance['inline'];

    echo('<p>
		<label for="'.$this->get_field_id('title').'">Title:</label>
		<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text"
					 value="'.attribute_escape($title).'" >
		</p>');
    
    echo('<p>
		<label for="'.$this->get_field_id('button').'">Button text:</label>
		<input class="widefat" id="'.$this->get_field_id('button').'" name="'.$this->get_field_name('button').'" type="text"
					 value="'.attribute_escape($button).'" >
		</p>');
    ?>
    <p>
        <input class="checkbox" type="checkbox" <?php checked($instance['inline'], 'on'); ?> id="<?php echo $this->get_field_id('inline'); ?>" name="<?php echo $this->get_field_name('inline'); ?>" /> 
        <label for="<?php echo $this->get_field_id('inline'); ?>">Show select box and button on the same row</label>
    </p>
    <?php
  }

	/**
	 * Zpracování změn po uložení (v administraci)
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
  function update($new_instance, $old_instance)
  {
    $instance = $old_instance;
    $instance['title'] = $new_instance['title'];
    $instance['button'] = $new_instance['button'];
    $instance['inline'] = $new_instance['inline'];
    return $instance;
  }

  /**
	 * Výstup widgetu na stránce
	 *
	 * @param array $args
	 * @param array $instance
	 */
  function widget($args, $instance)
  {
    extract($args, EXTR_SKIP);
    
    echo $before_widget;
    $title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
    $button = empty($instance['button']) ? ' ' : apply_filters('widget_button', $instance['button']);
    $inline = $instance['inline'] ? true : false;

    if(!empty($title))
        echo $before_title . $title . $after_title;
        
    $current_lang = icom_current_lang(); //aktuálně zvolený pro zobrazení stránek
    $langs = get_option('icom_language_selected_front'); //všechny dostupné jazyky
    
   
    
    //iComLangSwitcher formulář
    echo('<form style="text-align: center;" method="GET">');
    
    //zachování původních $_GET hodnot
    foreach ($_GET as $key => $param) {
        if($key == "lang")
            continue;
        echo('<input type="hidden" name="'.$key.'" value="'.$param.'">');
    }
    
    $icom_all_lang = icom_api_get_languages();
    //výpis select boxu s dostupnými jazyky
    $inline ? $selectbox_width = "width: 50%; margin-top: 11px;" : $selectbox_width = "width: 100%;";
    $inline ? $button_width = "width: 48%; margin-left: 2%;" : $button_width = "margin-top: 5px;";
    
  	echo('<select size="1" style="position: relative; z-index: 999999999; '.$selectbox_width.'" name="lang">');
    foreach($langs as $lang){
        echo('<option value="'.$lang.'"');
            if($current_lang == $lang) echo(' selected ');
        echo('>'.$icom_all_lang[$lang]->name.'</option>');
    }
    echo('</select>');
    echo('<button type="submit" style="'.$button_width.'">'.$button.'</button>');
    echo('</form>');
    
//    replace_menu_by_lang();
    
    echo $after_widget;
  }
}

// zavislost na iCom pluginu - pokud neni naistalovany vypise se o tom hlaska
if(strcasecmp(get_option('icom_installed'), 'FALSE') == 0){
    add_action( 'admin_notices', 'icom_plugin_not_installed' );
}else {
    //aktivace widgetu
    add_action( 'widgets_init', create_function('', 'return register_widget("iComLangSwitcher");') );
}

function icom_plugin_not_installed() {
    echo('<div class="error"><p>iCom plugin is not instaled!</p> </div>');
}
?>