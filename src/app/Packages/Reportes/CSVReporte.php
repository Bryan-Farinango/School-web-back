<?php

namespace App\Packages\Reportes;

use App;
use Closure;
use Exception;
use League\Csv\Writer;
use SplTempFileObject;

/**
 * Generador de Reportes CSV
 *
 * @package stupendo
 * @author Julio Hernandez (juliohernandezs@gmail.com)
 */
class CSVReporte extends GeneradorReporte
{
    protected $showMeta = false;
    protected $csv;

    public function generate($type_object = false)
    {
        if (!class_exists(Writer::class)) {
            throw new Exception('Por favor, instala league/csv para poder generar el reporte CSV!');
        }

        if ($type_object) {
            $csv = Writer::createFromFileObject(new SplTempFileObject());
        } else {
            if (!$this->getPath()) {
                $this->setPath();
            }

            $csv = Writer::createFromPath($this->getFullPath(), 'w+');
        }

        if ($this->showMeta) {
            foreach ($this->headers['meta'] as $key => $value) {
                $csv->insertOne([$key, $value]);
            }

            $csv->insertOne([' ']);
        }

        $ctr = 1;

        if ($this->showHeader) {
            $columns = array_keys($this->columns);

            if (!$this->withoutManipulation && $this->showNumColumn) {
                array_unshift($columns, 'No');
            }

            $csv->insertOne($columns);
        }

        foreach ($this->query->take($this->limit ?: null)->get() as $result) {
            if ($this->withoutManipulation) {
                $data = $result->toArray();

                if (count($data) > count($this->columns)) {
                    array_pop($data);
                }

                $csv->insertOne($data);
            } else {
                $formattedRows = $this->formatRow($result);

                if ($this->showNumColumn) {
                    array_unshift($formattedRows, $ctr);
                }

                $csv->insertOne($formattedRows);
            }

            $ctr++;
        }

        $this->csv = $csv;

        return $this;
    }

    public function download()
    {
        if (!class_exists(Writer::class)) {
            throw new Exception('Por favor, instala league/csv para poder generar el reporte CSV!');
        }

        $this->generate(true)->csv->output($this->getFileName());
    }

    public function print()
    {
        $this->csv->output();

        return $this;
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

            array_push($rows, $displayedColValue);
        }

        return $rows;
    }
}