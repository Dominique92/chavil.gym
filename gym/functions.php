<?php
/**
 * Theme Name: gym
 * Theme URI: https://github.com/Dominique92/Chavil.gym
 * Description: Theme WordPress pour la Gym Volontaire de Chaville
 * Author: Dominique Cavailhez
 * Version: 1.0.0
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit();
}

// Load correctly syle.css files
add_action("wp_enqueue_scripts", "wp_enqueue_scripts_function");
function wp_enqueue_scripts_function() {
	wp_register_style("gym-theme-style", get_stylesheet_uri());
	wp_enqueue_style("gym-theme-style");
}

// Use global urls in block templates (as defined in wp-includes/general-template.php)
add_shortcode("get_info", "get_info_function");
function get_info_function($args) {
	if ($args[0] == "current_user_id") {
		return get_current_user_id(); //TODO use is_user_logged_in()
   	} else {
		return get_bloginfo($args[0]);
	}
}

// Sous menu dans la page
add_shortcode("menu", "menu_function");
function menu_function($args) {
	return wp_nav_menu([
		"menu_class" => @$args["class"],
		"echo" => false,
	]);
}

// Redirection d'une page produit
add_filter('template_include', 'template_include_function');
function template_include_function($template) {
	global $post;

	if ($post) {
		$query = get_queried_object();
		$cat = get_the_terms($post->ID, 'product_cat');

		if (isset($query->post_type) &&
			$query->post_type == 'product' &&
			$cat)
			header('Location: '.get_site_url().'/'.$cat[0]->slug);
	}

	return $template;
}

add_action("admin_head", "admin_head_function");
function admin_head_function() {
	wp_enqueue_style("admin_css", get_stylesheet_directory_uri() . "/style.css");
}

/* POUR PLUGIN */

$annee = 2024;
$nom_jour = ["lundi", "mardi", "mercredi", "jeudi", "vendredi",
	"samedi", "dimanche"];
$nom_mois = ["janvier", "fevrier", "mars", "avril", "mai", "juin",
	"juillet", "aout", "septembre", "octobre", "novembre", "décembre",
	"janvier", "fevrier", "mars", "avril", "mai", "juin"];

add_filter("auto_plugin_update_send_email", "__return_false"); // Disable plugin update emails
add_filter("auto_theme_update_send_email", "__return_false"); // Disable theme update emails
add_filter("auto_theme_update_send_email", "__return_false"); // Disable update emails
add_filter("auto_core_update_send_email", "send_email_function");
function send_email_function($send, $type) {
	if (!empty($type) && $type == "success") {
		return false;
	}
	return true;
}

// Load correctly syle.css files
add_action("wp_enqueue_scripts", "wp_enqueue_scripts_function_2");
function wp_enqueue_scripts_function_2() {

	// Replace "Ben Oui" by "Oui"
	global $wpdb;
	$orders_ben_oui = $wpdb->get_results(
		"SELECT * FROM wp3_wc_orders_meta " .
		"WHERE meta_value = 'Ben Oui'"
	);
	foreach ($orders_ben_oui as $obo)
		$wpdb->get_results(
			"UPDATE wp3_wc_orders_meta " .
			"SET meta_value = 'Oui' " .
			"WHERE wp3_wc_orders_meta.id = ".$obo->id
		);
}

