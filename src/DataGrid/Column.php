<?php

namespace Zofe\Rapyd\DataGrid;

use Closure;
use Exception;
use Zofe\Rapyd\Helpers\HTML;

class Column
{
    public $name;
    public $link = '';
    public $label = '';
    public $orderby;
    public $orderby_field;
    public $attributes = [];
    public $filters = [];

    public $key = 'id';
    public $uri;
    public $actions = [];

    public $value;
    public $cell_callable;

    public function __construct($name, $label = null, $orderby = false)
    {
        if (!$name) {
            throw new Exception('$name must be given when creating Column object.');
        }

        $this->name = $name;

        //check for filters
        $filter = mb_strstr($name, '|');
        if ($filter) {
            $this->name = mb_strstr($name, '|', true);
            $this->filter(trim($filter, '|'));
        }

        $this->label($label);
        $this->orderby($orderby);
    }

    public function filter($filters)
    {
        if (\is_string($filters)) {
            $filters = explode('|', trim($filters));
        }
        if (\is_array($filters)) {
            $this->filters = $filters;
        }

        return $this;
    }

    public function link($url)
    {
        $this->link = $url;

        return $this;
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

    public function actions($uri, $actions)
    {
        $this->uri = $uri;
        $this->actions = $actions;

        return $this;
    }

    public function key($key)
    {
        $this->key = $key;

        return $this;
    }

    public function cell(Closure $callable)
    {
        $this->cell_callable = $callable;

        return $this;
    }

    public function buildAttributes()
    {
        return HTML::buildAttributes($this->attributes);
    }

    protected function label($label)
    {
        $this->label = $label;
    }

    protected function orderby($orderby)
    {
        $this->orderby = (bool) $orderby;
        if ($this->orderby) {
            $this->orderby_field = (\is_string($orderby)) ? $orderby : $this->name;
        }

        return $this;
    }
}
