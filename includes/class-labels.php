<?php
/**
 * Centralized labels for admin enum rendering.
 *
 * @package Toptour_Ref
 * @version 0.2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toptour_Ref_Labels {

	private static function groups() {
		return [
			'target_type' => [
				'general' => 'Všeobecné',
				'facility' => 'Zariadenie',
				'destination' => 'Destinácia',
				'point_of_interest' => 'Bod záujmu',
				'contact' => 'Kontakt',
				'interest' => 'Záujem',
				'offer' => 'Ponuka',
				'source' => 'Zdroj',
				'collection_task' => 'Zberová úloha',
			],
			'source_type' => [
				'review' => 'Recenzia',
				'guest_photo' => 'Fotka hosťa',
				'official_photo' => 'Oficiálna fotka',
				'video' => 'Video',
				'blog' => 'Blog',
				'forum' => 'Fórum',
				'platform_rating' => 'Platformové hodnotenie',
				'own_client_feedback' => 'Vlastná spätná väzba klienta',
				'social_media' => 'Sociálne siete',
				'map_listing' => 'Mapový profil',
				'article' => 'Článok',
				'mixed' => 'Zmiešaný zdroj',
				'other' => 'Iné',
			],
			'source_origin' => [
				'unknown' => 'Neznámy pôvod',
				'official_provider' => 'Oficiálny poskytovateľ',
				'verified_platform' => 'Overená platforma',
				'public_review_platform' => 'Verejná recenzná platforma',
				'social_media' => 'Sociálne siete',
				'guest_generated' => 'Obsah od hostí',
				'own_client' => 'Vlastný klient',
				'partner' => 'Partner',
				'local_resident' => 'Lokálny rezident',
				'blog_or_article' => 'Blog alebo článok',
				'forum' => 'Fórum',
				'map_service' => 'Mapová služba',
				'manual_discovery' => 'Manuálne nájdené',
				'other' => 'Iné',
			],
			'credibility_level' => [
				'unknown' => 'Neznáma',
				'low' => 'Nízka',
				'medium' => 'Stredná',
				'high' => 'Vysoká',
				'verified' => 'Overená',
			],
			'suggested_credibility_level' => [
				'' => '—',
				'unknown' => 'Neznáma',
				'low' => 'Nízka',
				'medium' => 'Stredná',
				'high' => 'Vysoká',
				'verified' => 'Overená',
			],
			'verification_method' => [
				'manual' => 'Manuálne',
				'cross_checked' => 'Krížovo overené',
				'client_confirmed' => 'Potvrdené klientom',
				'resident_confirmed' => 'Potvrdené rezidentom',
				'platform_consistency' => 'Konzistentnosť platformy',
				'photo_consistency' => 'Konzistentnosť fotiek',
				'not_verified' => 'Neoverené',
				'future_automation' => 'Budúca automatizácia',
			],
			'suggestion_status' => [
				'none' => 'Bez návrhu',
				'suggested' => 'Navrhnuté',
				'manager_review' => 'Čaká na manažéra',
				'accepted' => 'Prijaté',
				'rejected' => 'Odmietnuté',
				'applied' => 'Aplikované',
			],
			'search_priority' => [
				'low' => 'Nízka',
				'normal' => 'Normálna',
				'high' => 'Vysoká',
				'urgent' => 'Urgentná',
				'deferred' => 'Odložená',
			],
			'next_action' => [
				'review_source' => 'Skontrolovať zdroj',
				'extract_findings' => 'Vyťažiť zistenia',
				'compare_photos' => 'Porovnať fotky',
				'cross_check' => 'Krížovo overiť',
				'ask_resident' => 'Opýtať sa rezidenta',
				'ask_manager' => 'Opýtať sa manažéra',
				'archive' => 'Archivovať',
				'ignore' => 'Ignorovať',
			],
			'validation_status' => [
				'new' => 'Nové',
				'checked' => 'Skontrolované',
				'useful' => 'Užitočné',
				'weak' => 'Slabé',
				'duplicate' => 'Duplicitné',
				'rejected' => 'Odmietnuté',
				'archived' => 'Archivované',
			],
			'access_status' => [
				'unknown' => 'Neznáme',
				'accessible' => 'Dostupné',
				'login_required' => 'Vyžaduje prihlásenie',
				'blocked' => 'Blokované',
				'broken' => 'Nefunkčné',
				'paywalled' => 'Za platobnou bránou',
			],
			'finding_type' => [
				'positive' => 'Pozitívum',
				'risk' => 'Riziko',
				'contradiction' => 'Rozpor',
				'repeated_signal' => 'Opakujúci sa signál',
				'neutral' => 'Neutrálne pozorovanie',
				'uncertainty' => 'Neistota',
				'source_quality' => 'Kvalita zdroja',
			],
			'finding_area' => [
				'cleanliness' => 'Čistota',
				'food' => 'Strava',
				'staff' => 'Personál',
				'location' => 'Poloha',
				'transport' => 'Doprava',
				'noise' => 'Hluk',
				'room' => 'Izba',
				'bathroom' => 'Kúpeľňa',
				'safety' => 'Bezpečnosť',
				'beach' => 'Pláž',
				'slope' => 'Svah',
				'surroundings' => 'Okolie',
				'accessibility' => 'Dostupnosť',
				'photos' => 'Fotky',
				'price_value' => 'Pomer cena / hodnota',
				'service_quality' => 'Kvalita služieb',
				'wellness' => 'Wellness',
				'family_suitability' => 'Vhodnosť pre rodiny',
				'senior_suitability' => 'Vhodnosť pre seniorov',
				'local_experience' => 'Lokálna skúsenosť',
				'other' => 'Iné',
			],
			'signal_strength' => [
				'weak' => 'Slabý',
				'medium' => 'Stredný',
				'strong' => 'Silný',
				'critical' => 'Kritický',
			],
			'repetition_level' => [
				'single' => 'Jednorazové',
				'repeated' => 'Opakované',
				'frequent' => 'Časté',
				'dominant' => 'Dominantné',
			],
			'verification_status' => [
				'unverified' => 'Neoverené',
				'pending' => 'Čaká na overenie',
				'new' => 'Nové',
				'checked' => 'Skontrolované',
				'confirmed' => 'Potvrdené',
				'disputed' => 'Sporné',
				'rejected' => 'Odmietnuté',
				'revoked' => 'Odvolané',
				'archived' => 'Archivované',
			],
			'evidence_type' => [
				'text' => 'Text',
				'review_excerpt' => 'Výňatok z recenzie',
				'guest_photo' => 'Fotka hosťa',
				'official_photo' => 'Oficiálna fotka',
				'video' => 'Video',
				'own_observation' => 'Vlastné pozorovanie',
				'resident_feedback' => 'Spätná väzba rezidenta',
				'client_feedback' => 'Spätná väzba klienta',
				'source_crosscheck' => 'Krížové overenie zdrojov',
				'mixed' => 'Zmiešaný dôkaz',
				'other' => 'Iné',
			],
			'photo_type' => [
				'official_photo' => 'Oficiálna fotka',
				'guest_photo' => 'Fotka hosťa',
				'guest_video' => 'Video hosťa',
				'own_photo' => 'Vlastná fotka',
				'own_video' => 'Vlastné video',
				'platform_photo' => 'Fotka z platformy',
				'social_media_photo' => 'Fotka zo sociálnych sietí',
				'map_photo' => 'Fotka z mapovej služby',
				'mixed' => 'Zmiešané',
				'other' => 'Iné',
			],
			'comparison_category' => [
				'unknown' => 'Neznáme',
				'matches_official' => 'Zodpovedá oficiálnej prezentácii',
				'slightly_enhanced' => 'Mierne prikrášlené',
				'significant_contradiction' => 'Výrazný rozpor',
				'risk_detail' => 'Rizikový detail',
				'positive_surprise' => 'Pozitívne prekvapenie',
				'outdated_official' => 'Zastaraná oficiálna prezentácia',
				'unclear' => 'Nejasné',
			],
			'visual_area' => [
				'room' => 'Izba',
				'bathroom' => 'Kúpeľňa',
				'cleanliness' => 'Čistota',
				'food' => 'Strava',
				'restaurant' => 'Reštaurácia',
				'wellness' => 'Wellness',
				'pool' => 'Bazén',
				'beach' => 'Pláž',
				'slope' => 'Svah',
				'surroundings' => 'Okolie',
				'entrance' => 'Vstup',
				'parking' => 'Parkovanie',
				'transport' => 'Doprava',
				'safety' => 'Bezpečnosť',
				'accessibility' => 'Dostupnosť',
				'view' => 'Výhľad',
				'noise_context' => 'Kontext hluku',
				'family_context' => 'Kontext pre rodiny',
				'senior_context' => 'Kontext pre seniorov',
				'local_experience' => 'Lokálna skúsenosť',
				'other' => 'Iné',
			],
			'contact_type' => [
				'person' => 'Osoba',
				'organization' => 'Organizácia',
				'group' => 'Skupina',
			],
			'trust_level' => [
				'unknown' => 'Neznáma',
				'low' => 'Nízka',
				'medium' => 'Stredná',
				'high' => 'Vysoká',
				'verified' => 'Overená',
			],
			'resident_type' => [
				'local_helper' => 'Lokálny pomocník',
				'local_guide' => 'Lokálny sprievodca',
				'facility_contact' => 'Kontakt na zariadenie',
				'destination_observer' => 'Pozorovateľ destinácie',
				'transport_helper' => 'Pomoc s dopravou',
				'experience_host' => 'Hostiteľ zážitku',
				'emergency_helper' => 'Pomoc v núdzi',
				'community_connector' => 'Komunitný prepájač',
				'other' => 'Iné',
			],
			'availability_status' => [
				'unknown' => 'Neznáma',
				'available' => 'Dostupný',
				'limited' => 'Obmedzene dostupný',
				'unavailable' => 'Nedostupný',
				'seasonal' => 'Sezónne dostupný',
			],
			'badge_status' => [
				'none' => 'Žiadny',
				'planned' => 'Plánovaný',
				'issued' => 'Vydaný',
				'suspended' => 'Pozastavený',
				'revoked' => 'Zrušený',
			],
			'interest_type' => [
				'tourism' => 'Cestovný ruch',
				'hospitality' => 'Pohostinnosť',
				'transport' => 'Doprava',
				'nature' => 'Príroda',
				'culture' => 'Kultúra',
				'sport' => 'Šport',
				'wellness' => 'Wellness',
				'food' => 'Jedlo',
				'local_product' => 'Lokálny produkt',
				'safety' => 'Bezpečnosť',
				'marketing' => 'Marketing',
				'community' => 'Komunita',
				'investment' => 'Investície',
				'technology' => 'Technológie',
				'other' => 'Iné',
			],
			'interest_level' => [
				'low' => 'Nízka',
				'medium' => 'Stredná',
				'high' => 'Vysoká',
				'expert' => 'Expert',
			],
			'relationship_type' => [
				'knows' => 'Pozná',
				'family' => 'Rodina',
				'friend' => 'Priateľ',
				'business_partner' => 'Obchodný partner',
				'supplier' => 'Dodávateľ',
				'client' => 'Klient',
				'local_partner' => 'Lokálny partner',
				'community_member' => 'Člen komunity',
				'recommended_by' => 'Odporúčaný cez',
				'conflict' => 'Konflikt',
				'unknown' => 'Neznáme',
			],
			'relationship_strength' => [
				'weak' => 'Slabý',
				'medium' => 'Stredný',
				'strong' => 'Silný',
				'critical' => 'Kritický',
			],
			'mutuality_level' => [
				'unknown' => 'Neznáma',
				'one_way' => 'Jednostranná',
				'balanced' => 'Vyvážená',
				'strong_mutual' => 'Silná vzájomnosť',
				'strategic' => 'Strategická',
			],
			'influence_type' => [
				'local_authority' => 'Lokálna autorita',
				'social_network' => 'Sociálna sieť',
				'business_access' => 'Obchodný prístup',
				'operational_help' => 'Operačná pomoc',
				'knowledge_source' => 'Zdroj znalostí',
				'trust_bridge' => 'Most dôvery',
				'marketing_reach' => 'Marketingový dosah',
				'safety_support' => 'Bezpečnostná podpora',
				'logistics_support' => 'Logistická podpora',
				'community_connector' => 'Komunitný prepájač',
			],
			'usefulness_level' => [
				'unknown' => 'Neznáma',
				'low' => 'Nízka',
				'medium' => 'Stredná',
				'high' => 'Vysoká',
				'exceptional' => 'Mimoriadna',
			],
			'poi_type' => [
				'viewpoint' => 'Vyhliadka',
				'trail_start' => 'Začiatok trasy',
				'landing_area' => 'Pristávacia plocha',
				'takeoff_area' => 'Štartovisko',
				'transport_point' => 'Dopravný bod',
				'restaurant' => 'Reštaurácia',
				'local_product_place' => 'Miesto lokálnych produktov',
				'natural_site' => 'Prírodná lokalita',
				'cultural_site' => 'Kultúrna lokalita',
				'service_point' => 'Servisný bod',
				'risk_point' => 'Rizikový bod',
				'meeting_point' => 'Miesto stretnutia',
				'other' => 'Iné',
			],
			'facility_type' => [
				'hotel' => 'Hotel',
				'pension' => 'Penzión',
				'apartment' => 'Apartmán',
				'resort' => 'Rezort',
				'campsite' => 'Kemp',
				'chalet' => 'Chata',
				'wellness' => 'Wellness',
				'restaurant' => 'Reštaurácia',
				'service' => 'Služba',
				'other' => 'Iné',
			],
			'destination_type' => [
				'city' => 'Mesto',
				'mountains' => 'Hory',
				'sea' => 'More',
				'spa' => 'Kúpele',
				'countryside' => 'Vidiek',
				'adventure' => 'Dobrodružstvo',
				'family' => 'Rodina',
				'cultural' => 'Kultúra',
				'nature' => 'Príroda',
				'other' => 'Iné',
			],
			'task_status' => [
				'draft' => 'Koncept',
				'active' => 'Aktívna',
				'paused' => 'Pozastavená',
				'pending' => 'Čaká',
				'in_progress' => 'Prebieha',
				'done' => 'Hotovo',
				'failed' => 'Zlyhalo',
				'needs_review' => 'Vyžaduje kontrolu',
				'archived' => 'Archivované',
			],
			'collection_frequency' => [
				'manual' => 'Manuálne',
				'daily' => 'Denne',
				'twice_daily' => '2x denne',
				'three_times_daily' => '3x denne',
				'six_daily' => '6x denne',
				'custom' => 'Vlastné',
			],
			'finding_lifecycle_status' => [
				'new' => 'Nové',
				'pending_review' => 'Čaká na kontrolu',
				'accepted' => 'Prijaté',
				'rejected' => 'Odmietnuté',
				'duplicate' => 'Duplicitné',
				'needs_verification' => 'Vyžaduje overenie',
			],
			'task_run_status' => [
				'running' => 'Prebieha',
				'finished' => 'Dokončené',
				'failed' => 'Zlyhalo',
				'skipped' => 'Preskočené',
			],
			'task_event_type' => [
				'created' => 'Vytvorené',
				'updated' => 'Upravené',
				'enabled' => 'Zapnuté',
				'disabled' => 'Vypnuté',
				'frequency_changed' => 'Zmena frekvencie',
				'query_changed' => 'Zmena zadania',
				'run_started' => 'Beh spustený',
				'run_finished' => 'Beh dokončený',
				'run_skipped' => 'Beh preskočený',
				'finding_added' => 'Nález pridaný',
				'finding_accepted' => 'Nález prijatý',
				'finding_rejected' => 'Nález odmietnutý',
				'poi_suggested' => 'POI navrhnuté',
				'poi_accepted' => 'POI prijaté',
				'reference_analysis_created' => 'Analýza referencie vytvorená',
				'reference_analysis_updated' => 'Analýza referencie upravená',
				'offer_snapshot_created' => 'Snapshot ponuky vytvorený',
				'offer_snapshot_updated' => 'Snapshot ponuky upravený',
				'poi_candidate_suggested' => 'Extrakcia POI pripravená',
				'poi_candidate_accepted' => 'POI kandidát prijatý',
				'poi_candidate_rejected' => 'POI kandidát odmietnutý',
				'error' => 'Chyba',
				'manual_note_added' => 'Poznámka pridaná',
			],
			'priority' => [
				'low' => 'Nízka',
				'normal' => 'Normálna',
				'high' => 'Vysoká',
				'urgent' => 'Urgentná',
			],
			'expected_source_type' => [
				'' => '— žiadny —',
				'review' => 'Recenzia',
				'guest_photo' => 'Fotka hosťa',
				'official_photo' => 'Oficiálna fotka',
				'video' => 'Video',
				'blog' => 'Blog',
				'forum' => 'Fórum',
				'platform_rating' => 'Platformové hodnotenie',
				'mixed' => 'Zmiešané',
				'other' => 'Iné',
			],
			'discovery_run_status' => [
				'draft' => 'Koncept',
				'needs_input' => 'Potrebuje údaje',
				'ready' => 'Pripravené',
				'running' => 'Prebieha',
				'completed' => 'Dokončené',
				'failed' => 'Zlyhalo',
				'archived' => 'Archivované',
			],
			'discovery_candidate_status' => [
				'new' => 'Nový',
				'accepted' => 'Prijatý',
				'rejected' => 'Odmietnutý',
				'duplicate' => 'Duplicitný',
				'needs_review' => 'Vyžaduje kontrolu',
				'archived' => 'Archivovaný',
			],
			'mail_status' => [
				'draft' => 'Koncept',
				'ready' => 'Pripravený',
				'sent' => 'Odoslaný',
				'failed' => 'Zlyhal',
				'cancelled' => 'Zrušený',
			],
			'discovery_provider' => [
				'manual' => 'Manuálne',
				'search_api' => 'Search API (placeholder)',
				'future_provider' => 'Budúci provider',
			],
			'status' => [
				'draft' => 'Koncept',
				'watched' => 'Sledované',
				'verifying' => 'Overuje sa',
				'verified' => 'Overené',
				'archived' => 'Archivované',
			],
		];
	}

	public static function get_label( $group, $value ) {
		$value = (string) $value;
		$groups = self::groups();
		if ( isset( $groups[ $group ] ) && array_key_exists( $value, $groups[ $group ] ) ) {
			return $groups[ $group ][ $value ];
		}

		if ( '' === $value ) {
			return '—';
		}

		$humanized = str_replace( '_', ' ', $value );
		return ucwords( $humanized );
	}

	public static function get_options( $group, $values ) {
		$options = [];
		foreach ( (array) $values as $value ) {
			$options[] = [
				'value' => (string) $value,
				'label' => self::get_label( $group, $value ),
			];
		}
		return $options;
	}

	public static function status_label( $value ) { return self::get_label( 'status', $value ); }
	public static function target_type_label( $value ) { return self::get_label( 'target_type', $value ); }
	public static function source_type_label( $value ) { return self::get_label( 'source_type', $value ); }
	public static function source_origin_label( $value ) { return self::get_label( 'source_origin', $value ); }
	public static function credibility_level_label( $value ) { return self::get_label( 'credibility_level', $value ); }
	public static function suggested_credibility_level_label( $value ) { return self::get_label( 'suggested_credibility_level', $value ); }
	public static function verification_method_label( $value ) { return self::get_label( 'verification_method', $value ); }
	public static function suggestion_status_label( $value ) { return self::get_label( 'suggestion_status', $value ); }
	public static function search_priority_label( $value ) { return self::get_label( 'search_priority', $value ); }
	public static function next_action_label( $value ) { return self::get_label( 'next_action', $value ); }
	public static function validation_status_label( $value ) { return self::get_label( 'validation_status', $value ); }
	public static function access_status_label( $value ) { return self::get_label( 'access_status', $value ); }
	public static function finding_type_label( $value ) { return self::get_label( 'finding_type', $value ); }
	public static function finding_area_label( $value ) { return self::get_label( 'finding_area', $value ); }
	public static function signal_strength_label( $value ) { return self::get_label( 'signal_strength', $value ); }
	public static function repetition_level_label( $value ) { return self::get_label( 'repetition_level', $value ); }
	public static function evidence_type_label( $value ) { return self::get_label( 'evidence_type', $value ); }
	public static function photo_type_label( $value ) { return self::get_label( 'photo_type', $value ); }
	public static function comparison_category_label( $value ) { return self::get_label( 'comparison_category', $value ); }
	public static function visual_area_label( $value ) { return self::get_label( 'visual_area', $value ); }
	public static function contact_type_label( $value ) { return self::get_label( 'contact_type', $value ); }
	public static function trust_level_label( $value ) { return self::get_label( 'trust_level', $value ); }
	public static function resident_type_label( $value ) { return self::get_label( 'resident_type', $value ); }
	public static function availability_status_label( $value ) { return self::get_label( 'availability_status', $value ); }
	public static function badge_status_label( $value ) { return self::get_label( 'badge_status', $value ); }
	public static function interest_type_label( $value ) { return self::get_label( 'interest_type', $value ); }
	public static function interest_level_label( $value ) { return self::get_label( 'interest_level', $value ); }
	public static function relationship_type_label( $value ) { return self::get_label( 'relationship_type', $value ); }
	public static function relationship_strength_label( $value ) { return self::get_label( 'relationship_strength', $value ); }
	public static function mutuality_level_label( $value ) { return self::get_label( 'mutuality_level', $value ); }
	public static function influence_type_label( $value ) { return self::get_label( 'influence_type', $value ); }
	public static function usefulness_level_label( $value ) { return self::get_label( 'usefulness_level', $value ); }
	public static function poi_type_label( $value ) { return self::get_label( 'poi_type', $value ); }
	public static function facility_type_label( $value ) { return self::get_label( 'facility_type', $value ); }
	public static function destination_type_label( $value ) { return self::get_label( 'destination_type', $value ); }
	public static function task_status_label( $value ) { return self::get_label( 'task_status', $value ); }
	public static function collection_frequency_label( $value ) { return self::get_label( 'collection_frequency', $value ); }
	public static function finding_lifecycle_status_label( $value ) { return self::get_label( 'finding_lifecycle_status', $value ); }
	public static function task_run_status_label( $value ) { return self::get_label( 'task_run_status', $value ); }
	public static function task_event_type_label( $value ) { return self::get_label( 'task_event_type', $value ); }
	public static function priority_label( $value ) { return self::get_label( 'priority', $value ); }
	public static function expected_source_type_label( $value ) { return self::get_label( 'expected_source_type', $value ); }
	public static function discovery_run_status_label( $value ) { return self::get_label( 'discovery_run_status', $value ); }
	public static function discovery_candidate_status_label( $value ) { return self::get_label( 'discovery_candidate_status', $value ); }
	public static function mail_status_label( $value ) { return self::get_label( 'mail_status', $value ); }
	public static function verification_status_label( $value ) { return self::get_label( 'verification_status', $value ); }
	public static function discovery_provider_label( $value ) { return self::get_label( 'discovery_provider', $value ); }
}
