<?php
//Disallow direct access to this file
if(!defined('LFAPPS__PLUGIN_PATH')) 
    die('Bye');

if ( ! class_exists( 'LFAPPS_View' ) ) {
    class LFAPPS_View {
        
        /**
         * Render a view 
         * @param string $view name of the view
         * @param array  $data list of data that the view can use
         * @param string $app optional app name (render view from app folder)
         */
        public static function render($view, $data=array(), $app=null) {
            if(is_array($data)) {
                foreach($data as $dname=>$dval) {
                    $$dname = $dval;
                }
            }
            if(is_null($app)) {
                $view_path = LFAPPS__PLUGIN_PATH . 'views/';        
                if(file_exists($view_path . 'header.php')) {
                    include $view_path . 'header.php';
                }
                if(file_exists($view_path . $view . '.php')) {
                    include $view_path . $view . '.php';
                }                
                if(file_exists($view_path . 'footer.php')) {
                    include $view_path . 'footer.php';
                }
            } else {
                $parent_view_path = LFAPPS__PLUGIN_PATH . 'views/';       
                $view_path = LFAPPS__PLUGIN_PATH . 'apps/'.$app.'/views/';   
                if(file_exists($parent_view_path . 'header.php')) {
                    include $parent_view_path . 'header.php';
                }
                if(file_exists($view_path . 'header.php')) {
                    include $view_path . 'header.php';
                }
                if(file_exists($view_path . $view . '.php')) {
                    include $view_path . $view . '.php';
                }                
                if(file_exists($view_path . 'footer.php')) {
                    include $view_path . 'footer.php';
                }
            }
        }
        
        /**
         * Render a partial view (does not include header+footer)
         * @param string $view name of the view
         * @param array  $data list of data that the view can use
         * @param string $app optional app name (render view from app folder)
         * @param boolean $return TRUE to return the content or FALSE to print in buffer
         */
        public static function render_partial($view, $data=array(), $app=null, $return=false) {
            if(is_array($data)) {
                foreach($data as $dname=>$dval) {
                    $$dname = $dval;
                }
            }
            
            $view_path = '';
            if(is_null($app)) {
                $view_path = LFAPPS__PLUGIN_PATH . 'views/';                
            } else {
                $view_path = LFAPPS__PLUGIN_PATH . 'apps/'.$app.'/views/';                
            }
            
            if(file_exists($view_path . $view . '.php')) {
                if($return) {
                    ob_start();
                    include $view_path . $view . '.php';
                    return ob_get_clean();
                } else {
                    include $view_path . $view . '.php';
                }
            }
        }
    }
}