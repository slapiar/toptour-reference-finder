<?php
/**
 * Mail templates data class.
 *
 * Internal template storage for manually triggered manager notifications.
 *
 * @package Toptour_Ref
 * @version 0.1.13
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Mail_Templates {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'toptour_ref_mail_templates';
	}

	public static function seed_templates() {
		global $wpdb;
		$table = self::get_table_name();
		$now = current_time( 'mysql' );

		$seeds = [
			[
				'template_key'   => 'source_credibility_review_request',
				'template_name'  => 'Source credibility review request',
				'subject'        => 'TOPTOUR: Zdroj čaká na posúdenie dôveryhodnosti',
				'body'           => "Dobrý deň,\n\nv systéme TOPTOUR Reference Finder je zdroj, ktorý čaká na posúdenie dôveryhodnosti.\n\nZdroj: {{source_title}}\nURL: {{source_url}}\nAktuálna dôveryhodnosť: {{credibility_level}}\nNavrhovaná dôveryhodnosť: {{suggested_credibility_level}}\nDôvod návrhu: {{suggestion_reason}}\n\nProsím, skontrolujte zdroj a rozhodnite, či sa má dôveryhodnosť upraviť.\n\nToto je interné pracovné upozornenie systému TOPTOUR.",
				'recipient_role' => 'manager',
				'is_active'      => 1,
			],
			[
				'template_key'   => 'source_access_problem',
				'template_name'  => 'Source access problem',
				'subject'        => 'TOPTOUR: Problém s prístupom k referenčnému zdroju',
				'body'           => "Dobrý deň,\n\npri referenčnom zdroji bol označený problém s prístupom.\n\nZdroj: {{source_title}}\nURL: {{source_url}}\nStav prístupu: {{access_status}}\n\nProsím, overte, či je zdroj dostupný alebo má byť archivovaný.",
				'recipient_role' => 'manager',
				'is_active'      => 1,
			],
			[
				'template_key'   => 'source_priority_review',
				'template_name'  => 'Source priority review',
				'subject'        => 'TOPTOUR: Zdroj má vysokú prioritu preverenia',
				'body'           => "Dobrý deň,\n\nreferenčný zdroj bol označený ako prioritný na preverenie.\n\nZdroj: {{source_title}}\nURL: {{source_url}}\nPriorita: {{search_priority}}\nOdporúčaný ďalší krok: {{next_action}}\n\nProsím, rozhodnite o ďalšom postupe.",
				'recipient_role' => 'manager',
				'is_active'      => 1,
			],
		];

		foreach ( $seeds as $seed ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE template_key = %s", $seed['template_key'] ) );
			if ( ! $exists ) {
				$wpdb->insert(
					$table,
					[
						'template_key'   => $seed['template_key'],
						'template_name'  => $seed['template_name'],
						'subject'        => $seed['subject'],
						'body'           => $seed['body'],
						'recipient_role' => $seed['recipient_role'],
						'is_active'      => $seed['is_active'],
						'created_at'     => $now,
						'updated_at'     => $now,
					]
				);
			}
		}
	}

	public static function get_template_by_key( $template_key ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE template_key = %s", sanitize_key( $template_key ) ) );
	}

	public static function render_template( $template_key, $context = [] ) {
		$template = self::get_template_by_key( $template_key );
		if ( ! $template ) {
			return false;
		}

		$placeholders = [
			'{{source_title}}',
			'{{source_url}}',
			'{{credibility_level}}',
			'{{suggested_credibility_level}}',
			'{{suggestion_reason}}',
			'{{access_status}}',
			'{{search_priority}}',
			'{{next_action}}',
		];

		$values = [
			(string) ( $context['source_title'] ?? '' ),
			(string) ( $context['source_url'] ?? '' ),
			(string) ( $context['credibility_level'] ?? '' ),
			(string) ( $context['suggested_credibility_level'] ?? '' ),
			(string) ( $context['suggestion_reason'] ?? '' ),
			(string) ( $context['access_status'] ?? '' ),
			(string) ( $context['search_priority'] ?? '' ),
			(string) ( $context['next_action'] ?? '' ),
		];

		$subject = str_replace( $placeholders, $values, $template->subject );
		$body = str_replace( $placeholders, $values, $template->body );

		return [
			'template_key' => $template->template_key,
			'subject'      => $subject,
			'body'         => $body,
		];
	}
}
