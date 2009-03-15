<?php

/**
 * Plugin Columns
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 *             Based on plugin by Michael Arlt <michael.arlt [at] sk-schwanstetten [dot] de>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_columns extends DokuWiki_Syntax_Plugin {

    var $mode;
    var $lexerSyntax;
    var $syntax;

    /**
     * Constructor
     */
    function syntax_plugin_columns() {
        $this->mode = substr(get_class($this), 7);

        $columns = $this->_getColumnsTagName();
        $newColumn = $this->_getNewColumnTagName();
        if ($this->getConf('wrapnewcol') == 1) {
            $newColumnLexer = '<' . $newColumn . '(?:>|\s.*?>)';
            $newColumnHandler = '<' . $newColumn . '(.*?)>';
        }
        else {
            $newColumnLexer = $newColumn;
            $newColumnHandler = $newColumn;
        }
        $enterLexer = '<' . $columns . '(?:>|\s.*?>)';
        $enterHandler = '<' . $columns . '(.*?)>';
        $exit = '<\/' . $columns . '>';
        $lookAhead = '(?=.*?' . $exit . ')';

        $this->lexerSyntax['enter'] = '\n' . $enterLexer . $lookAhead;
        $this->lexerSyntax['newcol'] = '\n' . $newColumnLexer . $lookAhead;
        $this->lexerSyntax['exit'] = '\n' . $exit;

        $this->syntax[DOKU_LEXER_ENTER] = '/' . $enterHandler . '/';
        $this->syntax[DOKU_LEXER_MATCHED] = '/' . $newColumnHandler . '/';
        $this->syntax[DOKU_LEXER_EXIT] = '/' . $exit . '/';
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-03-15',
            'name'   => 'Columns Plugin',
            'desc'   => 'Arrange information in multiple columns.',
            'url'    => 'http://wiki.splitbrain.org/plugin:columns'
        );
    }

    /**
     * What kind of syntax are we?
     */
    function getType() {
        return 'substition';
    }

    function getPType() {
        return 'block';
    }

    /**
     * Where to sort in?
     */
    function getSort() {
        return 65;
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern($this->lexerSyntax['enter'], $mode, $this->mode);
        $this->Lexer->addSpecialPattern($this->lexerSyntax['newcol'], $mode, $this->mode);
        $this->Lexer->addSpecialPattern($this->lexerSyntax['exit'], $mode, $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler) {
        foreach ($this->syntax as $state => $pattern) {
            if (preg_match($pattern, $match, $data) == 1) {
                break;
            }
        }
        switch ($state) {
            case DOKU_LEXER_ENTER:
            case DOKU_LEXER_MATCHED:
                return array($state, preg_split('/\s+/', $data[1], -1, PREG_SPLIT_NO_EMPTY));

            case DOKU_LEXER_EXIT:
                return array($state);
        }
        return false;
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            switch ($data[0]) {
                case DOKU_LEXER_ENTER:
                    $renderer->doc .= $this->_renderTable($data[1]) . DOKU_LF;
                    $renderer->doc .= '<tr>' . $this->_renderTd($data[1]) . DOKU_LF;
                    break;

                case DOKU_LEXER_MATCHED:
                    $renderer->doc .= '</td>' . $this->_renderTd($data[1]) . DOKU_LF;
                    break;

                case DOKU_LEXER_EXIT:
                    $renderer->doc .= '</td></tr></table>' . DOKU_LF;
                    break;
            }
            return true;
        }
        return false;
    }

    /**
     * Returns columns tag
     */
    function _getColumnsTagName() {
        $tag = $this->getConf('kwcolumns');
        if ($tag == '') {
            $tag = $this->getLang('kwcolumns');
        }
        return $tag;
    }

    /**
     * Returns new column tag
     */
    function _getNewColumnTagName() {
        $tag = $this->getConf('kwnewcol');
        if ($tag == '') {
            $tag = $this->getLang('kwnewcol');
        }
        return $tag;
    }

    /**
     *
     */
    function _renderTable($attribute) {
        $width = $this->_getAttribute($attribute, 'table-width');
        if ($width != '') {
            return '<table class="columns-plugin" style="width:' . $width . '">';
        }
        else {
            return '<table class="columns-plugin">';
        }
    }

    /**
     *
     */
    function _renderTd($attribute) {
        $class = $this->_getAttribute($attribute, 'class');
        $textAlign = $this->_getAttribute($attribute, 'text-align');
        if ($textAlign != '') {
            if ($class != '') {
                $class .= ' ';
            }
            $class .= $textAlign;
        }
        if ($class == '') {
            $html = '<td';
        }
        else {
            $html = '<td class="' . $class . '"';
        }
        $style = $this->_getStyle($attribute, 'column-width', 'width');
        $style .= $this->_getStyle($attribute, 'vertical-align');
        if ($style != '') {
            $html .= ' style="' . $style . '"';
        }
        return $html . '>';
    }

    /**
     *
     */
    function _getStyle($attribute, $attributeName, $styleName = '') {
        $result = $this->_getAttribute($attribute, $attributeName);
        if ($result != '') {
            if ($styleName == '') {
                $styleName = $attributeName;
            }
            $result = $styleName . ':' . $result . ';';
        }
        return $result;
    }

    /**
     *
     */
    function _getAttribute($attribute, $name) {
        $result = '';
        if (array_key_exists($name, $attribute)) {
            $result = $attribute[$name];
        }
        return $result;
    }
}
