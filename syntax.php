<?php
/**
 * Strata Endpoint, syntax plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
if(!defined('DOKU_INC')) die('Meh.');
 
/**
 * Select syntax for basic query handling.
 */
class syntax_plugin_strataendpoint extends DokuWiki_Syntax_Plugin {
    function __construct() {
        $this->helper =& plugin_load('helper', 'stratabasic');
        $this->types =& plugin_load('helper','stratastorage_types');
        $this->triples =& plugin_load('helper','stratastorage_triples',false);
        $this->triples->initialize();
    }

    function getType() {
        return 'substition';
    }

    function getPType() {
        return 'block';
    }

    function getSort() {
        return 450;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<endpoint>\n.*?\n</endpoint>',$mode, 'plugin_strataendpoint');
    }

    function handle($match, $state, $pos, &$handler) {
        $result = array();

        // split into lines and remove header and footer
        $lines = explode("\n",$match);
        $header = array_shift($lines);
        $footer = array_pop($lines);

        // determine tree
        $tree = $this->helper->constructTree($lines,'endpoint');

        // set default query type
        $queryType = null;

        // handle all line options
        foreach($this->helper->extractText($tree) as $setting) {
            if(preg_match('/type\s*:\s*(relations|resources)/',$setting,$capture)) {
                // the query type option
                $queryType = $capture[1];

            } else {
                // an unknown option
                msg('Unknown endpoint setting \'<code>'.hsc($setting).'</code>\'',-1);
                return array();
            }
        }

        if($queryType == null) {
            msg('The endpoint requires a \'<code>type</code>\' setting.', -1);
            return array();
        }

        // access control allow origin list
        $allowedOrigins = array();

        // handle all origins
        foreach($this->helper->extractGroups($tree,'allow-origin') as $origins) {
            // FIXME: check for '*' only, or all having 'scheme://host[:port]'
            $allowedOrigins = array_merge($allowedOrigins, array_map('trim',$this->helper->extractText($origins)));

            // check for stray groups
            if(count($origins['cs'])) {
                msg('The <code>allow-origins</code> group should not contain groups.',-1);
                return array();
            }
        }

        // set query or free query
        $queryGroups = $this->helper->extractGroups($tree,'query');
        if(count($queryGroups) == 0) {
            $query = null;
        } else {
            $query = $this->_parseQuery($queryGroups[0]);
            if($query == array()) {
                return array();
            }
        }


        return array($queryType, $allowedOrigins, $query);
    }

    function _parseQuery(&$tree) {
        $query = array();

        // parse long fields, if available
        $query['fields'] = $this->helper->getFields($tree, $typemap);

        // check no data
        if(count($query['fields']) == 0) {
            msg($this->helper->getLang('error_query_noselect'),-1);
            return array();
        }

        // determine the variables to project
        $projection = array();
        foreach($query['fields'] as $f) $projection[] = $f['variable'];
        $projection = array_unique($projection);

        // parse the query itself
        list($query['query'], $variables) = $this->helper->constructQuery($tree, $typemap, $projection);
        if(!$query['query']) return array();

        // check projected variables and load types
        foreach($query['fields'] as $i=>$f) {
            $var = $f['variable'];
            if(!in_array($var, $variables)) {
                msg(sprintf($this->helper->getLang('error_query_unknownselect'),utf8_tohtml(hsc($var))),-1);
                return array();
            }

            if(empty($f['type'])) {
                if(!empty($typemap[$var])) {
                    $query['fields'][$i] = array_merge($query['fields'][$i],$typemap[$var]);
                } else {
                    list($type, $hint) = $this->types->getDefaultType();
                    $query['fields'][$i]['type'] = $type;
                    $query['fields'][$i]['hint'] = $hint;
                }
            }
        }

        return $query;
    }

    function render($mode, &$R, $data) {
        global $ID, $conf;

        if($data == array()) {
            return false;
        }

        list($queryType, $allowedOrigins, $query) = $data;

        if($mode == 'xhtml') {
            $R->p_open();
            $R->strong_open();
            $R->doc .= 'Endpoint (';

            $link['target'] = $conf['target']['extern'];
            $link['class']  = 'urlextern';
            $link['url']    = exportlink($ID,'strataendpoint');
 
            $link['name']   = 'JSON';
            $link['title']  = $R->_xmlEntities($link['url']);
            if($conf['relnofollow']) $link['more'] .= ' rel="nofollow"';

            //output formatted
            $R->doc .= $R->_formatLink($link);

            $R->doc .= ')';
            $R->strong_close();
            $R->p_close();

            $R->p_open();
            $R->strong_open();
            $R->doc .= 'Query: ';
            $R->strong_close();
            $R->emphasis_open();
            $R->doc .= (!$query!=null?'free - query will be read from POST body':'fixed - query determined by endpoint');
            $R->emphasis_close();
            $R->p_close();

            $R->p_open();
            $R->strong_open();
            $R->doc .= 'Query type: ';
            $R->strong_close();
            $R->emphasis_open();
            $R->doc .= $queryType;
            $R->emphasis_close();
            $R->p_close();


            $R->p_open();
            $R->strong_open();
            $R->doc .= 'Allowed origins: ';
            $R->strong_close();
            $R->emphasis_open();
            $R->doc .= $R->_xmlEntities(implode(', ', $allowedOrigins));
            $R->emphasis_close();
            $R->p_close();

            if($query != null) {
                $R->p_open();
                $R->strong_open();
                $R->doc .= 'Query preview: ';
                $R->strong_close();
                $R->p_close();
                $preview =& plugin_load('syntax','stratabasic_select');
                $preview->render($mode, $R, $query);
            }

            return true;
        } elseif($mode == 'strataendpoint') {
            $R->headers['Access-Control-Allow-Origin'] = implode(' ', $allowedOrigins);;

            if($query == null) {
                // determine tree
                $tree = $this->helper->constructTree(file('php://input'),'endpoint');
                $query = $this->_parseQuery($tree);
            }

            foreach($query['fields'] as $meta) {
                $fields[] = array(
                    'variable'=>$meta['variable'],
                    'caption'=>$meta['caption'],
                    'type'=>$meta['type'],
                    'hint'=>$meta['hint'],
                    'aggregate'=>$this->types->loadAggregate($meta['aggregate']),
                    'aggregateHint'=>$meta['aggregateHint']
                );
            }
            
            // execute the query
            if($queryType == 'relations') {
                $result = $this->triples->queryRelations($query['query']);
            } else {
                $result = $this->triples->queryResources($query['query']);
            }
            
            if($result == false) {
                // FIXME
                global $MSG;
                $R->doc .= 'Ohoh!';
                foreach($MSG as $msg) {
                    $R->doc .= $msg['lvl']. ': '.$msg['msg']."\n";
                }
                return false;
            }

            if($queryType == 'relations') {
                $reply = array(
                    'head'=>array(),
                    'body'=>array()
                );

                foreach($result as $row) {
                    $item = array();
                    foreach($fields as $f) {
                        $item[$f['variable']] = $f['aggregate']->aggregate($row[$f['variable']],$f['aggregateHint']);
                    }
                    $reply['body'][] = $item;
                }
            } else {
                $reply = array(
                    'body'=>array()
                );
                foreach($result as $subject=>$resource) {
                    $reply['body'][$subject] = $resource;
                }
            }
            $result->closeCursor();
            $R->doc .= json_encode($reply);
            
            return true;
        }

        return false;
    }
}