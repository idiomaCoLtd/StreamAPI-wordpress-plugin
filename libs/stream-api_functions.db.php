<?php

	/**
	 *  zjistí zda-li tato stránka existuje v DB
	 *  @param $parent_page
	 *  @param $lang
	 */
	function icom_db_get_page_by_parent($id_post_parent, $lang) {
		global $wpdb;

		return $wpdb->get_row("SELECT id_post, lang, preview FROM " . $wpdb->prefix . "icom_translations WHERE id_post_parent = '" . $id_post_parent . "' AND lang = '" . icom_save_db($lang) . "' ORDER BY id DESC LIMIT 1");
	}

	/**
	 *  vytáhne status zadané stránky
	 *  @param $post_id
	 */
	function icom_db_get_post_status($post_id) {
		global $wpdb;

		return $wpdb->get_var("SELECT post_status FROM " . $wpdb->prefix . "posts WHERE ID = '" . icom_save_db($post_id) . "'");
	}

	/**
	 *  vytáhne typ zadané stránky
	 *  @param $post_id
	 */
	function icom_db_get_post_type($post_id) {
		global $wpdb;

		return $wpdb->get_var("SELECT post_type FROM " . $wpdb->prefix . "posts WHERE ID = '" . icom_save_db($post_id) . "'");
	}

	/**
	 * je tato stránka sourcová? pokud ano, vrátí své ID, pokud ne, vrátí ID sourcu
	 * @param $id stránky
	 */
	function icom_get_source_id($id) {
		global $wpdb;

		return $wpdb->get_var("SELECT IF(id_post_parent = 0, id_post, id_post_parent) FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . icom_save_db($id) . "'");
	}

	/**
	 * změní post_status na náš vlastní kvůli zobrazení stránky v iQube (kvůli zaheslovaným, draft stránkám apod.)
	 * @param $post_id
	 */
	function icom_db_set_icom_post_status($post_id) {
		global $wpdb;

		$post_id = icom_save_db($post_id);

		$post_status = $wpdb->get_var("SELECT CONCAT(post_status, '\n', post_password) FROM " . $wpdb->prefix . "posts WHERE ID = '" . $post_id . "'");
    $wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations SET post_status = '" . $post_status . "' WHERE id_post = '" . $post_id . "'");

    $wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_status = '" . ICOM_POST_STATUS . "', post_password = '' WHERE ID = '" . $post_id . "'");
	}

	/**
	 *  změní post_status po překladu (nebo smazání stránky z košíku) zpět na originální (vč. hesla)
	 *  @param $post_id
	 */
	function icom_db_set_orig_post_status($post_id) {
		global $wpdb;

		$post_id = icom_save_db($post_id);

		$post_status = $wpdb->get_var("SELECT post_status FROM " . $wpdb->prefix . "icom_translations WHERE id_post = '" . $post_id . "'");
		$post_status = explode("\n", $post_status);
		$wpdb->query("UPDATE " . $wpdb->prefix . "posts SET post_status = '" . $post_status[0] . "', post_password = '" . $post_status[1] . "' WHERE ID = '" . $post_id . "'");

		$wpdb->query("UPDATE " . $wpdb->prefix . "icom_translations SET post_status = '' WHERE id_post = '" . $post_id . "'");
	}
?>
