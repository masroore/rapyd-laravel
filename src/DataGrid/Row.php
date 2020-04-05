<?php

namespace Zofe\Rapyd\DataGrid;

use Zofe\Rapyd\Helpers\HTML;

class Row
{
    public $attributes = [];
    public $cells = [];
    public $cell_names = [];
    public $data;

    public function __construct($tablerow)
    {
        $this->data = $tablerow;
    }

    public function add(Cell $cell)
    {
        if (!\in_array($cell->name, $this->cell_names)) {
            array_push($this->cell_names, $cell->name);
        }
        $this->cells[] = $cell;

        return $this;
    }

    public function cell($name)
    {
        $index = array_search($name, $this->cell_names);
        if (false === $index) {
            return false;
        }

        return $this->cells[$index];
    }

    public function attributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function style($style)
    {
        $this->attributes['style'] = $style;

        return $this;
    }

    public function buildAttributes()
    {
        return HTML::buildAttributes($this->attributes);
    }

    public function toArray()
    {
        $values = [];
        foreach ($this->cells as $cell) {
            $values[] = $cell->value;
        }

        return $values;
    }
}
