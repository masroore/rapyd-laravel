<?php

use Zofe\Rapyd\Rapyd;

Collective\Html\FormFacade::macro('field', static function ($field) {
    $form = Rapyd::getForm();
    if ($form) {
        return $form->field($field);
    }
});