// Horaires
add_shortcode("horaires", "horaires_function");
function horaires_function() {
	global $nom_jour, $wp_query;

	// Seulement pour les pages
	if (!isset ($wp_query->queried_object->post_title)) {
		return;
	}

	$products = wc_get_products([
		"status" => "publish",
		"limit" => - 1,
	]);
	$horaires = [];
	foreach ($products as $p) {
		$id = $p->get_id();

		$cours = explode("*", $p->get_title());
		foreach ($cours as $k => $v) {
			$cours[$k] = ucfirst(trim($v));
		}

		if (count($cours) == 5 &&
			strstr(
				" * " . str_replace (
					["<", "’", ">"],
					[" * ", "'", " * "],
					wc_get_product_category_list($id)
				) .
				" * " . wc_get_product($id)->get_data()["name"] . " * Horaires * ",
				"* " . $wp_query->queried_object->post_title . " *"
			)) {
			$no_day = array_search(strtolower($cours[1]), $nom_jour);
			preg_match("/\/([^\/]*)\/\"/", wc_get_product_category_list($id), $category);
			$cours[] = $category[1];
			$cours[] = $id;
			$horaires[$no_day][$cours[2].$id] = $cours;
		}
	}

	$cal = ["\n<div class=\"horaires\">"];
	ksort($horaires);
	foreach ($horaires as $no_jour => $jour) {
		$rj = ["\t<table class=\"has-background\">", "\t\t<caption>{$nom_jour[$no_jour]}</caption>"];
		ksort($jour);
		foreach ($jour as $heure) {
			// Ligne sécable si trop longue et comporte des ()
			if (strlen($heure[0]) > 30) $heure[0] = implode("<br/>(", explode("(", $heure[0]));
			$panier = "<a href=\"" .
				get_bloginfo("url") . "/panier?add-to-cart=" . $heure[6] .
				'" title="S\'inscrire"">&#128722;</a>';
			$edit = isset(wp_get_current_user()->allcaps["edit_others_pages"]) ?
				"<a class=\"crayon\" title=\"Modification de la séance\" href=\"" .
					get_bloginfo("url") .
					"/wp-admin/post.php?&action=edit&post={$heure[6]}\">&#9998;</a>" :
				"";
			$ligne = [
				$heure[2] . " &nbsp; " . $panier,
				$edit . " &nbsp; " . lien_page($heure[0], $heure[5]),
				lien_page($heure[4]),
				lien_page($heure[3]),
			];
			$rj[] = "\t\t<tr>\n\t\t\t<td>" .
				implode("</td>\n\t\t\t<td>", $ligne) .
				"</td>\n\t\t</tr>";
		}
		$rj[] = "\t</table>";
		$cal[] = implode(PHP_EOL, $rj);
	}
	$cal[] = "</div>";

	return implode(PHP_EOL, $cal);
}

function lien_page($titre, $slug = "") {
	global $slugs_pages;
	if (!isset($slugs_pages)) {
		$pages = get_pages([
			"post_type" => "page",
			"post_status" => "publish",
		]);
		$slugs_pages = [];
		foreach ($pages as $p) {
			$slugs_pages[$p->post_title] = $p->post_name;
		}
	}
	if (!$slug && isset($slugs_pages[$titre])) {
		$slug = $slugs_pages[$titre];
	}
	if ($slug) {
		return '<a href="' . get_bloginfo("url") . "/$slug\">$titre</a>";
	} else {
		return $titre;
	}
}

// Calendrier
add_shortcode("calendrier", "calendrier_function");
function calendrier_function() {
	global $annee, $nom_jour, $nom_mois, $wp_query;

	// Seulement pour les pages
	if (!isset ($wp_query->queried_object->post_title)) {
		return;
	}

	date_default_timezone_set("Europe/Paris");
	$calendrier = [];

	// Remplir des cases actives
	preg_match_all("|<tr>.*</tr>|U", $wp_query->queried_object->post_content, $lignes);
	foreach ($lignes[0] as $l) {
		preg_match_all("|<td>(.*)</td>|U", $l, $colonnes);
		if (count($colonnes) == 2 && is_numeric($colonnes[1][0])) {
			foreach (explode(",", $colonnes[1][1]) as $jour) {
				remplir_calendrier(
					$calendrier,
					$annee + ($colonnes[1][0] < 8 ? 1 : 0),
					$colonnes[1][0],
					$jour,
					"date_active"
				);
			}
		}
	}

	// Déclarer les autres cases (uniquement si jour déjà existant)
	for ($j = 1;$j < 310;$j++) {
		remplir_calendrier($calendrier, $annee, 9, $j, "");
	}

	// Afficher le calendrier
	$cal = [];
	ksort($calendrier);
	foreach ($calendrier as $k => $v) {
		$edit = isset(wp_get_current_user()->allcaps["edit_others_pages"]) ?
			"<a class=\"crayon\" title=\"Modification du calendrier\" href=\"" . get_bloginfo("url") .
			"/wp-admin/post.php?&action=edit&post={$wp_query->queried_object->ID}\">&#9998;</a>" :
			"";
		$cal[] = "<table class=\"calendrier\">";
		$cal[] = "<tr><td colspan=\"6\">Les {$nom_jour[$k]}s $edit</td></tr>";

		ksort($v);
		foreach ($v as $kv => $vv) {
			if ($kv < 19) {
				// N'affiche pas juillet
				$cal[] = "<tr><td>{$nom_mois[$kv]}</td>";
				ksort($vv);
				foreach ($vv as $kvv => $vvv) {
					$cal[] = "<td class=\"$vvv\">$kvv</td>";
				}
				$cal[] = "</tr>";
			}
		}
		$cal[] = "</table>";
	}
	$cal[] = "</div>";

	return implode(PHP_EOL, $cal);
}

