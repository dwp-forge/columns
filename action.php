<?php

/**
 * Plugin Columns: Layout parser
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

/* Must be run within Dokuwiki */
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once(DOKU_PLUGIN . 'action.php');
require_once(DOKU_PLUGIN . 'columns/info.php');
require_once(DOKU_PLUGIN . 'columns/rewriter.php');

class action_plugin_columns extends DokuWiki_Action_Plugin {

    var $block;
    var $currentBlock;
    var $currentSectionLevel;

    /**
     * Return some info
     */
    function getInfo() {
        return columns_getInfo('layout parser');
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
        $this->_reset();
        $this->_buildLayout($event);
        $rewriter = new instruction_rewriter();
        foreach ($this->block as $block) {
            $block->processAttributes($event);
            $rewriter->addCorrections($block->getCorrections());
        }
        $rewriter->process($event->data->calls);
    }

    /**
     * Find all columns instructions and construct columns layout based on them
     */
    function _buildLayout(&$event) {
        $calls = count($event->data->calls);
        for ($c = 0; $c < $calls; $c++) {
            $call =& $event->data->calls[$c];
            switch ($call[0]) {
                case 'section_open':
                    $this->currentSectionLevel = $call[1][0];
                    break;

                case 'section_close':
                    $this->currentBlock->closeSection($c);
                    break;

                case 'plugin':
                    if ($call[1][0] == 'columns') {
                        $this->_handleColumns($c, $call[1][1][0]);
                    }
                    break;
            }
        }
    }

    /**
     * Reset internal state
     */
    function _reset() {
        $this->block = array();
        $this->block[0] = new columns_root_block();
        $this->currentBlock = $this->block[0];
        $this->currentSectionLevel = 0;
    }

    /**
     *
     */
    function _handleColumns($callIndex, $state) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                $this->currentBlock = new columns_block(count($this->block), $this->currentBlock);
                $this->currentBlock->addColumn($callIndex, $this->currentSectionLevel);
                $this->block[] = $this->currentBlock;
                break;

            case DOKU_LEXER_MATCHED:
                $this->currentBlock->addColumn($callIndex, $this->currentSectionLevel);
                break;

            case DOKU_LEXER_EXIT:
                $this->currentBlock->close($callIndex);
                $this->currentBlock = $this->currentBlock->getParent();
                break;
        }
    }
}

class columns_root_block {

    var $call;

    /**
     * Constructor
     */
    function columns_root_block() {
        $this->call = array();
    }

    /**
     *
     */
    function getParent() {
        return $this;
    }

    /**
     * Collect stray <newcolumn> tags
     */
    function addColumn($callIndex, $sectionLevel) {
        $this->call[] = $callIndex;
    }

    /**
     *
     */
    function closeSection($callIndex) {
    }

    /**
     * Collect stray </colums> tags
     */
    function close($callIndex) {
        $this->call[] = $callIndex;
    }

    /**
     *
     */
    function processAttributes(&$event) {
    }

    /**
     * Delete all captured tags
     */
    function getCorrections() {
        $correction = array();
        foreach ($this->call as $call) {
            $correction[] = new instruction_rewriter_delete($call);
        }
        return $correction;
    }
}

class columns_block {

    var $id;
    var $parent;
    var $column;
    var $attribute;
    var $sectionLevel;
    var $sectionClose;
    var $end;

    /**
     * Constructor
     */
    function columns_block($id, $parent) {
        $this->id = $id;
        $this->parent = $parent;
        $this->column = array();
        $this->attribute = array();
        $this->sectionLevel = array();
        $this->sectionClose = array();
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
    function addColumn($callIndex, $sectionLevel) {
        $this->column[] = $callIndex;
        $this->attribute[] = new columns_attributes_bag();
        $this->sectionLevel[] = $sectionLevel;
        $this->sectionClose[] = -1;
    }

    /**
     *
     */
    function closeSection($callIndex) {
        $column = count($this->column) - 1;
        if ($this->sectionClose[$column] == -1) {
            $this->sectionClose[$column] = $callIndex;
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
                $this->_loadTableAttributes($call[1][1][1]);
                $this->attribute[0]->addAttribute('columns', $columns);
                $this->attribute[0]->addAttribute('class', 'first');
            }
            else {
                $this->_loadColumnAttributes($c, $call[1][1][1]);
                if ($c == ($columns - 1)) {
                    $this->attribute[$c]->addAttribute('class', 'last');
                }
            }
            $this->attribute[$c]->addAttribute('block-id', $this->id);
            $this->attribute[$c]->addAttribute('column-id', $c + 1);
            $call[1][1][1] = $this->attribute[$c]->getAttributes();
        }
    }

