<?php
/**
 * Plugin Name: facebook-simple-translation
 * Plugin URI: --
 * Description: Performs simple translation of strings. 
 * Version: 1.0.0
 * Author: Miles Minton (mminton1)
 * Author URI: --
 * Text Domain: n/a
 * Domain Path: n/a
 * Network: n/a
 * License: ©facebook 2015
 */

class FacebookSimpleTranslation {
	private static $site_uri;
	private static $desired_locales;
	private static $text_domain;

	public static function set_site_uri( $uri ) {
		self::$site_uri = $uri;
	}

	public static function set_desired_locales( $desired_locales ) {
		self::$desired_locales = $desired_locales;
	}

	public static function set_text_domain( $text_domain ) {
		self::$text_domain = $text_domain;
	}

	public static function initialize_translation () {
		// change locale.
		// must be called before load_theme_textdomain()
		add_filter( 'locale', function ( $locale ) {
			$current_locale = FacebookSimpleTranslation::get_current_locale();
			if ($current_locale !== '') {
				return $current_locale;
			}
			return $locale;
		} );

		load_theme_textdomain( self::$text_domain, get_template_directory().'/languages' );
	}

	private static function get_locale_list() {
		$desired_locales = array_map('strtolower', self::$desired_locales);
		if (!in_array('en-us', $desired_locales)) {
			$desired_locales[] = 'en-us';
		}
		return array_intersect_key(self::get_locale_array(), array_flip($desired_locales));
	}

	public static function get_current_locale() {
		$locales = self::get_locale_list();
		$request_subdomain = explode('.', $_SERVER['HTTP_HOST'])[0];
		$site_uri_subdomain = explode('.', self::$site_uri)[0];
		if ($request_subdomain !== $site_uri_subdomain && array_key_exists($request_subdomain, $locales)) {
			return $request_subdomain;
		}
		return 'en-us';
	}

	public static function language_picker() {
		$url = self::$site_uri;
		$onchange = "window.location=\"http://\" + document.getElementById(\"locale_picker\").value + \"" . self::$site_uri . "\";";
		$dropdown = '<select id="locale_picker" onchange="' . esc_js( $onchange ) . '">';

		foreach(self::get_locale_list() as $locale => $locale_info) {
			$selected_string = '';
			if ($locale == self::get_current_locale()) {
				$selected_string = 'selected';
			}
			if ($locale == 'en-us') {
				$locale = '';
			} else {
				$locale .= '.';
			}
			$dropdown .= '<option value="' . esc_attr($locale) . '" ' . esc_attr($selected_string) . '>' . esc_html($locale_info['name']) . ' (' . esc_html($locale_info['native_name']) . ')' . '</option>';
		}
		$dropdown .= '</select>';
		return $dropdown;
	}

