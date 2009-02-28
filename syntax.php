<?php

/**
 * Columns Plugin
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
    var $block;
    var $nextBlock;
    var $nestingLevel;
    var $nestedBlock;
    var $columns;
    var $align;

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

        $this->lexerSyntax['enter'] = $enterLexer . '\n?' . $lookAhead;
        $this->lexerSyntax['newcol'] = '\n?' . $newColumnLexer . '\n?' . $lookAhead;
        $this->lexerSyntax['exit'] = '\n?' . $exit;

        $this->syntax[DOKU_LEXER_ENTER] = '/' . $enterHandler . '/';
        $this->syntax[DOKU_LEXER_MATCHED] = '/' . $newColumnHandler . '/';
        $this->syntax[DOKU_LEXER_EXIT] = '/' . $exit . '/';

        $this->block = 0;
        $this->nextBlock = 0;
        $this->nestingLevel = 0;
        $this->nestedBlock = array(0);
        $this->columns = array();
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-01-31',
            'name'   => 'Columns Plugin',
            'desc'   => 'Arrange information in multiple columns',
            'url'    => 'http://wiki.splitbrain.org/plugin:columns',
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
                    $this->_renderEnter($renderer, $data[1]);
                    break;

                case DOKU_LEXER_MATCHED:
                    $this->_renderMatched($renderer, $data[1]);
                    break;

                case DOKU_LEXER_EXIT:
                    $renderer->doc .= '<!-- /columns -->';
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
     * Renders table and col tags, starts the table with first column
     */
    function _renderEnter(&$renderer, $attribute) {
        $renderer->doc .= '<!-- columns';
        foreach($attribute as $a) {
            $renderer->doc .= ' ' . $a;
        }
        $renderer->doc .= ' -->';
    }

    /**
     */
    function _renderMatched(&$renderer, $attribute) {
        $renderer->doc .= '<!-- newcolumn';
        foreach($attribute as $a) {
            $renderer->doc .= ' ' . $a;
        }
        $renderer->doc .= ' -->';
    }

    /**
     */
    function _renderTable($width) {
        if ($width == '-') {
            return '<table class="columns-plugin">';
        }
        else {
            return '<table class="columns-plugin" style="width:' . $width . '">';
        }
    }

    /**
     */
    function _renderTd($align, $class = '') {
        if ($align != '') {
            if ($class != '') {
                $class .= ' ';
            }
            $class .= $align;
        }
        if ($class == '') {
            $html = '<td';
        }
        else {
            $html = '<td class="' . $class . '"';
        }
        return $html . '>';
    }
}
