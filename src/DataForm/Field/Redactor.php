<?php

namespace Zofe\Rapyd\DataForm\Field;

use Collective\Html\FormFacade as Form;
use Zofe\Rapyd\Rapyd;

class Redactor extends Field
{
    public $type = 'text';

    public function build()
    {
        $output = '';
        if (false === parent::build()) {
            return;
        }

        switch ($this->status) {
            case 'disabled':
            case 'show':

                if ('hidden' == $this->type || '' == $this->value) {
                    $output = '';
                } elseif ((!isset($this->value))) {
                    $output = $this->layout['null_label'];
                } else {
                    $output = nl2br(htmlspecialchars($this->value));
                }
                $output = "<div class='help-block'>" . $output . '&nbsp;</div>';
                break;

            case 'create':
            case 'modify':

                Rapyd::js('redactor/jquery.browser.min.js');
                Rapyd::js('redactor/redactor.min.js');
                Rapyd::css('redactor/css/redactor.css');
                $output = Form::textarea($this->name, $this->value, $this->attributes);
                Rapyd::script("$('[id=\"" . $this->name . "\"]').redactor();");

                break;

            case 'hidden':
                $output = Form::hidden($this->name, $this->value);
                break;

            default:
        }
        $this->output = "\n" . $output . "\n" . $this->extra_output . "\n";
    }
}
