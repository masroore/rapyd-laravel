<?php

namespace Zofe\Rapyd\DataForm;

use ArrayObject;
use Closure;
use Collective\Html\FormFacade as Form;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Zofe\Rapyd\DataForm\Field\Auto;
use Zofe\Rapyd\DataForm\Field\Autocomplete;
use Zofe\Rapyd\DataForm\Field\Colorpicker;
use Zofe\Rapyd\DataForm\Field\Date;
use Zofe\Rapyd\DataForm\Field\Field;
use Zofe\Rapyd\DataForm\Field\File;
use Zofe\Rapyd\DataForm\Field\Hidden;
use Zofe\Rapyd\DataForm\Field\Number;
use Zofe\Rapyd\DataForm\Field\Numberrange;
use Zofe\Rapyd\DataForm\Field\Password;
use Zofe\Rapyd\DataForm\Field\Radiogroup;
use Zofe\Rapyd\DataForm\Field\Redactor;
use Zofe\Rapyd\DataForm\Field\Select;
use Zofe\Rapyd\DataForm\Field\Tags;
use Zofe\Rapyd\DataForm\Field\Text;
use Zofe\Rapyd\DataForm\Field\Textarea;
use Zofe\Rapyd\Rapyd;
use Zofe\Rapyd\Widget;

/**
 * Class DataForm.
 *
 * @method Text         text        (string $name, string $label, $validation = '')
 * @method Hidden       hidden      (string $name, string $label, string $validation = '')
 * @method Password     password    (string $name, string $label, string $validation = '')
 * @method File         file        (string $name, string $label, string $validation = '')
 * @method Textarea     textarea    (string $name, string $label, string $validation = '')
 * @method Select       select      (string $name, string $label, string $validation = '')
 * @method Radiogroup   radiogroup  (string $name, string $label, string $validation = '')
 * @method Redactor     redactor    (string $name, string $label, string $validation = '')
 * @method Autocomplete autocomplete(string $name, string $label, string $validation = '')
 * @method Tags         tags        (string $name, string $label, string $validation = '')
 * @method Number       number      (string $name, string $label, string $validation = '')
 * @method Numberrange  numberrange (string $name, string $label, string $validation = '')
 * @method Colorpicker  colorpicker (string $name, string $label, string $validation = '')
 * @method Date         date        (string $name, string $label, string $validation = '')
 * @method Auto         auto        (string $name, string $label, string $validation = '')
 * @method Text         addText        (string $name, string $label, $validation = '')
 * @method Hidden       addHidden      (string $name, string $label, string $validation = '')
 * @method Password     addPassword    (string $name, string $label, string $validation = '')
 * @method File         addFile        (string $name, string $label, string $validation = '')
 * @method Textarea     addTextarea    (string $name, string $label, string $validation = '')
 * @method Select       addSelect      (string $name, string $label, string $validation = '')
 * @method Radiogroup   addRadiogroup  (string $name, string $label, string $validation = '')
 * @method Redactor     addRedactor    (string $name, string $label, string $validation = '')
 * @method Autocomplete addAutocomplete(string $name, string $label, string $validation = '')
 * @method Tags         addTags        (string $name, string $label, string $validation = '')
 * @method Number       addNumber      (string $name, string $label, string $validation = '')
 * @method Numberrange  addNumberrange (string $name, string $label, string $validation = '')
 * @method Colorpicker  addColorpicker (string $name, string $label, string $validation = '')
 * @method Date         addDate        (string $name, string $label, string $validation = '')
 * @method Auto         addAuto        (string $name, string $label, string $validation = '')
 */
class DataForm extends Widget
{
    public $model;
    public $model_relations;
    public $validator;
    public $validator_messages = [];

    public $output = '';
    public $custom_output;
    public $fields = [];
    public $hash = '';
    public $error = '';

    public $open;
    public $close;

    protected $method = 'POST';
    protected $redirect;
    protected $source;
    protected $process_url = '';
    protected $view = 'rapyd::dataform';
    protected $orientation = 'horizontal';
    protected $has_labels = true;
    protected $has_placeholders = false;
    protected $form_callable = '';

    public function __construct()
    {
        parent::__construct();
        $this->process_url = $this->url->append('process', 1)->get();
        $this->model_relations = new ArrayObject();
    }

