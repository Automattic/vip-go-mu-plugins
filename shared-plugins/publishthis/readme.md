# PublishThis Curation Plugin


## Minimum Requirements

* WordPress 3.3 or greater
* PHP version 5.2.4 or greater
* MySQL version 5.0 or greater

---

## Installation

1. Download the plugin file to your computer and unzip it.
2. Using an FTP program, or your hosting control panel, upload the
unzipped plugin folder to your WordPress installation's
wp-content/plugins/ directory.
3. Activate the plugin from the Plugins menu within the WordPress admin.

---

## Styling

* Each template provides various CSS class names that can be used to
customize the look at feel of each content type. See
publishthis/templates/ directory for examples.
* Each PublishThis specific class name is prefixed with "pt-".

---

## Templating

All necessary templates are included within the publishthis/templates/
directory. If you'd like to customize those templates, simply copy it
into a directory withing your theme named /publishthis, keeping the same
file structure.

Example: To overide the combined template, copy
publishthis/templates/combined.php to
<your-theme>/publishthis/templates/combined.php.

Each template has access to a global $pt_content variable. See the
default templates for the various properties and indexes available for
each template.

---

## Usage

Each page provides various help topics via the "Help" tab in the
WordPress admin. The "Help" tab is located at the top right portion of
the screen, and will slide down with information specific to each page.

Further help and support can be found at the [PublishThis Education
Center](http://docs.publishthis.com/).
