<?php
/**
 * DokuWiki Plugin Strata Endpoint (JSON Renderer Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Brend Wanders <b.wanders@utwente.nl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die('Meh.');

require_once DOKU_INC . 'inc/parser/renderer.php';

class renderer_plugin_strataendpoint extends Doku_Renderer {
    var $headers = array();
    var $doc = '';

    function document_start() {
        $this->headers = array(
            'Content-Type' => 'text/plain',
            'Access-Control-Allow-Credentials' => 'true'
        );
        $this->info['cache'] = false;
    }

    function document_end() {
        global $ID;
        p_set_metadata($ID, array('format' => array($this->getFormat() => $this->headers) ));
    }

    function getFormat(){
        return 'strataendpoint';
    }
}
