<?php

/************************************************************************
 * Class name : duoconfig                                               *
 * Description: This class reads the Duo Security configuration from    *
 * /var/www/config/cilogon.ini and stores the values in a 'param'       *
 * array.                                                               *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('duoconfig.php');                                    *
 *    $duoconfig = new duoconfig();                                     *
 *    $ikey = $duoconfig->param['ikey'];                                *
 ************************************************************************/

class duoconfig {

    const cilogon_ini_file = '/var/www/config/cilogon.ini';

    public $param = array();

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new duoconfig object.                              *
     * Default constructor. This method 
     ********************************************************************/
    function __construct() {
        $duoparamnames = array('host','ikey','skey','akey','name');

        $ini_array = @parse_ini_file(self::cilogon_ini_file);
        if (is_array($ini_array)) {
            foreach ($duoparamnames as $val) {
                if (array_key_exists('duo.'.$val,$ini_array)) {
                    $this->param[$val] = $ini_array['duo.'.$val];
                }
            }
        }
    }

}

?>
