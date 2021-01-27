<?php

namespace App\Packages\Reportes;

use App;
use Closure;
use Config;
use Illuminate\Support\Collection;

class ExcelReporte extends GeneradorReporte
{
    private $format = 'xlsx';
    private $total = [];

    public function setFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    public function make($simpleVersion = false)
    {
        if (!$this->getPath()) {
            $this->setPath();
        }

        $filename = $this->getFilename();

        if ($simpleVersion) {
            return App::make('excel')->create(
                $filename,
                function ($excel) use ($filename) {
                    $excel->sheet(
                        'Sheet 1',
                        function ($sheet) {
                            $sheet->setColumnFormat(['A:Z' => '@']);
                            $ctr = 1;
                            $grandTotalSkip = 1;
                            $currentGroupByData = [];
                            $isOnSameGroup = true;

                            foreach ($this->showTotalColumns as $column => $type) {
                                $this->total[$column] = 0;
                            }

                            if ($this->showTotalColumns != []) {
                                foreach ($this->columns as $colName => $colData) {
                                    if (!array_key_exists($colName, $this->showTotalColumns)) {
                                        $grandTotalSkip++;
                                    } else {
                                        break;
                                    }
                                }
                            }

                            $grandTotalSkip = !$this->showNumColumn ? $grandTotalSkip - 1 : $grandTotalSkip;
                            $sheet->appendRow([$this->headers['title']]);
                            $sheet->appendRow([' ']);

                            if ($this->showMeta) {
                                foreach ($this->headers['meta'] as $key => $value) {
                                    $sheet->appendRow([$key, $value]);
                                }
                                $sheet->appendRow([' ']);
                            }

                            if ($this->showHeader) {
                                $columns = array_keys($this->columns);
                                if (!$this->withoutManipulation && $this->showNumColumn) {
                                    array_unshift($columns, 'No');
                                }
                                $sheet->appendRow($columns);
                            }

                            foreach ($this->query->take($this->limit ?: null)->get() as $result) {
                                if ($this->groupByArr) {
                                    $isOnSameGroup = true;

                                    foreach ($this->groupByArr as $groupBy) {
                                        if (is_object(
                                                $this->columns[$groupBy]
                                            ) && $this->columns[$groupBy] instanceof Closure) {
                                            $thisGroupByData[$groupBy] = $this->columns[$groupBy]($result);
                                        } else {
                                            $thisGroupByData[$groupBy] = $result->{$this->columns[$groupBy]};
                                        }

                                        if (isset($currentGroupByData[$groupBy])) {
                                            if ($thisGroupByData[$groupBy] != $currentGroupByData[$groupBy]) {
                                                $isOnSameGroup = false;
                                            }
                                        }

                                        $currentGroupByData[$groupBy] = $thisGroupByData[$groupBy];
                                    }

                                    if ($isOnSameGroup === false) {
                                        $totalRows = collect(['Gran Total']);

                                        foreach ($columns as $columnName) {
                                            if ($columnName == $columns[0]) {
                                                continue;
                                            }

                                            if (array_key_exists($columnName, $this->showTotalColumns)) {
                                                if ($this->showTotalColumns[$columnName] == 'point') {
                                                    $totalRows->push(
                                                        number_format($this->total[$columnName], 2, '.', ',')
                                                    );
                                                } else {
                                                    $totalRows->push(
                                                        strtoupper(
                                                            $this->showTotalColumns[$columnName]
                                                        ) . ' ' . number_format($this->total[$columnName], 2, '.', ',')
                                                    );
                                                }
                                            } else {
                                                $totalRows->push(null);
                                            }
                                        }

                                        $sheet->appendRow($totalRows->toArray());
                                        $no = 1;
                                        foreach ($this->showTotalColumns as $showTotalColumn => $type) {
                                            $this->total[$showTotalColumn] = 0;
                                        }
                                        $isOnSameGroup = true;
                                    }
                                }

                                if ($this->withoutManipulation) {
                                    $data = $result->toArray();
                                    if (count($data) > count($this->columns)) {
                                        array_pop($data);
                                    }
                                    $sheet->appendRow($data);
                                } else {
                                    $formattedRows = $this->formatRow($result);
                                    if ($this->showNumColumn) {
                                        array_unshift($formattedRows, $ctr);
                                    }
                                    $sheet->appendRow($formattedRows);
                                }

                                foreach ($this->showTotalColumns as $colName => $type) {
                                    $this->total[$colName] += $result->{$this->columns[$colName]};
                                }

                                $ctr++;
                            }

                            if ($this->showTotalColumns) {
                                $totalRows = collect(['Gran Total']);
                                array_shift($columns);

                                foreach ($columns as $columnName) {
                                    if (array_key_exists($columnName, $this->showTotalColumns)) {
                                        if ($this->showTotalColumns[$columnName] == 'point') {
                                            $totalRows->push(number_format($this->total[$columnName], 2, '.', ','));
                                        } else {
                                            $totalRows->push(
                                                strtoupper($this->showTotalColumns[$columnName]) . ' ' . number_format(
                                                    $this->total[$columnName],
                                                    2,
                                                    '.',
                                                    ','
                                                )
                                            );
                                        }
                                    } else {
                                        $totalRows->push(null);
                                    }
                                }

                                $sheet->appendRow($totalRows->toArray());
                            }
                        }
                    );
                }
            );
        } else {
            return App::make('excel')->create(
                $filename,
                function ($excel) use ($filename) {
                    $excel->sheet(
                        'Sheet 1',
                        function ($sheet) {
                            $headers = $this->headers;
                            $query = $this->query;
                            $columns = $this->columns;
                            $limit = $this->limit;
                            $groupByArr = $this->groupByArr;
                            $orientation = $this->orientation;
                            $editColumns = $this->editColumns;
                            $showTotalColumns = $this->showTotalColumns;
                            $styles = $this->styles;
                            $showHeader = $this->showHeader;
                            $showTitle = $this->showTitle;
                            $showMeta = $this->showMeta;
                            $applyFlush = $this->applyFlush;
                            $showNumColumn = $this->showNumColumn;
                            $sheet->setColumnFormat(['A:Z' => '@']);

                            if ($this->columnsFormat) {
                                $sheet->setColumnFormat($this->columnsFormat);
                            }

                            if ($query instanceof Collection) {
                                $sheet->loadView(
                                    Config::get('reportes.excel.views.general'),
                                    compact(
                                        'headers',
                                        'columns',
                                        'editColumns',
                                        'showTotalColumns',
                                        'styles',
                                        'query',
                                        'limit',
                                        'groupByArr',
                                        'orientation',
                                        'showHeader',
                                        'showMeta',
                                        'applyFlush',
                                        'showNumColumn',
                                        'showTitle'
                                    )
                                );
                            } else {
                                if ($this->withoutManipulation) {
                                    $sheet->loadView(
                                        Config::get('reportes.excel.views.sin-manipulacion'),
                                        compact(
                                            'headers',
                                            'columns',
                                            'showTotalColumns',
                                            'query',
                                            'limit',
                                            'groupByArr',
                                            'orientation',
                                            'showHeader',
                                            'showMeta',
                                            'applyFlush',
                                            'showNumColumn',
                                            'showTitle'
                                        )
                                    );
                                } else {
                                    $sheet->loadView(
                                        Config::get('reportes.excel.views.general'),
                                        compact(
                                            'headers',
                                            'columns',
                                            'editColumns',
                                            'showTotalColumns',
                                            'styles',
                                            'query',
                                            'limit',
                                            'groupByArr',
                                            'orientation',
                                            'showHeader',
                                            'showMeta',
                                            'applyFlush',
                                            'showNumColumn',
                                            'showTitle'
                                        )
                                    );
                                }
                            }
                        }
                    );
                }
            );
        }
    }

    public function download()
    {
        return $this->make($this->filename, $this->simpleVersion)->export($this->format);
    }

    public function simpleDownload()
    {
        return $this->make($this->filename, true)->export($this->format);
    }

    private function formatRow($result)
    {
        $rows = [];

        foreach ($this->columns as $colName => $colData) {
            if (is_object($colData) && $colData instanceof Closure) {
                $generatedColData = $colData($result);
            } else {
                $generatedColData = $result->$colData;
            }

            $displayedColValue = $generatedColData;

            if (array_key_exists($colName, $this->editColumns)) {
                if (isset($this->editColumns[$colName]['displayAs'])) {
                    $displayAs = $this->editColumns[$colName]['displayAs'];

                    if (is_object($displayAs) && $displayAs instanceof Closure) {
                        $displayedColValue = $displayAs($result);
                    } elseif (!(is_object($displayAs) && $displayAs instanceof Closure)) {
                        $displayedColValue = $displayAs;
                    }
                }
            }

            if (array_key_exists($colName, $this->showTotalColumns)) {
                $this->total[$colName] += $generatedColData;
            }

            array_push($rows, $displayedColValue);
        }

        return $rows;
    }
}