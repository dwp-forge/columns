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
            'date'   => '2009-03-01',
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
            $change = array();
            foreach ($this->block as $block) {
                $block->processAttributes($event);
                $change = array_merge($change, $block->getCallChanges($event));
            }
            if (count($change) > 0) {
                $change = $this->_sortChanges($change);
                $this->_applyChanges($event, $change);
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
            if (($call[0] == 'section_close') && ($currentBlock != NULL)) {
                $currentBlock->closeSection($c);
            }
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
                        $currentBlock->close($c);
                        $currentBlock = $currentBlock->getParent();
                        break;
                }
            }
        }
    }

    /**
     *
     */
    function _sortChanges($change) {
        $result = array();
        foreach ($change as $ch) {
            $result[$ch['index']] = $ch;
        }
        ksort($result);
        return array_values($result);
    }

    /**
     *
     */
    function _applyChanges(&$event, $change) {
        $calls = count($event->data->calls);
        $changes = count($change);
        $call = array();
        for ($c = 0, $ch = 0; $c < $calls; $c++) {
            if (($ch < $changes) && ($change[$ch]['index'] == $c)) {
                switch ($change[$ch]['command']) {
                    case 'delete':
                        break;

                    case 'insert':
                        foreach ($change[$ch]['call'] as $cl) {
                            $call[] = $cl;
                        }
                        $call[] = $event->data->calls[$c];
                        break;
                }
                $ch++;
            }
            else {
                $call[] = $event->data->calls[$c];
            }
        }
        $event->data->calls = $call;
    }
}

class columns_block {

    var $parent;
    var $column;
    var $closeSection;
    var $end;

    /**
     * Constructor
     */
    function columns_block($parent) {
        $this->parent = $parent;
        $this->column = array();
        $this->closeSection = array();
        $this->end = -1;
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
        $this->closeSection[] = -1;
    }

    /**
     *
     */
    function closeSection($callIndex) {
        $column = count($this->column) - 1;
        if ($this->closeSection[$column] == -1) {
            $this->closeSection[$column] = $callIndex;
        }
    }

    /**
     *
     */
    function close($callIndex) {
        $this->end = $callIndex;
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
                $attribute[$c] = array_merge($attribute[$c], $this->_loadColumnAttributes($call[1][1][1]));
                if ($c == ($columns - 1)) {
                    $attribute[$c]['class'] = 'last';
                }
            }
            $call[1][1][1] = $attribute[$c];
        }
    }

    /**
     * Convert raw attributes into column attributes
     */
    function _loadTableAttributes($attribute) {
        $result = array();
        $column = -1;
        $nextColumn = -1;
        foreach ($attribute as $a) {
            list($name, $temp) = $this->_parseAttribute($a);
            if ($name == 'width') {
                if (($column == -1) && array_key_exists('column-width', $temp)) {
                    $result[0]['table-width'] = $temp['column-width'];
                }
                $nextColumn = $column + 1;
            }
            if ($column >= 0) {
                $result[$column] = array_merge($result[$column], $temp);
            }
            $column = $nextColumn;
        }
        return $result;
    }

    /**
     * Convert raw attributes into column attributes
     */
    function _loadColumnAttributes($attribute) {
        $result = array();
        foreach ($attribute as $a) {
            list($name, $temp) = $this->_parseAttribute($a);
            $result = array_merge($result, $temp);
        }
        return $result;
    }

    /**
     *
     */
    function _parseAttribute($attribute) {
        static $syntax = array(
            '/^left|right|center|justify$/' => 'text-align',
            '/^top|middle|bottom$/' => 'vertical-align',
            '/^[lrcjtmb]{2}$/' => 'align',
            '/^(\*?)((?:-|(?:\d+\.?|\d*\.\d+)(?:%|em|px)))(\*?)$/' => 'width'
        );
        $result = array();
        $attributeName = '';
        foreach ($syntax as $pattern => $name) {
            if (preg_match($pattern, $attribute, $match) == 1) {
                $attributeName = $name;
                break;
            }
        }
        switch ($attributeName) {
            case 'text-align':
            case 'vertical-align':
                $result[$attributeName] = $match[0];
                break;

            case 'align':
                $result = $this->_parseAlignAttribute($match);
                break;

            case 'width':
                $result = $this->_parseWidthAttribute($match);
                break;
        }
        return array($attributeName, $result);
    }

    /**
     *
     */
    function _parseAlignAttribute($syntax) {
        $result = array();
        $align1 = preg_match('/lrcj/', $syntax{0}) ? 'text-align' : 'vertical-align';
        $align2 = preg_match('/lrcj/', $syntax{1}) ? 'text-align' : 'vertical-align';
        if ($align1 != $align2) {
            $result[$align1] = $this->_getAlignment($syntax{0});
            $result[$align2] = $this->_getAlignment($syntax{1});
        }
        return $result;
    }

    /**
     *
     */
    function _parseWidthAttribute($syntax) {
        $result = array();
        if ($syntax[2] != '-') {
            $result['column-width'] = $syntax[2];
        }
        $align = $syntax[1] . '-' . $syntax[3];
        if ($align != '-') {
            $result['text-align'] = $this->_getAlignment($align);
        }
        return $result;
    }

    /**
     * Returns column text alignment
     */
    function _getAlignment($syntax) {
        static $align = array(
            'l' => 'left', '-*' => 'left',
            'r' => 'right', '*-' => 'right',
            'c' => 'center', '*-*' => 'center',
            'j' => 'justify',
            't' => 'top',
            'm' => 'middle',
            'b' => 'bottom'
        );
        if (array_key_exists($syntax, $align)) {
            return $align[$syntax];
        }
        else {
            return '';
        }
    }

    /**
     * Returns a list of changes that have to be applied to the instruction array
     */
    function getCallChanges(&$event) {
        $columns = count($this->column);
        $change = array();
        for ($c = 0; $c < $columns; $c++) {
            if ($this->closeSection[$c] != -1) {
                $change[] = $this->_buildChange($this->closeSection[$c], 'delete');
                if ($c < ($columns - 1)) {
                    $insert = $this->column[$c + 1];
                }
                else {
                    $insert = $this->end;
                }
                $call = array();
                $call[] = array('section_close', array(), $event->data->calls[$insert][2]);
                //TODO: Do something about section_edit?
                $change[] = $this->_buildChange($insert, 'insert', $call);
            }
        }
        return $change;
    }

    /**
     *
     */
    function _buildChange($index, $command, $call = NULL) {
        $change['index'] = $index;
        $change['command'] = $command;
        if ($command == 'insert') {
            $change['call'] = $call;
        }
        return $change;
    }
}