    public function __toString()
    {
        if ('' == $this->output) {

            //to avoid the error "toString() must not throw an exception"
            //http://stackoverflow.com/questions/2429642/why-its-impossible-to-throw-exception-from-tostring/27307132#27307132
            try {
                $this->getForm();
            } catch (Exception $e) {
                $previousHandler = set_exception_handler(static function () {
                });
                restore_error_handler();
                \call_user_func($previousHandler, $e);
                die;
            }
        }

        return $this->output;
    }

    /**
     * Magic method to catch all appends.
     *
     * @param string $name
     * @param array  $arguments
     */
    public function __call($name, $arguments)
    {
        if (0 === mb_strpos($name, 'add')) {
            $name = mb_substr($name, 3);
        }

        $classname = '\Zofe\Rapyd\DataForm\Field\\' . ucfirst($name);

        if (class_exists($classname)) {
            array_push($arguments, $name);

            return \call_user_func_array([$this, 'add'], $arguments);
        }
    }

    /**
     * @return static
     */
    public static function create()
    {
        $ins = new static();
        $ins->cid = $ins->getIdentifier();
        $ins->sniffStatus();
        $ins->sniffAction();

        return $ins;
    }

    /**
     * @param Model $source
     *
     * @return static
     */
    public static function source(?Model $source = null)
    {
        $ins = new static();
        if (\is_object($source) && is_a($source, Model::class)) {
            $ins->model = $source;
        }
        $ins->cid = $ins->getIdentifier();
        $ins->sniffStatus();
        $ins->sniffAction();

        return $ins;
    }

    /**
     * @param string $view
     *
     * @return string
     */
    public function getForm($view = '')
    {
        $this->build($view);

        return $this->output;
    }

    /**
     * build form output and prepare form partials (header / footer / ..).
     *
     * @param string $view
     */
    public function build($view = '')
    {
        if (isset($this->attributes['class']) and false !== mb_strpos($this->attributes['class'], 'form-inline')) {
            $this->view = 'rapyd::dataform_inline';
            $this->orientation = 'inline';
            $this->has_labels = false;
        }
        if (isset($this->attributes['class']) and false !== mb_strpos($this->attributes['class'], 'without-labels')) {
            $this->has_labels = false;
        }
        if (isset($this->attributes['class']) and false !== mb_strpos($this->attributes['class'], 'with-placeholders')) {
            $this->has_placeholders = true;
        }
        if (!blank($this->output)) {
            return;
        }
        if (!blank($view)) {
            $this->view = $view;
        }

        $this->process();

        //callable
        if ($this->form_callable && 'success' == $this->process_status) {
            $callable = $this->form_callable;
            $result = $callable($this);
            if ($result && is_a($result, RedirectResponse::class)) {
                $this->redirect = $result;
            } elseif ($result && is_a($result, \Illuminate\View\View::class)) {
                $this->custom_output = $result;
            }

            //reprocess if an error is added in closure
            if ('error' == $this->process_status) {
                $this->process();
            }
        }
        //cleanup submits if success
        if ('success' == $this->process_status) {
            $this->removeType('submit');
        }
        $this->buildButtons();
        $this->buildFields();
        $dataform = $this->buildForm();
        $this->output = $dataform->render();

        $sections = $dataform->renderSections();
        $this->header = $sections['df.header'];
        $this->footer = $sections['df.footer'];
        $this->body = @$sections['df.fields'];
        Rapyd::setForm($this);
    }

    /**
     * remove field where type==$type from field list and button container.
     *
     * @param $type
     *
     * @return $this
     */
    public function removeType($type)
    {
        foreach ($this->fields as $fieldname => $field) {
            if ($field->type == $type) {
                unset($this->fields[$fieldname]);
            }
        }
        foreach ($this->button_container as $container => $buttons) {
            foreach ($buttons as $key => $button) {
                if (false !== mb_strpos($button, 'type="' . $type . '"')) {
                    $this->button_container[$container][$key] = '';
                }
            }
        }

        return $this;
    }