    /**
     * Convert raw attributes into column attributes
     */
    function _loadTableAttributes($attribute) {
        $column = -1;
        $nextColumn = -1;
        foreach ($attribute as $a) {
            list($name, $temp) = $this->_parseAttribute($a);
            if ($name == 'width') {
                if (($column == -1) && array_key_exists('column-width', $temp)) {
                    $this->attribute[0]->addAttribute('table-width', $temp['column-width']);
                }
                $nextColumn = $column + 1;
            }
            if (($column >= 0) && ($column < count($this->column))) {
                $this->attribute[$column]->addAttributes($temp);
            }
            $column = $nextColumn;
        }
    }

    /**
     * Convert raw attributes into column attributes
     */
    function _loadColumnAttributes($column, $attribute) {
        foreach ($attribute as $a) {
            list($name, $temp) = $this->_parseAttribute($a);
            $this->attribute[$column]->addAttributes($temp);
        }
    }

    /**
     *
     */
    function _parseAttribute($attribute) {
        static $syntax = array(
            '/^left|right|center|justify$/' => 'text-align',
            '/^top|middle|bottom$/' => 'vertical-align',
            '/^[lrcjtmb]{1,2}$/' => 'align',
            '/^continue|\.{3}$/' => 'continue',
            '/^(\*?)((?:-|(?:\d+\.?|\d*\.\d+)(?:%|em|px|cm|mm|in|pt)))(\*?)$/' => 'width'
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
                $result = $this->_parseAlignAttribute($match[0]);
                break;

            case 'continue':
                $result[$attributeName] = 'on';
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
        $align1 = $this->_getAlignStyle($syntax{0});
        if (strlen($syntax) == 2) {
            $align2 = $this->_getAlignStyle($syntax{1});
            if ($align1 != $align2) {
                $result[$align1] = $this->_getAlignment($syntax{0});
                $result[$align2] = $this->_getAlignment($syntax{1});
            }
        }
        else{
            $result[$align1] = $this->_getAlignment($syntax{0});
        }
        return $result;
    }

    /**
     *
     */
    function _getAlignStyle($align) {
        return preg_match('/[lrcj]/', $align) ? 'text-align' : 'vertical-align';
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
     * Returns a list of corrections that have to be applied to the instruction array
     */
    function getCorrections() {
        if ($this->end != -1) {
            $correction = $this->_fixSections();
        }
        else {
            $correction = $this->_deleteColumns();
        }
        return $correction;
    }

    /**
     * Re-write section open/close instructions to produce valid HTML
     */
    function _fixSections() {
        $columns = count($this->column);
        $correction = array();
        for ($c = 0; $c < $columns; $c++) {
            /* If there is a section_close within the column there is also a section_open */
            $deleteSectionClose = ($this->sectionClose[$c] != -1);
            $closeSection = $deleteSectionClose;
            if (($this->attribute[$c]->getAttribute('continue') == 'on') && ($this->sectionLevel[$c] > 0)) {
                /* Insert section_open at the start of the column */
                $insert = new instruction_rewriter_insert($this->column[$c] + 1);
                $insert->addCall('section_open', array($this->sectionLevel[$c]));
                $correction[] = $insert;
                /* Ensure that this section will be properly closed */
                $deleteSectionClose = false;
                $closeSection = true;
            }
            if ($deleteSectionClose) {
                /* Remove first section_close from the column to prevent </div> in the middle of the column */
                $correction[] = new instruction_rewriter_delete($this->sectionClose[$c]);
            }
            if ($closeSection) {
                /* Close last open section in the column */
                if ($c < ($columns - 1)) {
                    $insert = $this->column[$c + 1];
                }
                else {
                    $insert = $this->end;
                }
                $insert = new instruction_rewriter_insert($insert);
                $insert->addCall('section_close', array());
                //TODO: Do something about section_edit?
                $correction[] = $insert;
            }
        }
        return $correction;
    }

    /**
     *
     */
    function _deleteColumns() {
        $correction = array();
        foreach ($this->column as $column) {
            $correction[] = new instruction_rewriter_delete($column);
        }
        return $correction;
    }
}

class columns_attributes_bag {

    var $attribute;

    /**
     * Constructor
     */
    function columns_attributes_bag() {
        $this->attribute = array();
    }

    /**
     *
     */
    function addAttribute($name, $value) {
        $this->attribute[$name] = $value;
    }

    /**
     *
     */
    function addAttributes($attribute) {
        if (is_array($attribute) && (count($attribute) > 0)) {
            $this->attribute = array_merge($this->attribute, $attribute);
        }
    }

    /**
     *
     */
    function getAttribute($name) {
        $result = '';
        if (array_key_exists($name, $this->attribute)) {
            $result = $this->attribute[$name];
        }
        return $result;
    }

    /**
     *
     */
    function getAttributes() {
        return $this->attribute;
    }
}