	private static function get_locale_array() {
		return array(   
		  'af' => array(
		    'name' => 'Afrikaans',
		    'native_name' => 'Afrikaans'
		  ),
		  'ak' => array(
		    'name' => 'Akan',
		    'native_name' => 'Akan'
		  ),
		  'sq' => array(
		    'name' => 'Albanian',
		    'native_name' => 'Shqip'
		  ),
		  'am' => array(
		    'name' => 'Amharic',
		    'native_name' => 'አማርኛ'
		  ),
		  'ar' => array(
		    'name' => 'Arabic',
		    'native_name' => 'العربية'
		  ),
		  'hy' => array(
		    'name' => 'Armenian',
		    'native_name' => 'Հայերեն'
		  ),
		  'rup-mk' => array(
		    'name' => 'Aromanian',
		    'native_name' => 'Armãneashce'
		  ),
		  'as' => array(
		    'name' => 'Assamese',
		    'native_name' => 'অসমীয়া'
		  ),
		  'az' => array(
		    'name' => 'Azerbaijani',
		    'native_name' => 'Azərbaycan dili'
		  ),
		  'az-tr' => array(
		    'name' => 'Azerbaijani (Turkey)',
		    'native_name' => 'Azərbaycan Türkcəsi'
		  ),
		  'ba' => array(
		    'name' => 'Bashkir',
		    'native_name' => 'башҡорт теле'
		  ),
		  'eu' => array(
		    'name' => 'Basque',
		    'native_name' => 'Euskara'
		  ),
		  'bel' => array(
		    'name' => 'Belarusian',
		    'native_name' => 'Беларуская мова'
		  ),
		  'bn-bd' => array(
		    'name' => 'Bengali',
		    'native_name' => 'বাংলা'
		  ),
		  'bs-ba' => array(
		    'name' => 'Bosnian',
		    'native_name' => 'Bosanski'
		  ),
		  'bg-bg' => array(
		    'name' => 'Bulgarian',
		    'native_name' => 'Български'
		  ),
		  'my-mm' => array(
		    'name' => 'Burmese',
		    'native_name' => 'ဗမာစာ'
		  ),
		  'ca' => array(
		    'name' => 'Catalan',
		    'native_name' => 'Català'
		  ),
		  'bal' => array(
		    'name' => 'Catalan (Balear)',
		    'native_name' => 'Català (Balear)'
		  ),
		  'zh-cn' => array(
		    'name' => 'Chinese (China)',
		    'native_name' => '简体中文'
		  ),
		  'zh-hk' => array(
		    'name' => 'Chinese (Hong Kong)',
		    'native_name' => '香港中文版'
		  ),
		  'zh-tw' => array(
		    'name' => 'Chinese (Taiwan)',
		    'native_name' => '繁體中文'
		  ),
		  'co' => array(
		    'name' => 'Corsican',
		    'native_name' => 'Corsu'
		  ),
		  'hr' => array(
		    'name' => 'Croatian',
		    'native_name' => 'Hrvatski'
		  ),
		  'cs-cz' => array(
		    'name' => 'Czech',
		    'native_name' => 'Čeština‎'
		  ),
		  'da-dk' => array(
		    'name' => 'Danish',
		    'native_name' => 'Dansk'
		  ),
		  'dv' => array(
		    'name' => 'Dhivehi',
		    'native_name' => 'ދިވެހި'
		  ),
		  'nl' => array(
		    'name' => 'Dutch',
		    'native_name' => 'Nederlands'
		  ),
		  'nl-be' => array(
		    'name' => 'Dutch (Belgium)',
		    'native_name' => 'Nederlands (België)'
		  ),
		  'en-us' => array(
		    'name' => 'English',
		    'native_name' => 'English'
		  ),
		  'en-au' => array(
		    'name' => 'English (Australia)',
		    'native_name' => 'English (Australia)'
		  ),
		  'en-ca' => array(
		    'name' => 'English (Canada)',
		    'native_name' => 'English (Canada)'
		  ),
		  'en-gb' => array(
		    'name' => 'English (UK)',
		    'native_name' => 'English (UK)'
		  ),
		  'eo' => array(
		    'name' => 'Esperanto',
		    'native_name' => 'Esperanto'
		  ),
		  'et' => array(
		    'name' => 'Estonian',
		    'native_name' => 'Eesti'
		  ),
		  'fo' => array(
		    'name' => 'Faroese',
		    'native_name' => 'Føroyskt'
		  ),
		  'fi' => array(
		    'name' => 'Finnish',
		    'native_name' => 'Suomi'
		  ),
		  'fr-be' => array(
		    'name' => 'French (Belgium)',
		    'native_name' => 'Français de Belgique'
		  ),
		  'fr' => array(
		    'name' => 'French (France)',
		    'native_name' => 'Français'
		  ),
		  'fy' => array(
		    'name' => 'Frisian',
		    'native_name' => 'Frysk'
		  ),
		  'fuc' => array(
		    'name' => 'Fulah',
		    'native_name' => 'Pulaar'
		  ),
		  'gl-es' => array(
		    'name' => 'Galician',
		    'native_name' => 'Galego'
		  ),
		  'ka-ge' => array(
		    'name' => 'Georgian',
		    'native_name' => 'ქართული'
		  ),
		  'de' => array(
		    'name' => 'German',
		    'native_name' => 'Deutsch'
		  ),
		  'de-ch' => array(
		    'name' => 'German (Switzerland)',
		    'native_name' => 'Schweizer Hochdeutsch'
		  ),
		  'el' => array(
		    'name' => 'Greek',
		    'native_name' => 'Ελληνικά'
		  ),
		  'gn' => array(
		    'name' => 'Guaraní',
		    'native_name' => 'Avañe\'ẽ'
		  ),
		  'gu-in' => array(
		    'name' => 'Gujarati',
		    'native_name' => 'ગુજરાતી'
		  ),
		  'haw-us' => array(
		    'name' => 'Hawaiian',
		    'native_name' => 'Ōlelo Hawaiʻi'
		  ),
		  'haz' => array(
		    'name' => 'Hazaragi',
		    'native_name' => 'هزاره گی'
		  ),
		  'he-il' => array(
		    'name' => 'Hebrew',
		    'native_name' => 'עִבְרִית'
		  ),
		  'hi-in' => array(
		    'name' => 'Hindi',
		    'native_name' => 'हिन्दी'
		  ),
		  'hu' => array(
		    'name' => 'Hungarian',
		    'native_name' => 'Magyar'
		  ),
		  'is' => array(
		    'name' => 'Icelandic',
		    'native_name' => 'Íslenska'
		  ),
		  'ido' => array(
		    'name' => 'Ido',
		    'native_name' => 'Ido'
		  ),
		  'id' => array(
		    'name' => 'Indonesian',
		    'native_name' => 'Bahasa Indonesia'
		  ),
		  'ga' => array(
		    'name' => 'Irish',
		    'native_name' => 'Gaelige'
		  ),
		  'it' => array(
		    'name' => 'Italian',
		    'native_name' => 'Italiano'
		  ),
		  'ja' => array(
		    'name' => 'Japanese',
		    'native_name' => '日本語'
		  ),
		  'jv-id' => array(
		    'name' => 'Javanese',
		    'native_name' => 'Basa Jawa'
		  ),
		  'kn' => array(
		    'name' => 'Kannada',
		    'native_name' => 'ಕನ್ನಡ'
		  ),
		  'kk' => array(
		    'name' => 'Kazakh',
		    'native_name' => 'Қазақ тілі'
		  ),
		  'km' => array(
		    'name' => 'Khmer',
		    'native_name' => 'ភាសាខ្មែរ'
		  ),
		  'kin' => array(
		    'name' => 'Kinyarwanda',
		    'native_name' => 'Ikinyarwanda'
		  ),
		  'ky' => array(
		    'name' => 'Kirghiz',
		    'native_name' => 'кыргыз тили'
		  ),
		  'ko-kr' => array(
		    'name' => 'Korean',
		    'native_name' => '한국어'
		  ),
		  'ckb' => array(
		    'name' => 'Kurdish (Sorani)',
		    'native_name' => 'كوردی‎'
		  ),
		  'lo' => array(
		    'name' => 'Lao',
		    'native_name' => 'ພາສາລາວ'
		  ),
		  'lv' => array(
		    'name' => 'Latvian',
		    'native_name' => 'Latviešu valoda'
		  ),
		  'li' => array(
		    'name' => 'Limburgish',
		    'native_name' => 'Limburgs'
		  ),
		  'lin' => array(
		    'name' => 'Lingala',
		    'native_name' => 'Ngala'
		  ),
		  'lt' => array(
		    'name' => 'Lithuanian',
		    'native_name' => 'Lietuvių kalba'
		  ),
		  'lb-lu' => array(
		    'name' => 'Luxembourgish',
		    'native_name' => 'Lëtzebuergesch'
		  ),
		  'mk' => array(
		    'name' => 'Macedonian',
		    'native_name' => 'Македонски јазик'
		  ),
		  'mg' => array(
		    'name' => 'Malagasy',
		    'native_name' => 'Malagasy'
		  ),
		  'ms-my' => array(
		    'name' => 'Malay',
		    'native_name' => 'Bahasa Melayu'
		  ),
		  'ml-in' => array(
		    'name' => 'Malayalam',
		    'native_name' => 'മലയാളം'
		  ),
		  'mr' => array(
		    'name' => 'Marathi',
		    'native_name' => 'मराठी'
		  ),
		  'xmf' => array(
		    'name' => 'Mingrelian',
		    'native_name' => 'მარგალური ნინა'
		  ),
		  'mn' => array(
		    'name' => 'Mongolian',
		    'native_name' => 'Монгол'
		  ),
		  'me' => array(
		    'name' => 'Montenegrin',
		    'native_name' => 'Crnogorski jezik'
		  ),
		  'ne-np' => array(
		    'name' => 'Nepali',
		    'native_name' => 'नेपाली'
		  ),
		  'nb-no' => array(
		    'name' => 'Norwegian (Bokmål)',
		    'native_name' => 'Norsk bokmål'
		  ),
		  'nn-no' => array(
		    'name' => 'Norwegian (Nynorsk)',
		    'native_name' => 'Norsk nynorsk'
		  ),
		  'ory' => array(
		    'name' => 'Oriya',
		    'native_name' => 'ଓଡ଼ିଆ'
		  ),
		  'os' => array(
		    'name' => 'Ossetic',
		    'native_name' => 'Ирон'
		  ),
		  'ps' => array(
		    'name' => 'Pashto',
		    'native_name' => 'پښتو'
		  ),
		  'fa-ir' => array(
		    'name' => 'Persian',
		    'native_name' => 'فارسی'
		  ),
		  'fa-af' => array(
		    'name' => 'Persian (Afghanistan)',
		    'native_name' => '(فارسی (افغانستان'
		  ),
		  'pl' => array(
		    'name' => 'Polish',
		    'native_name' => 'Polski'
		  ),
		  'pt-br' => array(
		    'name' => 'Portuguese (Brazil)',
		    'native_name' => 'Português do Brasil'
		  ),
		  'pt' => array(
		    'name' => 'Portuguese (Portugal)',
		    'native_name' => 'Português'
		  ),
		  'pa-in' => array(
		    'name' => 'Punjabi',
		    'native_name' => 'ਪੰਜਾਬੀ'
		  ),
		  'rhg' => array(
		    'name' => 'Rohingya',
		    'native_name' => 'Ruáinga'
		  ),
		  'ro' => array(
		    'name' => 'Romanian',
		    'native_name' => 'Română'
		  ),
		  'ru' => array(
		    'name' => 'Russian',
		    'native_name' => 'Русский'
		  ),
		  'ru-ua' => array(
		    'name' => 'Russian (Ukraine)',
		    'native_name' => 'украї́нська мо́ва'
		  ),
		  'rue' => array(
		    'name' => 'Rusyn',
		    'native_name' => 'Русиньскый'
		  ),
		  'sah' => array(
		    'name' => 'Sakha',
		    'native_name' => 'Сахалыы'
		  ),
		  'sa-in' => array(
		    'name' => 'Sanskrit',
		    'native_name' => 'भारतम्'
		  ),
		  'srd' => array(
		    'name' => 'Sardinian',
		    'native_name' => 'Sardu'
		  ),
		  'gd' => array(
		    'name' => 'Scottish Gaelic',
		    'native_name' => 'Gàidhlig'
		  ),
		  'sr-rs' => array(
		    'name' => 'Serbian',
		    'native_name' => 'Српски језик'
		  ),
		  'sd-pk' => array(
		    'name' => 'Sindhi',
		    'native_name' => 'سندھ'
		  ),
		  'si-lk' => array(
		    'name' => 'Sinhala',
		    'native_name' => 'සිංහල'
		  ),
		  'sk' => array(
		    'name' => 'Slovak',
		    'native_name' => 'Slovenčina'
		  ),
		  'sl-si' => array(
		    'name' => 'Slovenian',
		    'native_name' => 'Slovenščina'
		  ),
		  'so' => array(
		    'name' => 'Somali',
		    'native_name' => 'Afsoomaali'
		  ),
		  'azb' => array(
		    'name' => 'South Azerbaijani',
		    'native_name' => 'گؤنئی آذربایجان'
		  ),
		  'es-ar' => array(
		    'name' => 'Spanish (Argentina)',
		    'native_name' => 'Español de Argentina'
		  ),
		  'es-cl' => array(
		    'name' => 'Spanish (Chile)',
		    'native_name' => 'Español de Chile'
		  ),
		  'es-co' => array(
		    'name' => 'Spanish (Colombia)',
		    'native_name' => 'Español de Colombia'
		  ),
		  'es-mx' => array(
		    'name' => 'Spanish (Mexico)',
		    'native_name' => 'Español de México'
		  ),
		  'es-pe' => array(
		    'name' => 'Spanish (Peru)',
		    'native_name' => 'Español de Perú'
		  ),
		  'es-pr' => array(
		    'name' => 'Spanish (Puerto Rico)',
		    'native_name' => 'Español de Puerto Rico'
		  ),
		  'es' => array(
		    'name' => 'Spanish (Spain)',
		    'native_name' => 'Español'
		  ),
		  'es-ve' => array(
		    'name' => 'Spanish (Venezuela)',
		    'native_name' => 'Español de Venezuela'
		  ),
		  'su-id' => array(
		    'name' => 'Sundanese',
		    'native_name' => 'Basa Sunda'
		  ),
		  'sw' => array(
		    'name' => 'Swahili',
		    'native_name' => 'Kiswahili'
		  ),
		  'sv-se' => array(
		    'name' => 'Swedish',
		    'native_name' => 'Svenska'
		  ),
		  'gsw' => array(
		    'name' => 'Swiss German',
		    'native_name' => 'Schwyzerdütsch'
		  ),
		  'tl' => array(
		    'name' => 'Tagalog',
		    'native_name' => 'Tagalog'
		  ),
		  'tg' => array(
		    'name' => 'Tajik',
		    'native_name' => 'Тоҷикӣ'
		  ),
		  'tzm' => array(
		    'name' => 'Tamazight (Central Atlas)',
		    'native_name' => 'ⵜⴰⵎⴰⵣⵉⵖⵜ'
		  ),
		  'ta-in' => array(
		    'name' => 'Tamil',
		    'native_name' => 'தமிழ்'
		  ),
		  'ta-lk' => array(
		    'name' => 'Tamil (Sri Lanka)',
		    'native_name' => 'தமிழ்'
		  ),
		  'tt-ru' => array(
		    'name' => 'Tatar',
		    'native_name' => 'Татар теле'
		  ),
		  'te' => array(
		    'name' => 'Telugu',
		    'native_name' => 'తెలుగు'
		  ),
		  'th' => array(
		    'name' => 'Thai',
		    'native_name' => 'ไทย'
		  ),
		  'bo' => array(
		    'name' => 'Tibetan',
		    'native_name' => 'བོད་སྐད'
		  ),
		  'tir' => array(
		    'name' => 'Tigrinya',
		    'native_name' => 'ትግርኛ'
		  ),
		  'tr' => array(
		    'name' => 'Turkish',
		    'native_name' => 'Türkçe'
		  ),
		  'tuk' => array(
		    'name' => 'Turkmen',
		    'native_name' => 'Türkmençe'
		  ),
		  'ug-cn' => array(
		    'name' => 'Uighur',
		    'native_name' => 'Uyƣurqə'
		  ),
		  'uk' => array(
		    'name' => 'Ukrainian',
		    'native_name' => 'Українська'
		  ),
		  'ur' => array(
		    'name' => 'Urdu',
		    'native_name' => 'اردو'
		  ),
		  'uz' => array(
		    'name' => 'Uzbek',
		    'native_name' => 'O‘zbekcha'
		  ),
		  'vi' => array(
		    'name' => 'Vietnamese',
		    'native_name' => 'Tiếng Việt'
		  ),
		  'wa' => array(
		    'name' => 'Walloon',
		    'native_name' => 'Walon'
		  ),
		  'cy' => array(
		    'name' => 'Welsh',
		    'native_name' => 'Cymraeg'
		  ),
		);
	}
}