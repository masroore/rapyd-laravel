<?php

namespace Zofe\Rapyd\DataFilter;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Zofe\Rapyd\DataForm\DataForm;
use Zofe\Rapyd\Persistence;

class DataFilter extends DataForm
{
    public $cid;
    public $source;
    public $attributes = ['class' => 'form-inline'];
    /**
     * @var Builder
     */
    public $query;
    protected $process_url = '';
    protected $reset_url = '';

    /**
     * @param $source
     *
     * @return static
     */
    public static function source($source = null)
    {
        $ins = new static();
        $ins->source = $source;
        $ins->query = $source;
        if (\is_object($source) && (is_a($source, EloquentBuilder::class) ||
                is_a($source, Model::class))) {
            $ins->model = $source->getModel();
        }
        $ins->cid = $ins->getIdentifier();
        $ins->sniffStatus();
        $ins->sniffAction();

        return $ins;
    }

    public function getResetUrl()
    {
        return $this->reset_url;
    }

    protected function sniffAction()
    {
        $this->reset_url = $this->url->remove('ALL')->append('reset' . $this->cid, 1)->get();
        $this->process_url = $this->url->remove('ALL')->append('search' . $this->cid, 1)->get();

        ///// search /////
        if ($this->url->value('search')) {
            $this->action = 'search';

            Persistence::save();
        } ///// reset /////
        elseif ($this->url->value('reset')) {
            $this->action = 'reset';

            Persistence::clear();
        } else {
            Persistence::clear();
        }
    }

    protected function table($table)
    {
        $this->query = DB::table($table);

        return $this->query;
    }

