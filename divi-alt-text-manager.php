<?php
/**
 * Plugin Name: Divi ALT-Text Manager
 * Description: Listet alle Bilder aus Divi's et_pb_image-Modulen und erlaubt das Bearbeiten der ALT-Texte.
 * Version: 1.0
 * Author: Dein Name
 */

if (!defined('ABSPATH')) {
	exit;
}

// Admin-Menü-Eintrag hinzufügen
function datm_add_menu_page() {
	add_menu_page('Divi ALT-Text Manager', 'Divi ALT-Manager', 'manage_options', 'divi-alt-text-manager', 'datm_admin_page');
}
add_action('admin_menu', 'datm_add_menu_page');

// Funktion für die Admin-Seite
function datm_admin_page() {
	global $wpdb;

	// Alle Divi-Layouts durchsuchen
	$posts = $wpdb->get_results("
        SELECT ID, post_content 
        FROM {$wpdb->posts} 
        WHERE post_type IN ('page', 'post', 'et_pb_layout') 
        AND post_content LIKE '%et_pb_image%'
        AND post_status = 'publish'
        ORDER BY ID ASC
        LIMIT 0, 100000
    ");

	echo '<div class="wrap">';
	echo '<h1>Divi ALT-Text Manager</h1>';
	echo '<form method="post" action="">';
	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<thead><tr><th>Bild</th><th>ALT-Text</th></tr></thead><tbody>';

	wp_nonce_field('save_alt_texts', 'datm_nonce');

	$count = 0;
	foreach ($posts as $post) {
		$content = maybe_unserialize($post->post_content);

		echo '<tr><td colspan="4">Prüfe ID '.$post->ID.'</td></tr>';

		if ($content) {
			$images = datm_extract_images_without_alt($content);
			foreach ($images as $image) {
				echo '<tr>';
				echo '<td><img src="' . esc_url($image) . '" width="150"></td>';
				echo '<td><a href="'.get_permalink($post->ID).'">Seite (ID '.$post->ID.')</a></td>';
				echo '<td>' . esc_url($image) . '</td>';
				echo '<td><input type="text" name="alt_texts[' . $count . ']" value="">';
				echo '<input type="hidden" name="image_urls[' . $count . ']" value="' . esc_url($image) . '">';
				echo '<input type="hidden" name="post_ids[' . $count . ']" value="' . $post->ID . '"></td>';
				echo '</tr>';
				$count++;
			}
			if (!$images) {
				echo '<tr><td colspan="4">Keine Bilder gefunden</td></tr>';
			}
		}
	}

	echo '</tbody></table>';
	echo '<br><input type="submit" name="save_alt_texts" class="button-primary" value="Speichern">';
	echo '</form>';
	echo '</div>';
}

// 🔎 **Bilder OHNE ALT-Text finden**
function datm_extract_images_without_alt($content) {
	$images = [];

	// Regex für et_pb_image-Module mit src und optionalem alt-Attribut
	preg_match_all('/\[et_pb_image[^]]*?src="([^"]+)"[^]]*?\]/s', $content, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		$src = $match[1];

		// Überprüfen, ob das ALT-Attribut fehlt oder leer ist
		if (!preg_match('/alt="([^"]*)"/', $match[0]) || preg_match('/alt="\s*"/', $match[0])) {
			$images[] = $src;
		}
	}

	return $images;
}


// 📝 **Speichern der neuen ALT-Texte**
function datm_save_alt_texts() {
	if (isset($_POST['save_alt_texts']) && check_admin_referer('save_alt_texts', 'datm_nonce')) {
		global $wpdb;

		$alt_texts = $_POST['alt_texts'] ?? [];
		$image_urls = $_POST['image_urls'] ?? [];
		$post_ids = $_POST['post_ids'] ?? [];
		$t = "";

		foreach ($alt_texts as $index => $new_alt_text) {
			if (!empty($new_alt_text)) {

				$t .= "<br>\nErsetze ALT-Text von Bild $image_urls[$index] durch <b>$new_alt_text</b><br>\n";
				$post_id = intval($post_ids[$index]);
				$image_url = esc_url($image_urls[$index]);
				$new_alt_text = sanitize_text_field($new_alt_text);

				// Lade aktuellen Inhalt
				$post = $wpdb->get_row("SELECT post_content FROM {$wpdb->posts} WHERE ID = $post_id", ARRAY_A);
				if ($post) {
					$content = $post['post_content'];

					// ALT-Text hinzufügen (ersetzen oder neu setzen)
					$updated_content = preg_replace(
						'/(\[et_pb_image.*?src="' . preg_quote($image_url, '/') . '".*?)(alt="[^"]*")?/',
						'$1 alt="' . esc_attr($new_alt_text) . '"',
						$content
					);

					// Falls kein ALT-Attribut existierte, wird es eingefügt
					if ($updated_content === $content) {
						$updated_content = preg_replace(
							'/(\[et_pb_image.*?src="' . preg_quote($image_url, '/') . '".*?)\]/',
							'$1 alt="' . esc_attr($new_alt_text) . '"]',
							$content
						);
					}

					// Speichere den neuen Inhalt
					$wpdb->update(
						$wpdb->posts,
						['post_content' => $updated_content],
						['ID' => $post_id]
					);
				}
			}
		}

		echo '<div class="updated"><p>'.$t.'ALT-Texte gespeichert!</p></div>';
	}
}
add_action('admin_init', 'datm_save_alt_texts');