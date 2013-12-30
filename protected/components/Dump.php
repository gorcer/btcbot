<?php
class Dump extends CVarDumper {
    /**
     * Displays a variable.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed variable to be dumped
     * @param boolean 
     * @param integer maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean whether the result should be syntax-highlighted
     */
    public static function d($var, $output=false, $depth=10,$highlight=true){
        
    	if ($output)
    		return self::dumpAsString($var,$depth,$highlight);
    	else
    		echo self::dumpAsString($var,$depth,$highlight).'<br/>';
    }
}