<?php
/**
 * OrphansWanted Plugin: Display Orphans, Wanteds and Valid link information
 * version 2.4 2008-11-13  
 * syntax ~~ORPHANSWANTED:<choice>[!<exclude list>]~~  <choice> :: orphans | wanted | valid | all
 * [!<exclude list>] :: optional.  prefix each with ! e.g., !wiki!comments:currentyear
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     <dae@douglasedmunds.com>
 * @author     Andy Webber <dokuwiki at andywebber dot com>
 * @author     Federico Ariel Castagnini
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

require_once(DOKU_INC.'inc/search.php');


//-------------------------------------

//mod dae
function orph_handle_link(&$data, $link) {
    $item = &$data["$link"];
	if(isset($item)) {
		// This item already has a member in the array
		// Note that the file search found it
		$item['links'] = 1 + $item['links'];   // count the link
		// echo "      <!-- added count? " . $item['links'] . " --> \n";
    } else {
		// Create a new entry
		$data["$link"]=array('exists' => false,  // Only found a link, not the file
        		'links' => 1);
		// echo "      <!-- added link to list --> \n";
	}
}

function orph_fileNS($file) {
    $path_probs   = array("!^/!", "!/$!", "!/!", "!::!");
    $replacements = array(  "",     "",    ":",    ":");
    $x = strrpos($file, '/');
	switch($x) {
	case 0:
	    $result = "";
		break;
	default:
	    // replace all the / with : after dropping the file off the filename
	  	$result = preg_replace($path_probs, $replacements, substr($file, 1, $x -1 ));
	}
	//echo "<!-- $x NS= $result file= $file -->\n";
	return $result;
}

function orph_Check_InternalLinks(&$data,$base,$file,$type,$lvl,$opts) {
    define("LINK_PATTERN", "%\[\[([^\]|#]*)(#[^\]|]*)?\|?([^\]]*)]]%");

    if(preg_match("/.*\.txt$/", $file)) {
		global $conf;
		// echo "  <!-- checking file: $file -->\n";
		$body = @file_get_contents($conf['datadir'] . $file);

        // ignores entries in <nowiki>, %%, <code> and emails with @
		foreach ( array("/<nowiki>[\W\w]*<\/nowiki>/",
                        "/%%.*%%/",
                        "/<code>[\W\w]*<\/code>/" ,
                        "/\[\[\\ *\\\\.*\]\]/" , //windows shares
                        "/\[\[\ *[a-zA-Z0-9._-]+@[a-zA-Z0-9-]+\..*\ *\]\]/" //email address with tags
                        //"/\[\[\ *[a-zA-Z0-9._-]+@[a-zA-Z0-9-]+\.[a-zA-Z.]+\ *\]\]/" //email address
                        //source http://www.sitepoint.com/article/regular-expressions-php
                         )  as $ignored){
             $body = preg_replace($ignored, "",  $body);
        }

        $links = array();
		preg_match_all(LINK_PATTERN, $body, $links);
        foreach($links[1] as $link) {
		    if( (0 < strlen(ltrim($link)))
			   and ( $link[0] <> "/" )
			   and (!preg_match("/^\ *(https?|mailto|ftp|file):/", $link))  //mod 7 june 06: allow spaces before http, etc.
			   and (!preg_match("/^(.*)>/", $link))
			   and (!strpos("@", $link)) ) {
				// Try fixing the link...
				//$link = preg_replace("![ ]!", "_", strtolower($link));
				// need to fix the namespace?
				if( $link[0] == ":" ) {       // forced root namespace
				   $link = substr($link, 1);
				   //echo "\t\t<!--  !! (2) $link -->\n";
				} else {
					if($link[0] == ".") { // forced relative namespace
//					   $link = preg_replace("!::!", ":",orph_fileNS($file) . ":" . substr($link, 1));
                                           $link = resolve_id(orph_fileNS($file),$link);
					   //echo "\t\t<!--  !! (2) $link -->\n";
					} else if(strpos($link,':') === false) {
					   $link = preg_replace("!::!", ":",orph_fileNS($file) . ":" . $link);
					   //echo "\t\t<!--  !! (3) $link -->\n";
					}
				} // namespace fix

        if( $link[strlen($link)-1] == ":" ) {
            $link .= $conf["start"];
        }

				// looks like an ID?
				$link = cleanID($link);
				if(((strlen(ltrim($link)) > 0)           // there IS an id?
				   and !auth_quickaclcheck($link) < AUTH_READ)) {    // should be visible to user
//				   and (!preg_match("/^(http|mailto):/", $link))  // URL
//				   and (!preg_match("/^(.*)>/", $link))) {        // interwiki
					//check ACL
					//echo "      <!-- adding $link -->\n";
					//dae mod
					//orph_handle_link(&$data, $link);
					orph_handle_link($data, $link);				}
			} // link is not empty?
		} // end of foreach link
	}
}


function orph_report_table($data, $page_exists, $has_links, $params_array) {
    global $conf;
    if ($page_exists && $conf['useheading']) {
      $show_heading = true;
    }
    //take off $params_array[0];
    $exclude_array = array_slice($params_array,1);

    $count = 1;
    $output = '';
    // for valid html - need to close the <p> that is feed before this
    $output .= '</p>';
	$output .= "<table class='inline'><tr><th> # </th><th> ID </th>" .
	 	($show_heading ? "<th>Title</th>" : "" ) . "<th>Links</th></tr>\n";

        arsort($data);
	foreach($data as $id=>$item) {

		if(($item["exists"] == $page_exists) and (($item["links"] <> 0)== $has_links)) {

			// $id is a string, looks like this: page, namespace:page, or namespace:<subspaces>:page
			$match_array = explode(":", $id);
			//remove last item in array, the page identifier
			$match_array = array_slice($match_array, 0, -1);
			//put it back together
			$page_namespace = implode (":", $match_array);
			//add a trailing :
			$page_namespace = $page_namespace . ':';

			//set it to show, unless blocked by exclusion list
			$show_it = true;
			foreach ($exclude_array as $exclude_item){
				//add a trailing : to each $item too
				$exclude_item = $exclude_item . ":";
				// need === to avoid boolean false
				// strpos(haystack, needle)
				// if exclusion is beginning of page's namespace , block it
				if (strpos($page_namespace, $exclude_item) === 0){
				   //there is a match, so block it
				   $show_it = false;
				}
			}

			if ($show_it) {
                           $output .=  "<tr><td>$count</td><td><a href=\"". wl($id) . "\" class=\"" . ($page_exists ? "wikilink1" : "wikilink2") . "\"  onclick=\"return svchk()\" onkeypress=\"return svchk()\">" .
 					$id ."</a></td>" .
 					($show_heading ? "<td>" . hsc(p_get_first_heading($id)) ."</td>" : "" ) .
 					"<td>" . $item["links"] .
					($has_links ? "&nbsp;:&nbsp;<a href=\"". wl($id,"do=backlink") . "\" class=\"wikilink1\">Show&nbsp;backlinks</a>" : "" ) .
                                        "</td></tr>\n";

				$count++;
			}

		}
	}
	//close the html table
	$output .=  "</table>\n";
	//for valid html = need to reopen a <p>
	$output .= "<p>";
        return $output;
}


function orph_search_wanted(&$data,$base,$file,$type,$lvl,$opts) {

	if($type == 'd'){
		return true; // recurse all directories, but we don't store namespaces
	}

    if(!preg_match("/.*\.txt$/", $file)) {  // Ignore everything but TXT
		return true;
	}

	// search the body of the file for links
	// dae mod
  //	orph_Check_InternalLinks(&$data,$base,$file,$type,$lvl,$opts);
	orph_Check_InternalLinks($data,$base,$file,$type,$lvl,$opts);

	// get id of this file
	$id = pathID($file);

	//check ACL
	if(auth_quickaclcheck($id) < AUTH_READ) {
		return false;
	}

	// try to avoid making duplicate entries for forms and pages
	$item = &$data["$id"];
	if(isset($item)) {
		// This item already has a member in the array
		// Note that the file search found it
		$item['exists'] = true;
    } else {
		// Create a new entry
		$data["$id"]=array('exists' => true,
				 'links' => 0);
	}
	return true;
}

// --------------------

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_orphanswanted extends DokuWiki_Syntax_Plugin {
    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Doug Edmunds, Andy Webber, Federico Ariel Castagnini',
            'email'  => 'dokuwiki at andywebber dot com',
            'date'   => @file_get_contents(DOKU_PLUGIN . 'VERSION'),
            'name'   => 'OrphansWanted Plugin',
            'desc'   => 'Find orphan pages and wanted pages .
            syntax ~~ORPHANSWANTED:<choice>[!<excluded namespaces>]~~ .
            <choice> :: orphans|wanted|valid|all .
            <excluded namespaces> are optional, start each namespace with !' ,
            'url'    => 'http://dokuwiki.org/plugin:orphanswanted',
        );
    }

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
        return 990;     //was 990
    }


    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('~~ORPHANSWANTED:[0-9a-zA-Z_:!]+~~',$mode,'plugin_orphanswanted');
    }

    /**
     * Handle the match
     */

    function handle($match, $state, $pos, &$handler){
        $match_array = array();
        $match = substr($match,16,-2); //strip ~~ORPHANSWANTED: from start and ~~ from end
        // Wolfgang 2007-08-29 suggests commenting out the next line
        $match = strtolower($match);
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
        if($format == 'xhtml'){

			// user needs to add ~~NOCACHE~~ manually to page, to assure ACL rules are followed
			// coding here is too late, it doesn't get parsed
			// $renderer->doc .= "~~NOCACHE~~";

            // $data is an array
            // $data[1]..[x] are excluded namespaces, $data[0] is the report type
            //handle choices
            switch ($data[0]){
                case 'orphans':
                    $renderer->doc .= $this->orphan_pages($data);
                    break;
                case 'wanted':
                    $renderer->doc .= $this->wanted_pages($data);
                    break;
                case 'valid':
                    $renderer->doc .= $this->valid_pages($data);
                    break;
                case 'all':
                    $renderer->doc .= $this->all_pages($data);
                    break;
                default:
                    $renderer->doc .= "ORPHANSWANTED syntax error";
                   // $renderer->doc .= "syntax ~~ORPHANSWANTED:<choice>~~<optional_excluded>  <choice> :: orphans|wanted|valid|all  Ex: ~~ORPHANSWANTED:valid~~";
            }

             return true;
        }
        return false;
    }


