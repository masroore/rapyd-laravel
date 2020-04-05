

@if (in_array("show", $actions))
    <a class="row-action action-show" title="@lang('rapyd::rapyd.show')" href="{!! $uri !!}?show={!! $id !!}"><i class="fa fa-eye"></i></a>
@endif
@if (in_array("modify", $actions))
    <a class="row-action action-modify" title="@lang('rapyd::rapyd.modify')" href="{!! $uri !!}?modify={!! $id !!}"><i class="fa fa-pencil-square"></i></a>
@endif
@if (in_array("delete", $actions))
    <a class="row-action action-delete text-danger" title="@lang('rapyd::rapyd.delete')" href="{!! $uri !!}?delete={!! $id !!}"><i class="fa fa-trash-o"></i></a>
@endif
