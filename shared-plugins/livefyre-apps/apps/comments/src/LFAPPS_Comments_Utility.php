<?php
/*
 * Untility class that allows certain values to be set in the option table
 * based on a GET param.
 */
class LFAPPS_Comments_Utility {
    
    function __construct( $lf_core ) {

        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
    }
   
    function update_import_status( $status ) {

        return $this->ext->update_option( "livefyre_import_status", $status );
    }
}
