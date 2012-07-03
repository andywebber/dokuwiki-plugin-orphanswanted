<?php
/**
 * OrphansWanted Plugin: Display Orphans, Wanteds and Valid link information
 * 
 * syntax ~~ORPHANSWANTED:<choice>[!<exclude list>]~~  <choice> :: orphans | wanted | valid | all
 * [!<exclude list>] :: optional.  prefix each with ! e.g., !wiki!comments:currentyear
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     <dae@douglasedmunds.com>
 * @author     Andy Webber <dokuwiki at andywebber dot com>
 * @author     Federico Ariel Castagnini
 * @author     Cyrille37 <cyrille37@gmail.com>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_orphanswanted extends DokuWiki_Syntax_Plugin {
 
    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }
 
    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'normal';
    }
 
    /**
     * Where to sort in?
     */
    function getSort(){
        return 990;     // was 990
    }
 
 
    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~ORPHANSWANTED:[\w:!]+~~', $mode, 'plugin_orphanswanted');
    }
 
    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        $match_array = array();
        $match = substr($match,16,-2); //strip ~~ORPHANSWANTED: from start and ~~ from end
        
        // Wolfgang 2007-08-29 suggests commenting out the next line
        // $match = strtolower($match);
        //create array, using ! as separator
        $match_array = explode("!", $match);
        // $match_array[0] will be orphan, wanted, valid, all, or syntax error
        // if there are excluded namespaces, they will be in $match_array[1] .. [x]
        // this return value appears in render() as the $data param there
        
        return $match_array;
    }
 
    /**
     * Create output
     */
    function render($format, &$renderer, $data) {
        global $INFO, $conf;
        $helper = plugin_load('helper','orphanswanted');
        
        if($format == 'xhtml') {
            // prevent caching to ensure content is always fresh
            $renderer->info['cache'] = false;

            // $data is an array
            // $data[1]..[x] are excluded namespaces, $data[0] is the report type
            //handle choices
            
            switch ($data[0]) {
                case 'orphans':
                    $renderer->doc .= $helper->orphan_pages($data);
                    break;
                case 'wanted':
                    $renderer->doc .= $helper->wanted_pages($data);
                    break;
                case 'valid':
                    $renderer->doc .= $helper->valid_pages($data);
                    break;
                case 'all':
                    $renderer->doc .= $helper->all_pages($data);
                    break;
                default:
                    $renderer->doc .= "ORPHANSWANTED syntax error";
                   // $renderer->doc .= "syntax ~~ORPHANSWANTED:<choice>~~<optional_excluded>  <choice> :: orphans|wanted|valid|all  Ex: ~~ORPHANSWANTED:valid~~";
            }
            return true;
        }
        
        return false;
    } 
}
 
//Setup VIM: ex: et ts=4 enc=utf-8 :
