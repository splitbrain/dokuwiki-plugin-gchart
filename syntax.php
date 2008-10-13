<?php
/**
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');


/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_gchart extends DokuWiki_Syntax_Plugin {

    /**
     * return some info
     */
    function getInfo(){
        return array(
            'author' => 'Andreas Gohr',
            'email'  => 'andi@splitbrain.org',
            'date'   => '2008-09-15',
            'name'   => 'Amazon Plugin',
            'desc'   => 'Pull bookinfo from Amazon',
            'url'    => 'http://wiki.splitbrain.org/plugin:amazon',
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    function getPType(){
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 160;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
      $this->Lexer->addSpecialPattern('<gchart.*?>\n.*?\n</gchart>',$mode,'plugin_gchart');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){

        $return = array(
                     'type'   => 'p3',
                     'data'   => $data,
                     'width'  => 600,
                     'height' => 100,
                     'align'  => 'center'
                    );

        $lines = explode("\n",$match);
        $conf = array_shift($lines);
        array_pop($lines);

        if(preg_match('/\b(left|center|right)\b/i',$conf,$match)) $return['align'] = $match[1];
        if(preg_match('/\b(\d+)x(\d+)\b/',$conf,$match)){
            $return['width']  = $match[1];
            $return['height'] = $match[2];
        }

        $data = array();
        foreach ( $lines as $line ) {
            //ignore comments (except escaped ones)
            $line = preg_replace('/(?<![&\\\\])#.*$/','',$line);
            $line = str_replace('\\#','#',$line);
            $line = trim($line);
            if(empty($line)) continue;
            $line = preg_split('/(?<!\\\\)=/',$line,2); //split on unescaped equal sign
            $line[0] = str_replace('\\=','=',$line[0]);
            $line[1] = str_replace('\\=','=',$line[1]);
            $data[trim($line[0])] = trim($line[1]);
        }
        arsort($data,SORT_NUMERIC);
        $return['data'] = $data;

        return $return;
    }

    /**
     * Create output
     */
    function render($mode, &$R, $data) {
        if($mode != 'xhtml') return false;

        $val = array_values($data['data']);
        $key = array_keys($data['data']);

        $params = array(
            'cht'  => $data['type'],
            'chf'  => 'bg,s,282C2F',      # background
            'chco' => '1C86EE',           # pie color
            'chs'  => $data['width'].'x'.$data['height'], # size
            'chd'  => 't:'.join(',',$val),
            'chl'  => join('|',$key),
        );

        $url = 'http://chart.apis.google.com/chart?'.buildURLparams($params, '&').'&.png';
        $url = ml($url);

        $align = '';
        if($data['align'] == 'left')  $align=' align="left"';
        if($data['align'] == 'right') $align=' align="right"';

        $R->doc .= '<img src="'.$url.'" class="media'.$data['align'].' alt="" width="'.$data['width'].'" height="'.$data['height'].'"'.$align.' />';
        return true;
    }


}

//Setup VIM: ex: et ts=4 enc=utf-8 :
