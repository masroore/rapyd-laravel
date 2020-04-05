<?php

namespace Zofe\Rapyd\DataForm\Field;

use Collective\Html\FormFacade as Form;
use Zofe\Rapyd\Rapyd;

class Datetime extends Field
{
    public $type = 'datetime';
    public $format = 'm/d/Y H:i:s';
    public $store_as = 'Y-m-d H:i:s';
    public $language = 'en';

    public function __construct($name, $label, &$model = null, &$model_relations = null)
    {
        parent::__construct($name, $label, $model, $model_relations);
        $this->language = config('app.locale', $this->language);
        $this->format = config('rapyd.fields.datetime.format', $this->format);
        $this->store_as = config('rapyd.fields.datetime.store_as', $this->store_as);
        //dd($this->language, $this->format, $this->store_as);
    }

    /**
     * set instarnal preview datetime format.
     *
     * @param $format valid php datetime format
     * @param string     $language valid datetimePicker language string http://www.malot.fr/bootstrap-datetimepicker/
     * @param mixed|null $store_as
     */
    public function format($format, $language = 'en', $store_as = null)
    {
        $this->format = $format;
        $this->language = $language;
        if ($store_as) {
            $this->store_as = $store_as;
        }

        return $this;
    }

    /**
     * overwrite new value to store a iso datetime.
     */
    public function getNewValue()
    {
        parent::getNewValue();
        $this->new_value = $this->humandatetimeToIso($this->new_value);
    }

    public function build()
    {
        $output = '';

        unset($this->attributes['type']);
        if (false === parent::build()) {
            return;
        }

        switch ($this->status) {

            case 'show':
                if (!isset($this->value)) {
                    $value = $this->layout['null_label'];
                } else {
                    $value = $this->isodatetimeToHuman($this->value);
                }
                $output = $value;
                $output = "<div class='help-block'>" . $output . '&nbsp;</div>';
                break;

            case 'create':
            case 'modify':
                if ('' != $this->value) {
                    if (!$this->is_refill) {
                        $this->value = $this->isodatetimeToHuman($this->value);
                    }
                }

                Rapyd::css('datetimepicker/datetimepicker3.css');
                Rapyd::js('datetimepicker/bootstrap-datetimepicker.js');
                if ('en' != $this->language) {
                    Rapyd::js('datetimepicker/locales/bootstrap-datetimepicker.' . $this->language . '.js');
                }

                $output = Form::text($this->name, $this->value, $this->attributes);
                Rapyd::script("
                        $('#" . $this->name . "').datetimepicker({
                            format: '{$this->formatTodatetime()}',
                            language: '{$this->language}',
                            todayBtn: 'linked',
                            autoclose: true
                        });");

                break;
            case 'hidden':
                $output = Form::hidden($this->db_name, $this->value);
                break;
            default:
        }
        $this->output = $output;
    }

    /**
     * convert from current user format to iso datetime.
     */
    protected function humandatetimeToIso($humandatetime)
    {
        $datetime = \DateTime::createFromFormat($this->format, $humandatetime);
        if (!$datetime) {
            return '';
        }
        $timestamp = $datetime->getTimestamp();
        if ($timestamp < 1) {
            return '';
        }

        return date($this->store_as, $timestamp);
    }

    /**
     * convert from iso datetime to user format.
     */
    protected function isodatetimeToHuman($isodatetime)
    {
        $datetime = \DateTime::createFromFormat($this->store_as, $isodatetime);
        if (!$datetime) {
            return '';
        }
        $timestamp = $datetime->getTimestamp();
        if ($timestamp < 1) {
            return '';
        }

        return date($this->format, $timestamp);
    }

    /**
     * simple translation from php datetime format to datetimePicker format
     * basic translation of numeric timestamps m/d/Y H:i:s, d/m/Y g:i:s, ...
     *
     * @return string valid datetimepicker format http://www.malot.fr/bootstrap-datetimepicker/
     */
    protected function formatTodatetime()
    {
        $format = $this->format;

        return str_replace(['d', 'm', 'Y', 'H', 'i', 's', 'a', 'A', 'g', 'G'],
            ['dd', 'mm', 'yyyy', 'hh', 'ii', 'ss', 'p', 'P', 'H', 'h'],
            $format
        );
    }
}
