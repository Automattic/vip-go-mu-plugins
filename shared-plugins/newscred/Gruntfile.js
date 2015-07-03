module.exports = function(grunt) {
    grunt.initConfig({
        concat: {
            metabox_js: {
                src: [
                    'static/js/backbone/main.js',
                    'static/js/backbone/models/*.js',
                    'static/js/backbone/collections/*.js',
                    'static/js/backbone/views/*.js',
                    'static/js/backbone/common.js',
                    'static/js/backbone/router.js',
                    'static/js/backbone/run.js'
                ],
                dest: 'static/build/js/metabox.js',
                options: {
                    banner: 'var $nc = jQuery.noConflict();(function($){',
                    footer : '})($nc);'
                }

            },
            myfeeds_js: {
                src: [
                    'static/js/myfeeds.js'
                ],
                dest: 'static/build/js/myfeeds.js'
            },
            nc_plugin_lib_js: {
                src: [
                    'static/js/lib/jquery.colorbox.js',
                    'static/js/lib/jquery.tooltipster.min.js',
                    'static/js/lib/select2.min.js'
                ],
                dest: 'static/build/js/lib.js'
            },
            metabox_main_css: {
                src: [
                    'static/css/colorbox.css',
                    'static/css/tooltipster.css',
                    'static/css/select2.css',
                    'static/css/style.css'
                ],
                dest: 'static/css/style.min.css'
            },
            metabox_html: {
                src: [
                    'static/js/backbone/templates/*.html'
                ],
                dest: 'static/build/html/metabox.html'
            }
        },
        watch: {
            files: ['static/js/**/*.js','static/css/**/*.css', 'static/js/backbone/templates/*.html'],
            tasks: ['dev' ]
        }
    });
    grunt.loadNpmTasks('grunt-contrib-concat');


    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-cssmin');

//    grunt.loadNpmTasks('grunt-contrib-compress');

    //register tasks
    grunt.registerTask('dev:nc_plugin',
        [
            'concat:metabox_js',
            'concat:myfeeds_js',
            'concat:nc_plugin_lib_js',
            'concat:metabox_main_css',
            'concat:metabox_html'
        ]);



    grunt.registerTask('dev', ['dev:nc_plugin']);


};

/**
 * npm install grunt-concat
 npm install grunt-uglify
 npm install grunt-uglify
 npm install grunt-uglify


 *
 */