//    three choices
//    $params_array used to extract excluded namespaces for report
//    orphans =  orph_report_table($data, true, false, $params_array);
//    wanted =  orph_report_table($data, false, true), $params_array;
//    valid  =  orph_report_table($data, true, true, $params_array);


    function orphan_pages($params_array) {
      global $conf;
      $result = '';
      $data = array();
      search($data,$conf['datadir'],'orph_search_wanted',array('ns' => $ns));
      $result .=  orph_report_table($data, true, false,$params_array);

      return $result;
    }

    function wanted_pages($params_array) {
      global $conf;
      $result = '';
      $data = array();
      search($data,$conf['datadir'],'orph_search_wanted',array('ns' => $ns));
      $result .= orph_report_table($data, false, true,$params_array);

      return $result;
    }

    function valid_pages($params_array) {
      global $conf;
      $result = '';
      $data = array();
      search($data,$conf['datadir'],'orph_search_wanted',array('ns' => $ns));
      $result .= orph_report_table($data, true, true, $params_array);

      return $result;
    }

    function all_pages($params_array) {
      global $conf;
      $result = '';
      $data = array();
      search($data,$conf['datadir'],'orph_search_wanted',array('ns' => $ns));

      $result .= "</p><p>Orphans</p><p>";
      $result .= orph_report_table($data, true, false,$params_array);
      $result .= "</p><p>Wanted</p><p>";
      $result .= orph_report_table($data, false, true,$params_array);
      $result .= "</p><p>Valid</p><p>";
      $result .= orph_report_table($data, true, true, $params_array);


      return $result;
    }

}

?>