function remplir_calendrier(&$calendrier, $an, $mois, $jour, $set) {
	global $annee;

	if ($jour) {
		$dateTime = new DateTime();
		$dateTime->setDate($an, $mois, $jour);
		$dt = explode(" ", $dateTime->format("Y N n j"));
		$no_jour = $dt[1];
		$no_mois = $dt[2] + ($dt[0] - $annee) * 12;

		if (!isset($calendrier[$no_jour]) && $set) { // On crée le jour si on y a une date
			$calendrier[$no_jour] = [];
		}
		if (isset($calendrier[$no_jour])) { // On popule si le jour est crée
			@$calendrier[$no_jour][$no_mois][$dt[3]] .= $set;
		}
	}
}

// Calcul des forfaits
add_action("woocommerce_before_calculate_totals", "wbct_function", 20, 1);
function wbct_function($cart) {
	// Calcul du total des cours
	$nb_cours = $total_cours = $nb_mn = $total_mn = 0;
	foreach ($cart->get_cart() as $item) {
		$is_mn = str_contains(
			wc_get_product_category_list(
				$item["data"]->get_id()
			),
			"nordique"
		);
		if ($item["data"]->get_price() > 10) { // Exclus "dons"
			if ($is_mn) {
				if ($nb_mn++)
					$total_mn += $item["data"]->get_price();
				else {
					$nb_cours++;
					$total_cours += $item["data"]->get_price();
				}
			} else {
				$nb_cours++;
				$total_cours += $item["data"]->get_price();
			}
		}
	}

	// Liste des coupons
	$coupons = get_posts([
		"post_type" => "shop_coupon",
		"post_status" => "publish",
	]);
	foreach ($coupons as $c) {
		$coupon = new WC_Coupon($c->post_title);
		$min = $coupon->get_meta("_wjecf_min_matching_product_qty") ? : 0;
		$max = $coupon->get_meta("_wjecf_max_matching_product_qty") ? : 1000;

		if (str_contains($c->post_title, "nordique")) {
			if ($total_mn)
				$cart->add_fee($c->post_title, -$total_mn);
		} elseif ($min <= $nb_cours && $nb_cours <= $max)
			$cart->add_fee($c->post_title, $coupon->get_amount() - $total_cours);
	}
}

add_shortcode("doc_admin", "doc_admin_function");
function doc_admin_function() {
	// Verification de droits d'accès
	if (!count(array_intersect(["administrator", "shop_manager"], wp_get_current_user()->roles))) {
		return;
	}

	parse_str($_SERVER["QUERY_STRING"], $query);

	// Affichage de la liste des commandes admin
	// Texte dans la page doc_admin
	$doc_admin = get_page_by_path("doc_admin");
	if (!count($query) && $doc_admin) {
		return "<h1>Fonctions d'administration GYM</h1>" . $doc_admin->post_content;
	}

	// Téléchargement du fichier de compta
	if (isset ($query["extract"])) {
		$order_db = [];
		$order_list = [[
			"Commande",
			"Date",
			"Nom",
			"Prénom",
			"Payé",
			"Commission",
			"Reçu",
			"Statut",
		]];

		foreach (wc_get_orders([]) as $order) {
			$o = $order->get_data();
			$order_db[$o["status"]][$o["id"]] = $o;
		}

		foreach ($order_db as $o_stat) {
			ksort($o_stat);
			foreach ($o_stat as $o) {
				$total = floatval($o["total"]);
				$com = round($total * 0.015 + 0.25, 2);

				if (intval ($o["total"]))
					$order_list[] = [
						$o["id"],
						$o["date_created"]->date_i18n(),
						$o["billing"]["last_name"],
						$o["billing"]["first_name"],
						number_format($total, 2, ',', ''),
						number_format($com, 2, ',', ''),
						number_format($total - $com, 2, ',', ''),
						$o["status"],
					];
			}
		}
		// Totaux
		$order_list[] = ['', '', '', 'Total',
			'=SOMME(E2:E'.count($order_list).')',
			'=SOMME(F2:F'.count($order_list).')',
			'=SOMME(G2:G'.count($order_list).')',
		];

		// Ecriture du fichier
		header("Content-Description: File Transfer");
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=" . $query["extract"] . ".csv");
		header("Content-Transfer-Encoding: binary");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: public");
		echo "\xEF\xBB\xBF"; // UTF-8 BOM

		ob_start();
		$out = fopen("php://output", "w");
		foreach ($order_list as $i) {
			fputcsv($out, $i, ";");
		}
		fclose($out);
		echo ob_get_clean();

		exit();
	}
}
