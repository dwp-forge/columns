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
    var $blocks;
    var $nestingLevel;
    var $nestedBlock;
    var $column;

    /**
     * Constructor
     */
    function action_plugin_columns() {
        $this->block = 0;
        $this->blocks = 0;
        $this->nestingLevel = 0;
        $this->nestedBlock = array(0);
        $this->column = array();
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
        if ($this->blocks > 0) {
            foreach ($this->column as $column) {
                $this->_processBlock($event, $column);
            }
        }
    }

    /**
     * Find all columns instructions and construct columns layout based on them
     */
    function _buildLayout(&$event) {
        $instructions = count($event->data->calls);
        for ($i = 0; $i < $instructions; $i++) {
            $call =& $event->data->calls[$i];
            if (($call[0] == 'plugin') && ($call[1][0] == 'columns')) {
                switch ($call[1][1][0]) {
                    case DOKU_LEXER_ENTER:
                        $this->block = ++$this->blocks;
                        $this->nestedBlock[++$this->nestingLevel] = $this->block;
                        $this->column[$this->block][] = $i;
                        break;

                    case DOKU_LEXER_MATCHED:
                        $this->column[$this->block][] = $i;
                        break;

                    case DOKU_LEXER_EXIT:
                        $this->block = $this->nestedBlock[--$this->nestingLevel];
                        break;
                }
            }
        }
    }

    /**
     * Convert raw attributes and layout information into column attributes
     */
    function _processBlock(&$event, $column) {
        $columns = count($column);
        for ($c = 0; $c < $columns; $c++) {
            $call =& $event->data->calls[$column[$c]];
            if ($c == 0) {
                //TODO: load attribs
                //TODO: set class = first
                //TODO: set table width
            }
            //TODO: load attribs (override)
            //TODO: set column attribs
            if ($c == ($count - 1)) {
                //TODO: set class = last
            }
        }
    }
}
