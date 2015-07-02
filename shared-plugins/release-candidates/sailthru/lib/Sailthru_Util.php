<?php

class Sailthru_Util {
    /**
     * Returns an MD5 hash of the signature string for an API call.
     *
     * This hash should be passed as the 'sig' value with any API request.
     *
     * @param array $params
     * @param string $secret
     * @return string
     */
    public static function getSignatureHash($params, $secret) {
        return md5(self::getSignatureString($params, $secret));
    }


    /**
     * Returns the unhashed signature string (secret + sorted list of param values) for an API call.
     *
     * Note that the SORT_STRING option sorts case-insensitive.
     *
     * @param array $params
     * @param string $secret
     * @return string
     */
    public static function getSignatureString($params, $secret) {
        $values = array();
        self::extractParamValues($params, $values);
        sort($values, SORT_STRING);
        $string = $secret . implode('', $values);
        return $string;
    }


    /**
     * Extracts the values of a set of parameters, recursing into nested assoc arrays.
     *
     * @param array $params
     * @param array $values
     */
    public static function extractParamValues($params, &$values) {
        foreach ($params as $k => $v) {
            if (is_array($v) || is_object($v)) {
                self::extractParamValues($v, $values);
            } else {
                if (is_bool($v))  {
                    //if a value is set as false, invalid hash will generate
                    //https://github.com/sailthru/sailthru-php5-client/issues/4
                    $v = intval($v);
                }
                $values[] = $v;
            }
        }
    }
}