    public function prepareForm()
    {
        $form_attr = ['url' => $this->process_url, 'class' => 'form-horizontal', 'role' => 'form', 'method' => $this->method];
        $form_attr = array_merge($form_attr, $this->attributes);

        // See if we need a multipart form
        foreach ($this->fields as $field_obj) {
            if (\in_array($field_obj->type, ['file', 'image'])) {
                $form_attr['files'] = 'true';
                break;
            }
        }
        // Set the form open and close
        if ('show' == $this->status) {
            $this->open = '<div class="' . ($form_attr['class'] ?? 'form') . '">';
            $this->close = '</div>';
        } else {
            $this->open = Form::open($form_attr);
            $this->close = Form::hidden('save', 1) . Form::close();

            if ('GET' == $this->method) {
                $this->close = Form::hidden('search', 1) . Form::close();
            }
        }
        if (isset($this->validator)) {
            $this->errors = $this->validator->messages();
            $this->error .= implode('<br />', $this->errors->all());
        }
    }

    /**
     * remove field from list.
     *
     * @param $fieldname
     *
     * @return $this
     */
    public function remove($fieldname)
    {
        if (isset($this->fields[$fieldname])) {
            unset($this->fields[$fieldname]);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param string $position
     * @param array  $options
     *
     * @return $this
     */
    public function submit($name, $position = 'BL', $options = [])
    {
        $options = array_merge(['class' => 'btn btn-primary'], $options);
        $this->button_container[$position][] = Form::submit($name, $options);

        return $this;
    }

    /**
     * @param string $name
     * @param string $position
     * @param array  $options
     *
     * @return $this
     */
    public function reset($name = '', $position = 'BL', $options = [])
    {
        if ('' == $name) {
            $name = trans('rapyd::rapyd.reset');
        }
        $this->link($this->url->current(true), $name, $position, $options);

        return $this;
    }

    /**
     * get entire field output (label, output, and messages).
     *
     * @param $field_name
     *
     * @return string
     */
    public function render($field_name, array $attributes = [])
    {
        $field = $this->field($field_name, $attributes);

        return $field->all();
    }

    /**
     * get field instance from fields array.
     */
    public function field(string $field_name, array $attributes = []): Field
    {
        if (isset($this->fields[$field_name])) {
            $field = $this->fields[$field_name];
            if (\count($attributes)) {
                $field->attributes($attributes);
                $field->build();
            }

            return $field;
        }
    }

    /**
     * add custom error messages to the validator inscance.
     *
     * @param array $messages
     */
    public function errors($messages = []): void
    {
        $this->validator_messages = $messages;
    }

    /**
     * append error (to be used in passed/saved closure).
     *
     * @return $this
     */
    public function error($error)
    {
        $this->process_status = 'error';
        $this->message = '';
        $this->error .= $error;

        return $this;
    }

    /**
     * @param array|string $process_status
     */
    public function on($process_status = 'false'): bool
    {
        if (\is_array($process_status)) {
            return (bool) \in_array($this->process_status, $process_status);
        }

        return $this->process_status == $process_status;
    }

    public function getRedirect(): string
    {
        return $this->redirect;
    }

    /**
     * @param string $viewname
     * @param array  $array    of values for view
     *
     * @return Redirect|View
     */
    public function view($viewname = 'rapyd::form', $array = [])
    {
        if (!isset($array['form'])) {
            $form = $this->getForm();
            $array['form'] = $form;
        }
        if ($this->hasRedirect()) {
            return ($this->redirect instanceof RedirectResponse) ? $this->redirect : Redirect::to($this->redirect);
        }
        if ($this->hasCustomOutput()) {
            return $this->custom_output;
        }

        return View::make($viewname, $array);
    }

    public function hasRedirect(): bool
    {
        return null != $this->redirect;
    }

    public function hasCustomOutput(): bool
    {
        return null != $this->custom_output;
    }

    /**
     * alias for saved.
     */
    public function passed(Closure $callable)
    {
        $this->saved($callable);
    }

    /**
     * build form and check if process status is "success" then execute a callable.
     */
    public function saved(Closure $callable)
    {
        $this->form_callable = $callable;
    }

    /**
     * Set a value to model without show anything (it appends an auto-field)
     * It set value on insert and update (but is configurable).
     *
     * @param array|string $field
     * @param $value
     * @param bool $insert
     * @param bool $update
     */
    public function set($field, $value = null, $insert = true, $update = true): void
    {
        if (\is_array($field)) {
            foreach ($field as $key => $val) {
                $this->set($key, $val, $insert, $update);
            }
        }

        $this->add($field, '', 'auto');
        if ($insert) {
            $this->field($field)->insertValue($value);
        }

        if ($update) {
            $this->field($field)->updateValue($value);
        }
    }

    public function add(string $name, string $label, string $type, string $validation = ''): Field
    {
        if (false !== mb_strpos($type, '\\')) {
            $field_class = $type;
        } else {
            $field_class = '\Zofe\Rapyd\DataForm\Field\\' . ucfirst($type);
        }

        //instancing
        if (isset($this->model)) {
            $field_obj = new $field_class($name, $label, $this->model, $this->model_relations);
        } else {
            $field_obj = new $field_class($name, $label);
        }

        if (!$field_obj instanceof Field) {
            throw new InvalidArgumentException('Third argument («type») must point to class inherited Field class');
        }

        if ('file' === $field_obj->type) {
            $this->multipart = true;
        }

        //default group
        if (isset($this->default_group) && !isset($field_obj->group)) {
            $field_obj->group = $this->default_group;
        }
        $this->fields[$name] = $field_obj;

        return $field_obj;
    }

    public function compact()
    {
        $this->has_labels = false;
        $this->has_placeholders = true;

        return $this;
    }

    protected function sniffStatus(): void
    {
        if (isset($this->model)) {
            $this->status = ($this->model->exists) ? 'modify' : 'create';
        } else {
            $this->status = 'create';
        }
    }

    protected function sniffAction()
    {
        if (Request::isMethod('post') && ($this->url->value('process'))) {
            $this->action = ('modify' == $this->status) ? 'update' : 'insert';
        }
    }

    protected function process()
    {
        //database save
        switch ($this->action) {
            case 'update':
            case 'insert':
                //validation failed
                if (!$this->isValid()) {
                    $this->process_status = 'error';
                    foreach ($this->fields as $field) {
                        $field->action = 'idle';
                    }

                    return false;
                }
                $this->process_status = 'success';

                foreach ($this->fields as $field) {
                    $field->action = $this->action;
                    $result = $field->autoUpdate();
                    if (!$result) {
                        $this->process_status = 'error';

                        return false;
                    }
                }
                if (isset($this->model)) {
                    $return = $this->model->save();
                    if (null === $return) {
                        // in the cases where an error has not been returned, but a record has been inserted or updated
                        // the model->save() method returns null which need to interpret as success
                        $return = true;
                    }
                } else {
                    $return = true;
                }
                if (!$return) {
                    $this->process_status = 'error';
                }

                return $return;
                break;
            case 'delete':
                $return = $this->model->delete();
                if (!$return) {
                    $this->process_status = 'error';
                } else {
                    $this->process_status = 'success';
                }
                break;
            case 'idle':
                $this->process_status = 'show';

                return true;
                break;
            default:
                return false;
        }
    }

    /**
     * @return bool
     */
    protected function isValid()
    {
        if (!blank($this->error)) {
            return false;
        }
        foreach ($this->fields as $field) {
            $field->action = $this->action;
            if (isset($field->rule)) {
                $rules[$field->name] = $field->rule;
                $attributes[$field->name] = $field->label;
            }
        }
        if (isset($this->validator)) {
            return !$this->validator->fails();
        }
        if (isset($rules)) {
            $this->validator = Validator::make(Request::all(), $rules, $this->validator_messages, $attributes);

            return !$this->validator->fails();
        }

        return true;
    }

    /**
     * needed by DataEdit, to build standard action buttons.
     */
    protected function buildButtons()
    {
    }

    /**
     * build each field and share some data from dataform to field (form status, validation errors).
     */
    protected function buildFields()
    {
        $messages = (isset($this->validator)) ? $this->validator->messages() : false;

        foreach ($this->fields as $field) {
            $field->status = $this->status;
            $field->orientation = $this->orientation;
            $field->has_label = $this->has_labels;
            $field->has_placeholder = $this->has_placeholders;
            if ($messages and $messages->has($field->name)) {
                $field->messages = $messages->get($field->name);
                $field->has_error = ' has-error';
            }
            $field->build();
        }
    }

    protected function buildForm()
    {
        $this->prepareForm();
        $df = $this;

        return View::make($this->view, compact('df'));
    }
}