    protected function process()
    {
        $this->method = 'GET';

        //database save
        switch ($this->action) {
            case 'search':

                // prepare the WHERE clause
                foreach ($this->fields as $field) {
                    $field->getValue();
                    $field->getNewValue();
                    $value = $field->new_value;

                    //query scope
                    $query_scope = $field->query_scope;
                    $query_scope_params = $field->query_scope_params;
                    if ($query_scope) {
                        if (is_a($query_scope, '\Closure')) {
                            array_unshift($query_scope_params, $value);
                            array_unshift($query_scope_params, $this->query);
                            $this->query = \call_user_func_array($query_scope, $query_scope_params);
                        } elseif (isset($this->model) && method_exists($this->model, 'scope' . $query_scope)) {
                            $query_scope = 'scope' . $query_scope;
                            array_unshift($query_scope_params, $value);
                            array_unshift($query_scope_params, $this->query);
                            $this->query = \call_user_func_array([$this->model, $query_scope], $query_scope_params);
                        }
                        continue;
                    }

                    //detect if where should be deep (on relation)
                    $deep_where = false;

                    if (isset($this->model) && null != $field->relation) {
                        $rel_type = \get_class($field->relation);

                        if (
                            is_a($field->relation, HasOne::class)
                            || is_a($field->relation, HasMany::class)
                            || is_a($field->relation, BelongsTo::class)
                            || is_a($field->relation, BelongsToMany::class)
                        ) {
                            if (
                                is_a($field->relation, BelongsTo::class) and
                                \in_array($field->type, ['select', 'radiogroup', 'autocomplete'])
                            ) {
                                $deep_where = false;
                            } else {
                                $deep_where = true;
                            }
                        }
                    }

                    if ('' != $value or (\is_array($value) and \count($value))) {
                        if (mb_strpos($field->name, '_copy') > 0) {
                            $name = mb_substr($field->db_name, 0, mb_strpos($field->db_name, '_copy'));
                        } else {
                            $name = $field->db_name;
                        }

                        //$value = $field->value;

                        if ($deep_where) {
                            //exception for multiple value fields on BelongsToMany
                            if (
                                (is_a($field->relation, BelongsToMany::class)
                                    || is_a($field->relation, BelongsTo::class)
                                ) and
                                \in_array($field->type, ['tags', 'checks', 'multiselect'])
                            ) {
                                $values = explode($field->serialization_sep, $value);

                                if ('wherein' == $field->clause) {
                                    $this->query = $this->query->whereHas($field->rel_name, static function ($q) use ($field, $values) {
                                        $q->whereIn($field->rel_fq_key, $values);
                                    });
                                }

                                if ('where' == $field->clause) {
                                    foreach ($values as $v) {
                                        $this->query = $this->query->whereHas($field->rel_name, static function ($q) use ($field, $v) {
                                            $q->where($field->rel_fq_key, '=', $v);
                                        });
                                    }
                                }
                                continue;
                            }

                            switch ($field->clause) {
                                case 'like':
                                    $this->query = $this->query->whereHas($field->rel_name, static function ($q) use ($field, $value) {
                                        $q->where($field->rel_field, 'LIKE', '%' . $value . '%');
                                    });
                                    break;
                                case 'orlike':
                                    $this->query = $this->query->orWhereHas($field->rel_name, static function ($q) use ($field, $value) {
                                        $q->where($field->rel_field, 'LIKE', '%' . $value . '%');
                                    });
                                    break;
                                case 'where':
                                    $this->query = $this->query->whereHas($field->rel_name, static function ($q) use ($field, $value) {
                                        $q->where($field->rel_field, $field->operator, $value);
                                    });
                                    break;
                                case 'orwhere':
                                    $this->query = $this->query->orWhereHas($field->rel_name, static function ($q) use ($field, $value) {
                                        $q->where($field->rel_field, $field->operator, $value);
                                    });
                                    break;
                                case 'wherebetween':
                                    $values = explode($field->serialization_sep, $value);
                                    $this->query = $this->query->whereHas($field->rel_name, static function ($q) use ($field, $values) {
                                        if ('' != $values[0] and '' == $values[1]) {
                                            $q->where($field->rel_field, '>=', $values[0]);
                                        } elseif ('' == $values[0] and '' != $values[1]) {
                                            $q->where($field->rel_field, '<=', $values[1]);
                                        } elseif ('' != $values[0] and '' != $values[1]) {

                                            //we avoid "whereBetween" because a bug in laravel 4.1
                                            $q->where(
                                                static function ($query) use ($field, $values) {
                                                    return $query->where($field->rel_field, '>=', $values[0])
                                                        ->where($field->rel_field, '<=', $values[1]);
                                                }
                                            );
                                        }
                                    });
                                    break;
                                case 'orwherebetween':
                                    $values = explode($field->serialization_sep, $value);
                                    $this->query = $this->query->orWhereHas($field->rel_name, static function ($q) use ($field, $values) {
                                        if ('' != $values[0] and '' == $values[1]) {
                                            $q->orWhere($field->rel_field, '>=', $values[0]);
                                        } elseif ('' == $values[0] and '' != $values[1]) {
                                            $q->orWhere($field->rel_field, '<=', $values[1]);
                                        } elseif ('' != $values[0] and '' != $values[1]) {

                                            //we avoid "whereBetween" because a bug in laravel 4.1
                                            $q->orWhere(
                                                static function ($query) use ($field, $values) {
                                                    return $query->where($field->rel_field, '>=', $values[0])
                                                        ->where($field->rel_field, '<=', $values[1]);
                                                }
                                            );
                                        }
                                    });
                                    break;
                            }

                            //not deep, where is on main entity
                        } else {
                            switch ($field->clause) {
                                case 'like':
                                    $this->query = $this->query->where($name, 'LIKE', '%' . $value . '%');
                                    break;
                                case 'orlike':
                                    $this->query = $this->query->orWhere($name, 'LIKE', '%' . $value . '%');
                                    break;
                                case 'where':
                                    $this->query = $this->query->where($name, $field->operator, $value);
                                    break;
                                case 'orwhere':
                                    $this->query = $this->query->orWhere($name, $field->operator, $value);
                                    break;
                                case 'wherein':
                                    $this->query = $this->query->whereIn($name, explode($field->serialization_sep, $value));
                                    break;
                                case 'wherebetween':
                                    $values = explode($field->serialization_sep, $value);
                                    if (2 == \count($values)) {
                                        if ('' != $values[0] and '' == $values[1]) {
                                            $this->query = $this->query->where($name, '>=', $values[0]);
                                        } elseif ('' == $values[0] and '' != $values[1]) {
                                            $this->query = $this->query->where($name, '<=', $values[1]);
                                        } elseif ('' != $values[0] and '' != $values[1]) {

                                            //we avoid "whereBetween" because a bug in laravel 4.1
                                            $this->query = $this->query->where(
                                                static function ($query) use ($name, $values) {
                                                    return $query->where($name, '>=', $values[0])
                                                        ->where($name, '<=', $values[1]);
                                                }
                                            );
                                        }
                                    }

                                    break;
                                case 'orwherebetween':
                                    $values = explode($field->serialization_sep, $value);
                                    if (2 == \count($values)) {
                                        if ('' != $values[0] and '' == $values[1]) {
                                            $this->query = $this->query->orWhere($name, '>=', $values[0]);
                                        } elseif ('' == $values[0] and '' != $values[1]) {
                                            $this->query = $this->query->orWhere($name, '<=', $values[1]);
                                        } elseif ('' != $values[0] and '' != $values[1]) {
                                            //we avoid "whereBetween" because a bug in laravel 4.1
                                            $this->query = $this->query->orWhere(
                                                static function ($query) use ($name, $values) {
                                                    return $query->where($name, '>=', $values[0])
                                                        ->where($name, '<=', $values[1]);
                                                }
                                            );
                                        }
                                    }

                                    break;
                            }
                        }
                    }
                }
                // dd($this->query->toSql());
                break;
            case 'reset':
                $this->process_status = 'show';

                return true;
                break;
            default:
                return false;
        }
    }
}
