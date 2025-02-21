<?php
/**
 * Plugin Name: Divi ALT-Text Manager
 * Description: Listet alle Bilder aus Divi's et_pb_image-Modulen und erlaubt das Bulk-Bearbeiten der ALT-Texte.
 * Version: 1.0
 * Author: Jens Fuchs
 * Author URI: https://www.d-mind.de
 */

if (!defined('ABSPATH')) {
	exit;
}

// Admin-Menü-Eintrag hinzufügen
function datm_add_menu_page() {
	add_menu_page('Divi Bulk Alt-Text', 'Divi Bulk Alt-Text', 'manage_options', 'divi-alt-text-manager', 'datm_admin_page');
}
add_action('admin_menu', 'datm_add_menu_page');

// Admin-Menü-Eintrag für die Subseite hinzufügen
function datm_add_submenu_page() {
	add_submenu_page(
		'divi-alt-text-manager', // Slug der Hauptseite (muss mit deinem Hauptmenü übereinstimmen)
		'Einstellungen',         // Titel der Unterseite
		'Einstellungen',         // Titel im Menü
		'manage_options',        // Berechtigungen
		'divi-alt-text-settings',// Slug der Unterseite
		'datm_settings_page'     // Callback-Funktion für die Anzeige der Seite
	);
}
add_action('admin_menu', 'datm_add_submenu_page');


// Funktion für die Admin-Seite
function datm_admin_page() {
	global $wpdb;
	$amount_articles = get_option('dmind_amount_articles', 15);

	// Alle Divi-Layouts durchsuchen
	$posts = $wpdb->get_results("
        SELECT ID, post_content 
        FROM {$wpdb->posts} 
        WHERE post_type IN ('page', 'post') 
        AND post_content LIKE '%et_pb_image%'
        AND post_status = 'publish'
        ORDER BY ID ASC
        LIMIT 0, $amount_articles
    ");

	echo '<div class="wrap">';
	echo '<h1>Divi ALT-Text Manager</h1>';
	echo '<form method="post" action="">';
	echo '<table class="wp-list-table widefat fixed striped">';
	echo '<thead><tr><th>'.__('Bild', 'divi-alt-text-manage').'</th><th>'.__('Alt-Text', 'divi-alt-text-manage').'</th></tr></thead><tbody>';

	wp_nonce_field('save_alt_texts', 'datm_nonce');

	$count = 0;
	foreach ($posts as $post) {
		$content = maybe_unserialize($post->post_content);

		echo '<tr><th colspan="4">'.__('Prüfe', 'divi-alt-text-manage').' Post-ID '.$post->ID.' ('. get_the_title($post->ID) .')</th></tr>';

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
				echo '<tr><td colspan="4">'.__('Keine Bilder gefunden', 'divi-alt-text-manage').'</td></tr>';
			}
		}
	}

	echo '</tbody></table>';
	echo '<br><input type="submit" name="save_alt_texts" class="button-primary" value="'.__('Speichern', 'divi-alt-text-manage').'">';
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

					echo '<div class="updated"><p>"'.esc_attr($new_alt_text).'" zu Post <a href="' . get_the_permalink($post_id) . '">"'.get_the_title($post_id).'"</a> gespeichert.</p></div>';
				}
			}
		}

		//echo '<div class="updated"><p>'.$t.'ALT-Texte gespeichert!</p></div>';
	}
}
add_action('admin_init', 'datm_save_alt_texts');


function datm_settings_page() {
	?>
	<div class="wrap">
		<h1>Divi Alt-Text Einstellungen</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields('datm_settings_group'); // Name der Einstellungsgruppe
			do_settings_sections('divi-alt-text-settings'); // Slug der Seite
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function datm_register_settings() {
	register_setting('datm_settings_group', 'dmind_amount_articles'); // Name der Option

	add_settings_section(
		'datm_main_section',
		'Anzahl der Artikel',
		null,
		'divi-alt-text-settings'
	);

	add_settings_field(
		'dmind_amount_articles',
		'Bitte die Anzahl der Artikel eingeben (leer=15)',
		'dmind_amount_articles_callback',
		'divi-alt-text-settings',
		'datm_main_section'
	);
}
add_action('admin_init', 'datm_register_settings');

function dmind_amount_articles_callback() {
	$value = get_option('dmind_amount_articles', '');
	echo '<input type="text" name="dmind_amount_articles" value="' . esc_attr($value) . '" />';
}
