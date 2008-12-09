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
            'date'   => '2008-10-13',
            'name'   => 'Google Chart Plugin',
            'desc'   => 'Create simple charts using the Google Chart API',
            'url'    => 'http://wiki.splitbrain.org/plugin:gchart',
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

        // prepare default data
        $return = array(
                     'type'   => 'p3',
                     'data'   => $data,
                     'width'  => 320,
                     'height' => 140,
                     'align'  => 'right',
                     'fg'     => ltrim($this->getConf('fg'),'#'),
                     'bg'     => ltrim($this->getConf('bg'),'#'),
                    );

        // prepare input
        $lines = explode("\n",$match);
        $conf = array_shift($lines);
        array_pop($lines);

        // parse adhoc configs
        if(preg_match('/\b(left|center|right)\b/i',$conf,$match)) $return['align'] = $match[1];
        if(preg_match('/\b(\d+)x(\d+)\b/',$conf,$match)){
            $return['width']  = $match[1];
            $return['height'] = $match[2];
        }
        if(preg_match('/\b(pie(3d)?|pie2d|line|spark(line)?|h?bar|vbar)\b/i',$conf,$match)){
            $return['type'] = $this->_charttype($match[1]);
        }
        if(preg_match_all('/#([0-9a-f]{6}([0-9a-f][0-9a-f])?)\b/i',$conf,$match)){
            if(isset($match[1][0])) $return['fg'] = $match[1][0];
            if(isset($match[1][1])) $return['bg'] = $match[1][1];
        }

        // parse chart data
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
        $return['data'] = $data;

        return $return;
    }

    /**
     * Create output
     */
    function render($mode, &$R, $data) {
        if($mode != 'xhtml') return false;

        $val = array_values($data['data']);
        $max = max($val);
        $min = min($val);
        $min = min($min,0);
        $val = array_map('rawurlencode',$val);
        $key = array_keys($data['data']);
        $key = array_map('rawurlencode',$key);

        $url  = 'http://chart.apis.google.com/chart?';
        $url .= '&cht='.$data['type'];
        if($data['bg']) $url .= '&chf=bg,s,'.$data['bg'];
        if($data['fg']) $url .= '&chco='.$data['fg'];
        $url .= '&chs='.$data['width'].'x'.$data['height']; # size
        $url .= '&chd=t:'.join(',',$val);
        $url .= '&chl='.join('|',$key);
        $url .= '&chds='.$min.','.$max;
        $url .= '&.png';

        $url = ml($url);

        $align = '';
        if($data['align'] == 'left')  $align=' align="left"';
        if($data['align'] == 'right') $align=' align="right"';

        $R->doc .= '<img src="'.$url.'" class="media'.$data['align'].'" alt="" width="'.$data['width'].'" height="'.$data['height'].'"'.$align.' />';
        return true;
    }

    /**
     * Map our syntax to Google types
     */
    function _charttype($type){
        $type = strtolower($type);
        switch($type){
            case 'pie2d':
                return 'p';
            case 'line':
                return 'lc';
            case 'spark':
            case 'sparkline':
                return 'ls';
            case 'hbar':
                return 'bhs';
            case 'bar':
            case 'vbar':
                return 'bvs';
        }
        return 'p3';

    }

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
