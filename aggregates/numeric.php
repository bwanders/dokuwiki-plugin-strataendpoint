<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die('Meh.');

/**
 * The 'numeric' aggregator.
 */
class plugin_strata_aggregate_numeric extends plugin_strata_aggregate {
    function aggregate($values, $hint = null) {
        if($hint == 'strict') {
            $function = function($a) {
                if(is_numeric($a)) return $a+0;
                return $a;
            };
        } else {
            $function = function($b) {
                return $b + 0;
            };
        }

        return array_map($function, $values);
    }

    function getInfo() {
        return array(
            'desc'=>'Converts all numerical values to numbers. This is only necessary when exporting data through an endpoint. Any item that does not have a clear numeric value (i.e. starts with a number) is counted as 0. If the \'strict\' hint is used, values that are not strictly numeric (i.e. contains only a number) are left intact.',
            'hint'=>'\'strict\' to leave non-numeric values intact',
            'tags'=>array('numeric')
        );
    }
}
