<?php

namespace App\Packages\Reportes;

use Config;

/**
 * Clase base para generar CSV/Excel
 *
 * @package stupendo
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
class GeneradorReporte
{
    protected $applyFlush;
    protected $headers;
    protected $columns;
    protected $columnsFormat;
    protected $query;
    protected $limit = null;
    protected $groupByArr = [];
    protected $paper = 'a4';
    protected $orientation = 'portrait';
    protected $editColumns = [];
    protected $showNumColumn = false;
    protected $showTotalColumns = [];
    protected $styles = [];
    protected $simpleVersion = false;
    protected $withoutManipulation = false;
    protected $showMeta = true;
    protected $showHeader = true;
    protected $showTitle = false;
    protected $filename = '';
    protected $path = '';

    public function __construct()
    {
        $this->applyFlush = (bool)Config::get('reportes.flush', true);
    }

    public function of($title, array $meta = [], $query, array $columns, array $columnsFormat = [])
    {
        $this->headers = [
            'title' => $title,
            'meta' => $meta
        ];
        $this->query = $query;
        $this->columns = $this->mapColumns($columns);
        $this->columnsFormat = $columnsFormat;

        return $this;
    }

    public function showHeader($value = true)
    {
        $this->showHeader = $value;
        return $this;
    }

    public function showMeta($value = true)
    {
        $this->showMeta = $value;
        return $this;
    }

    public function showNumColumn($value = true)
    {
        $this->showNumColumn = $value;
        return $this;
    }

    public function simple()
    {
        $this->simpleVersion = true;
        return $this;
    }

    public function withoutManipulation()
    {
        $this->withoutManipulation = true;
        return $this;
    }

    private function mapColumns(array $columns)
    {
        $result = [];
        foreach ($columns as $name => $data) {
            if (is_int($name)) {
                $result[$data] = snake_case($data);
            } else {
                $result[$name] = $data;
            }
        }
        return $result;
    }

    public function setPaper($paper)
    {
        $this->paper = strtolower($paper);
        return $this;
    }

    public function editColumn($columnName, array $options)
    {
        foreach ($options as $option => $value) {
            $this->editColumns[$columnName][$option] = $value;
        }
        return $this;
    }

    public function editColumns(array $columnNames, array $options)
    {
        foreach ($columnNames as $columnName) {
            $this->editColumn($columnName, $options);
        }
        return $this;
    }

    public function showTotal(array $columns)
    {
        $this->showTotalColumns = $columns;
        return $this;
    }

    public function groupBy($column)
    {
        if (is_array($column)) {
            $this->groupByArr = $column;
        } else {
            array_push($this->groupByArr, $column);
        }
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function setOrientation($orientation)
    {
        $this->orientation = strtolower($orientation);
        return $this;
    }

    public function setCss(array $styles)
    {
        foreach ($styles as $selector => $style) {
            array_push(
                $this->styles,
                [
                    'selector' => $selector,
                    'style' => $style
                ]
            );
        }
        return $this;
    }

    public function setFileName($name = '', $extension = 'csv')
    {
        $this->filename = ($name) ?: uniqid('reporte_') . '.' . $extension;
        return $this;
    }

    public function getFileName()
    {
        return $this->filename;
    }

    public function setPath($folder = '')
    {
        if ($folder) {
            $path = realpath($folder);
            //Si el directorio no existe, asignamos /tmp/reports
            $this->path = ($path && (is_dir($path))) ? $path : '/tmp/reports/';
        } else {
            $this->path = '/tmp/reports/';

            if (!realpath($this->path) && !is_dir($this->path)) {
                mkdir($this->path, 0777, true);
            }
        }

        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getFullPath()
    {
        return $this->getPath() . '/' . $this->getFileName();
    }
}