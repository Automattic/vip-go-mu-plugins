=== Facebook Simple Translation ===
Contributors: mminton1
Tags: translation

== Description ==

Creates a simple language switcher for showing translated strings in your theme. Include the language switcher by echoing the result of FacebookSimpleTranslation::language_picker().   

See http://codex.wordpress.org/I18n_for_WordPress_Developers about how to write strings so that they can be translated. 

== Set Up ==

In order to set up this plugin for translation, do the following:
1) 
In functions.php, call the following methods in order to configure your translation:

FacebookSimpleTranslation::set_site_uri('http://vip.local/');
FacebookSimpleTranslation::set_desired_locales(array('en-us', 'fr', 'de', 'ar'));
FacebookSimpleTranslation::set_text_domain('facebook-simple-translation'); 
FacebookSimpleTranslation::initialize_translation();

set_site_uri takes a string which is your site. 
set_desired_locales takes an array of strings which are locale codes, representing different translated versions of your site. These locale codes need to match the locale codes in get_locale_list, ie 'en-us' or 'de' or 'ar'.
set_text_domain should be a string that is text domain name that you use in your string function calls, like _e( 'String to Display', 'the-text-domain' ); See the i18n for developers link below for more info. 

After you have called the three required functions (set_site_uri, set_desired_locales, and set_default_locale) in your functions.php file, call FacebookSimpleTranslation::initialize_translation()

2) Echo FacebookSimpleTranslation::language_picker() wherever you would like to have a language picker.

3) Create a folder called 'languages' in the root directory of your theme.

To generate a .po file for sending to translation, run this command from the languages directory:
find ../ -iname "*.php" | xargs xgettext --from-code=UTF-8 -k__ -k_e -k_n -k_x -k_ex -kesc_html_e -kesc_html -o translation_file.po

Send the generated file translation_file.po to be translated. What you should recieve back is a series of files in the format de_DE.po, (German) or ar_AR.po (Arabic), etc. 
If the names of these .po files do not match exactly the locale codes in the function get_locale_list() in facebook-simple-translation.php, change their name so that they do. 

To generate .mo files, which are the files that the will be used for translation, from the .po files, run the following command in the /languages directory:
for file in `find . -name "*.po"` ; do msgfmt -o ${file/.po/.mo} $file ; done


Again, see http://codex.wordpress.org/I18n_for_WordPress_Developers about how to write strings so that they can be translated. 

== Style ==

Set css rules targeting #locale_picker to modify the style on the language selector.