<?php
/**
 * CheezCap - Cheezburger Custom Administration Panel
 * (c) 2008 - 2011 Cheezburger Network (Pet Holdings, Inc.)
 * LOL: http://cheezburger.com
 * Source: http://github.com/cheezburger/cheezcap/
 * Authors: Kyall Barrows, Toby McKes, Stefan Rusek, Scott Porad
 * UnLOLs by Mo Jangda (batmoo@gmail.com)
 * License: GNU General Public License, version 2 (GPL), http://www.gnu.org/licenses/gpl-2.0.html
 */

$number_entries = array( '', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '12', '14', '16', '18', '20' );
$number_entries_labels = array( '== Select a Number ==' );

$cap = new CheezCap( array(
		new CheezCapGroup( 'First Group', 'firstGroup',
			array(
				new CheezCapBooleanOption(
					'Simple Boolean Example',
					'This will create a simple true/false switch with default of "true".',
					'simple_boolean_example',
					true
				),
				new CheezCapTextOption(
					'Simple Text Example',
					'This will create store a string value with a default of "Say Cheez!".',
					'simple_text_example',
					'Say Cheez!'
				),
				new CheezCapTextOption(
					'Text Area Example',
					'This text option is displayed as a Text Area',
					'text_area_example',
					'Sup Dawg?  I put an option in your option so that you would have options.',
					true
				),
				new CheezCapDropdownOption(
					'Reusable Options Dropdown Example',
					'This dropdown creates its options by reusing an array.',
					'resuable_options_dropdown_example',
					$number_entries,
					0, // Default index is 0, 0 == 'Select a Number:'
					$number_entries_labels
				),
			)
		),
		new CheezCapGroup( 'Another Group', 'anotherGroup',
			array(
				new CheezCapBooleanOption(
					'Simple Boolean Example #2',
					'This will create a simple true/false switch with default of "true".',
					'simple_boolean_example2',
					true
				),
				new CheezCapTextOption(
					'Simple Text Example #2',
					'This will create store a string value with a default of "Say Cheez!".',
					'simple_text_example2',
					'Say Cheez!'
				),
				new CheezCapTextOption(
					'Text Area Example #2',
					'This text option is displayed as a Text Area',
					'text_area_example2',
					'Sup Dawg?  I put an option in your option so that you would have options.',
					true
				),
				new CheezCapDropdownOption(
					'Inline Options Dropdown Example #2',
					'This dropdown creates its options using an inline array.',
					'inline_options_dropdown_example2',
					array( 'Red', 'Yellow', 'Green' ),
					1 // Yellow
				),
				new CheezCapDropdownOption(
					'Reusable Options Dropdown Example #2',
					'This dropdown creates its options by reusing an array.',
					'resuable_options_dropdown_example2',
					$number_entries,
					1, // 1
					$number_entries_labels
				),
			)
		),
		new CheezCapGroup( 'Yet Another', 'yetAnother',
			array(
				new CheezCapBooleanOption(
					'Simple Boolean Example #3',
					'This will create a simple true/false switch with default of "true".',
					'simple_boolean_example3',
					true
				),
				new CheezCapTextOption(
					'Simple Text Example #3',
					'This will create store a string value with a default of "Say Cheez!".',
					'simple_text_example3',
					'Say Cheez!'
				),
				new CheezCapTextOption(
					'Text Area Example #3',
					'This text option is displayed as a Text Area',
					'text_area_example3',
					'Sup Dawg?  I put an option in your option so that you would have options.',
					true
				),
				new CheezCapDropdownOption(
					'Inline Options Dropdown Example #3',
					'This dropdown creates its options using an inline array.',
					'inline_options_dropdown_example3',
					array( 'Red', 'Yellow', 'Green' ),
					2 // Green
				),
				new CheezCapDropdownOption(
					'Reusable Options Dropdown Example #3',
					'This dropdown creates its options by reusing an array.',
					'resuable_options_dropdown_example3',
					$number_entries,
					2, // 2
					$number_entries_labels
				),
			)
		)
	), array(
		'themename' => 'CheezCap', // used on the title of the custom admin page
		'req_cap_to_edit' => 'manage_options', // the user capability that is required to access the CheezCap settings page
		'cap_menu_position' => 99, // OPTIONAL: This value represents the order in the dashboard menu that the CheezCap menu will display in. Larger numbers push it further down.
		'cap_icon_url' => '', // OPTIONAL: Path to a custom icon for the CheezCap menu item. ex. $cap_icon_url = WP_CONTENT_URL . '/your-theme-name/images/awesomeicon.png'; Image size should be around 20px x 20px.
	)
);
