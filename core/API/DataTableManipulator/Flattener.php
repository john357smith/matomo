<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik\API\DataTableManipulator;

use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\API\DataTableManipulator;

/**
 * This class is responsible for flattening data tables.
 *
 * It loads subtables and combines them into a single table by concatenating the labels.
 * This manipulator is triggered by using flat=1 in the API request.
 *
 * @package Piwik
 * @subpackage Piwik_API
 */
class Flattener extends DataTableManipulator
{

    private $includeAggregateRows = false;

    /**
     * If the flattener is used after calling this method, aggregate rows will
     * be included in the result. This can be useful when they contain data that
     * the leafs don't have (e.g. conversion stats in some cases).
     */
    public function includeAggregateRows()
    {
        $this->includeAggregateRows = true;
    }

    /**
     * Separator for building recursive labels (or paths)
     * @var string
     */
    public $recursiveLabelSeparator = ' - ';

    /**
     * @param  DataTable $dataTable
     * @return DataTable|DataTable\Map
     */
    public function flatten($dataTable)
    {
        if ($this->apiModule == 'Actions' || $this->apiMethod == 'getWebsites') {
            $this->recursiveLabelSeparator = '/';
        }

        return $this->manipulate($dataTable);
    }

    /**
     * Template method called from self::manipulate.
     * Flatten each data table.
     *
     * @param DataTable $dataTable
     * @return DataTable
     */
    protected function manipulateDataTable($dataTable)
    {
        if ($this->includeAggregateRows) {
            $dataTable->applyQueuedFilters();
        }

        $newDataTable = $dataTable->getEmptyClone($keepFilters = true);
        foreach ($dataTable->getRows() as $row) {
            $this->flattenRow($row, $newDataTable);
        }
        return $newDataTable;
    }

    /**
     * @param Row $row
     * @param DataTable $dataTable
     * @param string $labelPrefix
     * @param bool $parentLogo
     */
    private function flattenRow(Row $row, DataTable $dataTable,
                                $labelPrefix = '', $parentLogo = false)
    {
        $label = $row->getColumn('label');
        if ($label !== false) {
            $label = trim($label);
            if (substr($label, 0, 1) == '/' && $this->recursiveLabelSeparator == '/') {
                $label = substr($label, 1);
            }
            $label = $labelPrefix . $label;
            $row->setColumn('label', $label);
        }

        $logo = $row->getMetadata('logo');
        if ($logo === false && $parentLogo !== false) {
            $logo = $parentLogo;
            $row->setMetadata('logo', $logo);
        }

        $subTable = $this->loadSubtable($dataTable, $row);
        $row->removeSubtable();

        if ($subTable === null) {
            if ($this->includeAggregateRows) {
                $row->setMetadata('is_aggregate', 0);
            }
            $dataTable->addRow($row);
        } else {
            if ($this->includeAggregateRows) {
                $row->setMetadata('is_aggregate', 1);
                $dataTable->addRow($row);
            }
            $prefix = $label . $this->recursiveLabelSeparator;
            foreach ($subTable->getRows() as $row) {
                $this->flattenRow($row, $dataTable, $prefix, $logo);
            }
        }
    }

    /**
     * Remove the flat parameter from the subtable request
     *
     * @param array $request
     */
    protected function manipulateSubtableRequest(&$request)
    {
        unset($request['flat']);
    }
}
