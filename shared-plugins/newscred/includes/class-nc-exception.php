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
 * Custom nc plugin  exceptions class
 */

abstract class NC_Exception extends Exception {
    protected $_template;
    protected $_isAdmin;


    const EXCEPTION_API_RESPONSE_GET_FAILED = 'Failed to get API response for the request: ';
    const EXCEPTION_XML_PARSE_ERROR = 'Error parsing the XML response for the request: ';
    const EXCEPTION_JSON_PARSE_ERROR = 'Error parsing the JSON response for the request: ';
    const EXCEPTION_NO_ACCESS_KEY = 'No access key provided.';
    const EXCEPTION_API_ERROR = 'NewsCred API Error.';


    const EXCEPTION_AUTHENTICATION_FAILED = 'Authentication Failed. Please check the access key.';
    const EXCEPTION_INVALID_GUID = 'Invalid GUID provided.';

    const EXCEPTION_PLATFORM_RETURNED_ERROR = 'NewsCred Platform returned Internal Server Error for this request: ';

    public function __construct ( $extra = 'No additional information available.' ) {
        parent::__construct();
        $this->_template = new NC_Template();
        $this->_template->extra = $extra;

    }


    public function display () {
        $this->_template->assign( 'friendlyError', 'A problem has occurred' );
        $this->_template->assign( 'friendlyDescription', '' );

        if ( method_exists( $this, 'fire' ) )
            $this->fire();

        $this->_template->assign( 'line', $this->getLine() );
        $this->_template->assign( 'file', $this->getFile() );
        $this->_template->assign( 'message', $this->getMessage() );
        $this->_template->assign( 'code', $this->getCode() );
        $this->_template->assign( 'traceString', $this->getTraceAsString() );
        $this->_template->assign( 'trace', $this->getTrace() );
        $this->_template->assign( 'isAdmin', $this->_isAdmin );


    }

}


class NC_E_NOTEMPLATE extends NC_Exception {
    public function fire () {

        echo "Template not found";
        exit;

    }
}
