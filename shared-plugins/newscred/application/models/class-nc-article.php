<?php
/**
 * @package nc-plugin
 * @author  Md Imranur Rahman <imranur@newscred.com>
 *
 *
 *  Copyright 2012 NewsCred, Inc.  (email : sales@newscred.com)
 *
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 2, as
 *  published by the Free Software Foundation.
 *
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/**
 *
 * NC_Article Class
 */

class NC_Article {

    /**
     * _instance class variable
     *
     * Class instance
     *
     * @var null | object
     **/
    private static $_instance = NULL;


    /**
     * get_instance function
     *
     * Return singleton instance
     *
     * @return object
     **/
    static function get_instance () {
        if ( self::$_instance === NULL ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     *  Constructs the controller and assigns protected variables to be
     * @param array $params
     */
    public function __construct ( array $params = array() ) {

    }

    /**
     * search the articles list for metabax
     * get_metabox_articles
     * @static
     * @return array
     */
    public static function get_metabox_articles () {


        $pagesize = 10;

        $page = absint($_POST[ 'page' ]);
        $offset = ( $page - 1 ) * $pagesize;

        $query = ( isset($_POST['query']) ) ? strip_tags( $_POST['query'] ) : "";


        $fields = array(
            'article.guid',
            'article.description',
            'article.title',
            'article.published_at',
            'article.source.name',
            'article.tracking_pixel',
            'article.topic.name',
            'article.categories.dashed_name',
            'article.categories.name',
            'article.author.name',
            'article.image.guid',
            'article.image.caption',
            'article.image.description',
            'article.image.height',
            'article.image.width',
            'article.image.published_at',
            'article.image.source.name',
            'article.image.urls.large'
        );

        $options = array(
            'fields'    => $fields,
            'pagesize'  => $pagesize,
            'offset'    => $offset
        );

        $sort = ( isset($_POST['sort']) ) ? trim(strip_tags($_POST['sort'])) : null;

        if ( $sort  && ( $sort == "date" || $sort == "relevance" ) ) {
            $options[ 'sort' ] = sanitize_text_field( $sort );
        }

        $sources = ( isset($_POST['sources']) ) ? $_POST['sources'] : null;

        if ( $sources ) {
            $options[ 'sources' ] =  $sources ;
        }

        $topics = ( isset($_POST['topics']) ) ? $_POST['topics'] : null;

        if ( $topics ) {
            $options[ 'topics' ] =  $topics ;
        }


        if ( get_option('nc_article_has_images') )
            $options[ 'has_images' ] = "true";

        if ( get_option('nc_article_fulltext') )
            $options[ 'fulltext' ] = "true";


        $articles = array();

        try {
            $articles = NC_Plugin_Article::search( NC_ACCESS_KEY, $query, $options );

        } catch ( NC_Plugin_Exception $e ) {
            return $articles;

        }

        return $articles;
    }

}