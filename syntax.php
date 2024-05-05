<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Google Chart Plugin: Embeds Charts into DokuWiki
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
class syntax_plugin_gchart extends SyntaxPlugin
{
    private $supported_charts = [
        'qr' => 'qr',
        'pie' => 'p3',
        'pie3d' => 'p3',
        'pie2d' => 'p',
        'line' => 'lc',
        'spark' => 'ls',
        'sparkline' => 'ls',
        'bar' => 'bvs',
        'hbar' => 'bhs',
        'vbar' => 'bvs'
    ];

    /**
     * What kind of syntax are we?
     */
    public function getType()
    {
        return 'substition';
    }

    public function getPType()
    {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    public function getSort()
    {
        return 160;
    }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<gchart.*?>\n.*?\n</gchart>', $mode, 'plugin_gchart');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {

        // prepare default data
        $return = [
            'type' => 'p3',
            'data' => [],
            'width' => 320,
            'height' => 140,
            'align' => 'right',
            'legend' => false,
            'value' => false,
            'title' => '',
            'fg' => ltrim($this->getConf('fg'), '#'),
            'bg' => ltrim($this->getConf('bg'), '#')
        ];

        // prepare input
        $lines = explode("\n", $match);
        $conf = array_shift($lines);
        array_pop($lines);

        // parse adhoc configs
        if (preg_match('/"([^"]+)"/', $conf, $match)) {
            $return['title'] = $match[1];
            $conf = preg_replace('/"([^"]+)"/', '', $conf);
        }
        if (preg_match('/\b(left|center|right)\b/i', $conf, $match)) {
            $return['align'] = strtolower($match[1]);
        }
        if (preg_match('/\b(legend)\b/i', $conf, $match)) {
            $return['legend'] = true;
        }
        if (preg_match('/\b(values?)\b/i', $conf, $match)) {
            $return['value'] = true;
        }
        if (preg_match('/\b(\d+)x(\d+)\b/', $conf, $match)) {
            $return['width'] = $match[1];
            $return['height'] = $match[2];
        }

        $type_regex = '/\b(' . implode('|', array_keys($this->supported_charts)) . ')\b/i';
        if (preg_match($type_regex, $conf, $match)) {
            $return['type'] = $this->supported_charts[strtolower($match[1])];
        }
        if (preg_match_all('/#([0-9a-f]{6}([0-9a-f][0-9a-f])?)\b/i', $conf, $match)) {
            if (isset($match[1][0])) {
                $return['fg'] = $match[1][0];
            }
            if (isset($match[1][1])) {
                $return['bg'] = $match[1][1];
            }
        }

        // parse chart data
        $data = [];
        foreach ($lines as $line) {
            //ignore comments (except escaped ones)
            $line = preg_replace('/(?<![&\\\\])#.*$/', '', $line);
            $line = str_replace('\\#', '#', $line);
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $line = preg_split('/(?<!\\\\)=/', $line, 2); //split on unescaped equal sign
            $line[0] = str_replace('\\=', '=', $line[0]);
            $line[1] = str_replace('\\=', '=', $line[1]);
            $data[trim($line[0])] = trim($line[1]);
        }
        $return['data'] = $data;

        return $return;
    }

    /**
     * Create output
     */
    public function render($mode, Doku_Renderer $R, $data)
    {
        if ($mode != 'xhtml') {
            return false;
        }

        $val = array_map('floatval', array_values($data['data']));
        $max = max(0, ceil(max($val)));
        $min = min(0, floor(min($val)));
        $key = array_keys($data['data']);

        $parameters = [];

        $parameters['cht'] = $data['type'];
        if ($data['bg']) {
            $parameters['chf'] = 'bg,s,' . $data['bg'];
        }
        if ($data['fg']) {
            $parameters['chco'] = implode('|', $this->createColorPalette($data['fg'], count($val)));
        }
        $parameters['chs'] = $data['width'] . 'x' . $data['height']; # size
        $parameters['chd'] = 't:' . implode(',', $val);
        $parameters['chds'] = $min . ',' . $max;
        $parameters['choe'] = 'UTF-8';
        if ($data['title']) {
            $parameters['chtt'] = $data['title'];
        }

        switch ($data['type']) {
            case 'bhs': # horizontal bar
                $parameters['chxt'] = 'y';
                $parameters['chxl'] = '0:|' . implode('|', array_reverse($key));
                $parameters['chbh'] = 'a';
                if ($data['value']) {
                    $parameters['chm'] = 'N*f*,333333,0,-1,11';
                }
                break;
            case 'bvs': # vertical bar
                $parameters['chxt'] = 'y,x';
                $parameters['chxr'] = '0,' . $min . ',' . $max;
                $parameters['chxl'] = '1:|' . implode('|', $key);
                $parameters['chbh'] = 'a';
                if ($data['value']) {
                    $parameters['chm'] = 'N*f*,333333,0,-1,11';
                }
                break;
            case 'lc':  # line graph
                $parameters['chxt'] = 'y,x';
                $parameters['chxr'] = '0,' . floor(min($min, 0)) . ',' . ceil($max);
                $parameters['chxl'] = '1:|' . implode('|', $key);
                if ($data['value']) {
                    $parameters['chm'] = 'N*f*,333333,0,-1,11';
                }
                break;
            case 'ls':  # spark line
                if ($data['value']) {
                    $parameters['chm'] = 'N*f*,333333,0,-1,11';
                }
                break;
            case 'p3':  # pie graphs
            case 'p':
                if ($data['legend']) {
                    $parameters['chdl'] = implode('|', $key);
                    if ($data['value']) {
                        $parameters['chl'] = implode('|', $val);
                    }
                } else {
                    if ($data['value']) {
                        $cnt = count($key);
                        for ($i = 0; $i < $cnt; $i++) {
                            $key[$i] .= ' (' . $val[$i] . ')';
                        }
                    }
                    $parameters['chl'] = implode('|', $key);
                }
                break;
            case 'qr':
                $rawval = array_keys($data['data']);
                if (in_array($rawval[0], ['L', 'M', 'Q', 'H'])) {
                    $parameters['chld'] = array_shift($rawval);
                }
                unset($parameters['chd']);
                unset($parameters['chds']);
                $parameters['chl'] = implode(';', $rawval);
                break;
        }

        $url = $this->getConf('charturl') . '?' . http_build_query($parameters, '', '&') . '&.png';

        $attr = [
            'class' => 'media' . $data['align'],
            'alt' => '',
            'width' => $data['width'],
            'height' => $data['height']
        ];


        if ($data['align'] == 'left') {
            $attr['align'] = 'left';
        } elseif ($data['align'] == 'right') {
            $attr['align'] = 'right';
        }

        $R->doc .= sprintf('<img src="%s" %s />', ml($url), buildAttributes($attr));

        return true;
    }

    /**
     * Google used to creae a palette of colors based on a single given color,
     * quickcharts won't so we do it ourselves. Crudely. Using transparancy.
     * It does not look great but at least each element has a different shade.
     *
     * @param string $rgb original hex color
     * @param int $count number of colors to generate
     * @return array
     */
    protected function createColorPalette($rgb, $count)
    {
        $palette = [];
        $inc = floor(255 / $count);
        for ($i = 0; $i < $count; $i++) {
            $palette[] = $rgb . dechex(255 - $i * $inc);
        }
        return $palette;
    }
}
