<?php

/**
 * Plugin Columns
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_columns extends DokuWiki_Action_Plugin {

    var $block;

    /**
     * Constructor
     */
    function action_plugin_columns() {
        $this->block = array();
    }

    /**
     * Return some info
     */
    function getInfo() {
        return array(
            'author' => 'Mykola Ostrovskyy',
            'email'  => 'spambox03@mail.ru',
            'date'   => '2009-02-28',
            'name'   => 'Columns',
            'desc'   => 'Arrange information in multiple columns.',
            'url'    => 'http://wiki.splitbrain.org/plugin:columns',
        );
    }

    /**
     * Register callbacks
     */
    function register(&$controller) {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'handle');
    }

    /**
     *
     */
    function handle(&$event, $param) {
        $style = $this->_buildLayout($event);
        if (count($this->block) > 0) {
            foreach ($this->block as $block) {
                $block->processAttributes($event);
            }
        }
    }

    /**
     * Find all columns instructions and construct columns layout based on them
     */
    function _buildLayout(&$event) {
        $calls = count($event->data->calls);
        $currentBlock = NULL;
        for ($c = 0; $c < $calls; $c++) {
            $call =& $event->data->calls[$c];
            if (($call[0] == 'plugin') && ($call[1][0] == 'columns')) {
                switch ($call[1][1][0]) {
                    case DOKU_LEXER_ENTER:
                        $currentBlock = new columns_block($currentBlock);
                        $currentBlock->addColumn($c);
                        $this->block[] = $currentBlock;
                        break;

                    case DOKU_LEXER_MATCHED:
                        $currentBlock->addColumn($c);
                        break;

                    case DOKU_LEXER_EXIT:
                        $currentBlock = $currentBlock->getParent();
                        break;
                }
            }
        }
    }
}

class columns_block {

    var $parent;
    var $column;

    /**
     * Constructor
     */
    function columns_block($parent) {
        $this->parent = $parent;
        $this->column = array();
    }

    /**
     *
     */
    function getParent() {
        return $this->parent;
    }

    /**
     *
     */
    function addColumn($callIndex) {
        $this->column[] = $callIndex;
    }

    /**
     * Convert raw attributes and layout information into column attributes
     */
    function processAttributes(&$event) {
        $columns = count($this->column);
        for ($c = 0; $c < $columns; $c++) {
            $call =& $event->data->calls[$this->column[$c]];
            if ($c == 0) {
                $attribute = $this->_loadTableAttributes($call[1][1][1]);
                $attribute[$c]['class'] = 'first';
            }
            else {
                //TODO: load attribs (override)
                if ($c == ($columns - 1)) {
                    $attribute[$c]['class'] = 'last';
                }
            }
            if (array_key_exists($c, $attribute)) {
                $call[1][1][1] = $attribute[$c];
            }
            else {
                $call[1][1][1] = array();
            }
        }
    }

    /**
     * Convert raw attributes and layout information into column attributes
     */
    function _loadTableAttributes($attribute) {
        $result = array();
        $column = -1;
        foreach ($attribute as $a) {
            if (preg_match('/^(\*?)((?:-|(?:\d+\.?|\d*\.\d+)(?:%|em|px)))(\*?)$/', $a, $match) == 1) {
                if ($column == -1) {
                    if ($match[2] != '-') {
                        $result[0]['table-width'] = $match[2];
                    }
                }
                else {
                    if ($match[2] != '-') {
                        $result[$column]['column-width'] = $match[2];
                    }
                    $align = $match[1] . '-' . $match[3];
                    if ($align != '-') {
                        $result[$column]['text-align'] = $this->_getAlignment($align);
                    }
                }
                $column++;
            }
        }
        return $result;
    }

    /**
     * Returns column text alignment
     */
    function _getAlignment($align) {
        switch ($align) {
            case '-*':
                return 'left';

            case '*-':
                return 'right';

            case '*-*':
                return 'center';
        }
    }
}
