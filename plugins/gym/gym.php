<?php
/**
 * Plugin Name: gym
 * Plugin URI: https://github.com/Dominique92/Chavil.gym
 * Description: Plugin WordPress pour la Gym Volontaire de Chaville
 * Author: Dominique Cavailhez
 * Version: 1.0.0
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit();
}

$nom_jour = ["lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche"];

add_filter("auto_plugin_update_send_email", "__return_false"); // Disable plugin update emails
add_filter("auto_theme_update_send_email", "__return_false"); // Disable theme update emails
add_filter("auto_theme_update_send_email", "__return_false"); // Disable update emails
add_filter("auto_core_update_send_email", "send_email_gym_plugin");
function send_email_gym_plugin($send, $type) {
	if (!empty($type) && $type == "success") {
		return false;
	}
	return true;
}

// Load syle.css files
add_action("wp_enqueue_scripts", "wp_enqueue_scripts_gym_plugin");
function wp_enqueue_scripts_gym_plugin($args) {
    wp_register_style("gym-plugin-style", plugins_url("style.css", __FILE__));
    wp_enqueue_style("gym-plugin-style");
}

// Search pdf templates in this plugin
add_action("wpo_wcpdf_template_paths", "wpo_wcpdf_template_paths_gym_plugin");
function wpo_wcpdf_template_paths_gym_plugin($installed_templates) {
	$installed_templates['child-plugin'] = str_replace(
		'woocommerce-pdf-invoices-packing-slips/templates',
		'gym/woocommerce/pdf',
		$installed_templates ['default']
	);

	return $installed_templates;
}

// Replace "Ben Oui" by "Oui"
add_action("init", "init_gym_plugin");
function init_gym_plugin() {
	global $wpdb;
	$orders_ben_oui = $wpdb->get_results(
		"SELECT * FROM wp3_wc_orders_meta " .
		"WHERE meta_value = 'Ben Oui'"
	);
	foreach ($orders_ben_oui as $obo)
		$wpdb->get_results("UPDATE wp3_wc_orders_meta SET meta_value = 'Oui' WHERE wp3_wc_orders_meta.id = ".$obo->id);
}

// Redirection d'une page produit
add_filter('template_include', 'template_include_gym_theme');
function template_include_gym_theme($template) {
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

// Horaires
add_shortcode("horaires", "horaires_gym_plugin");
function horaires_gym_plugin() {
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

/** Calendrier
* Code court
[calendrier]
2024-9-5
...
2025-4-17
[/calendrier]
*/
add_shortcode("calendrier", "calendrier_gym_plugin");
function calendrier_gym_plugin($args, $text) {
	global $wp_query, $nom_jour;

	// Seulement pour les pages (bug en edit)
	if (!isset ($wp_query->queried_object->ID)) {
		return;
	}

	setlocale(LC_TIME, "fr_FR");
	$calendrier = [];
	$annee_debut = 9999;
	$dateTime = new DateTime();

	// Déclarer les dates actives
	preg_match_all("/[0-9\-]+/", $text, $dates);
	foreach ($dates[0] as $d) {
		$annee_debut = min ($annee_debut, strtok($d, '-'));

		remplir_calendrier(
			$calendrier,
			strtotime($d),
			"date_active"
		);
	}

	// Déclarer les autres cases pour les jours de la semaine ayant une date
	for ($j = 1; $j < 310; $j++) {
		$dateTime->setDate($annee_debut, 9, $j); // Normalise le jour/mois/année
		remplir_calendrier($calendrier, $dateTime->getTimestamp());
	}

	// Afficher le calendrier
	$output = [];
	ksort($calendrier);
	foreach ($calendrier as $k => $v) {
		// Si rôle = éditeur
		$edit = isset(wp_get_current_user()->allcaps["edit_others_pages"]) ?
			"<a class=\"crayon\" title=\"Modification du calendrier\" href=\"" .
			get_bloginfo("url") .
			"/wp-admin/post.php?&action=edit&post={$wp_query->queried_object->ID}\">&#9998;</a>" :
			"";
		$output[] = "<table class=\"calendrier\">";
		$output[] = "<tr><td colspan=\"6\">Les {$nom_jour[$k-1]}s $edit</td></tr>";

		for ($m = 9; $m <= 18; $m++) { // Par rapport à $annee_debut septembre à juin
			$dateTime->setDate($annee_debut, $m, 1); // Normalise le jour/mois/année
			$mf = utf8_encode(strftime("%B", $dateTime->getTimestamp()));
			$vv = $v[$m + $annee_debut * 12];
			ksort($vv);

			$output[] = "<tr><td>{$mf}</td>";
			foreach ($vv as $kvv => $vvv) {
				$output[] = "<td class=\"$vvv\">$kvv</td>";
			}
			$output[] = "</tr>";
		}
		$output[] = "</table>";
	}
	$output[] = "</div>";

	return implode(PHP_EOL, $output);
}

function remplir_calendrier(&$calendrier, $time, $set = "") {
	$js = date("N",$time); // n° jour dans la semaine
	$ma = date("n",$time) + date("o",$time) * 12; // n° mois + année * 12
	$jm = date("j",$time); // n° jour dans le mois

	if ((isset($calendrier[$js]) || $set) &&
		!isset($calendrier[$js][$ma][$jm]))
		$calendrier[$js][$ma][$jm] = $set;
}

// Calcul des forfaits
add_action("woocommerce_before_calculate_totals", "wbct_gym_plugin", 20, 1);
function wbct_gym_plugin($cart) {
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

add_shortcode("doc_admin", "doc_admin_gym_plugin");
function doc_admin_gym_plugin() {
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
